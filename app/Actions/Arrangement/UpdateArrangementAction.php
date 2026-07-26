<?php

namespace App\Actions\Arrangement;

use App\Models\Listing;
use App\Services\MediaService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateArrangementAction
{
    public function __construct(
        private ValidatePackageItemsAction $validateItems,
        private ValidatePackageFreelancersAction $validateFreelancers,
        private SyncPackageItemsAction $syncItems,
        private SyncPackageFreelancersAction $syncFreelancers,
        private SyncArrangementAvailabilitiesAction $syncAvailabilities,
        private MediaService $mediaService,
    ) {}

    public function execute(Listing $listing, array $data, string $providerId): Listing
    {
        if ($listing->provider_id !== $providerId) {
            throw new Exception('لا تملك صلاحية تعديل هذا الترتيب.', 403);
        }

        if (isset($data['items'])) {
            $this->validateItems->execute($data['items'], $providerId);
        }

        // الخطوة 8: إعادة فحص تعارض التواريخ عند التحديث.
        // يجب الفحص في حالتين: 
        // 1) إذا تم إرسال قائمة فريلانسرز جديدة.
        // 2) أو إذا تم تغيير تواريخ التنسيق (availabilities/date_range) وكان هناك فريلانسرز مربوطون مسبقاً.
      $freelancersSent = array_key_exists('freelancers', $data);
        $freelancers = $data['freelancers'] ?? null;
        $newDates = $this->extractArrangementDates($data);

        // إذا لم يتم إرسال freelancers إطلاقاً، نتحقق من الفريلانسرز الحاليين المرتبطين بالتنسيق
        if (!$freelancersSent && !empty($newDates)) {
            $variant = $listing->variants()->first();
            if ($variant) {
                $freelancers = $variant->packageFreelancers->map(fn($pf) => [
                    'freelancer_id' => $pf->freelancer_id,
                    'contract_id'   => $pf->contract_id,
                ])->toArray();
            }
        }
      
        if (!empty($freelancers)) {
            // نمرر التواريخ (سواء الجديدة أو الحالية إذا لم تتغير) للفحص
            $checkDates = !empty($newDates) ? $newDates : $this->getCurrentArrangementDates($listing);
            $this->validateFreelancers->execute($freelancers, $providerId, $checkDates);
        }

        return DB::transaction(function () use ($listing, $data, $providerId) {
            $listingFields = [
                'category_id', 'district_id', 'title', 'description',
                'secondary_contact_number', 'is_provider_location_based',
                'cancel_before_acceptance', 'cancel_after_acceptance', 'cancel_before_payment',
            ];
            $listingPayload = array_intersect_key($data, array_flip($listingFields));
            if ($listingPayload) {
                $listing->update($listingPayload);
            }

            $variant = $listing->variants()->firstOrFail();

            $variantPayload = array_intersect_key($data, array_flip(['price', 'price_type', 'currency']));
            if (isset($data['capacity'])) {
                $variantPayload['dynamic_attributes'] = array_merge(
                    $variant->dynamic_attributes ?? [],
                    ['capacity' => $data['capacity']]
                );
            }
            if (isset($data['title'])) {
                $variantPayload['variant_name'] = $data['title'];
            }
            if ($variantPayload) {
                $variant->update($variantPayload);
            }

            if (isset($data['items'])) {
                $this->syncItems->execute($variant->id, $data['items']);
            }
            if (isset($data['freelancers'])) {
                $this->syncFreelancers->execute($variant->id, $data['freelancers']);
            }

            // ── مزامنة التواريخ والمواعيد: بنفس أولوية الـ Listing تماماً ──────
            // - availabilities موجودة  → مزامنة مباشرة بالـ ID (تعديل/حذف آمن).
            // - وإلا date_range موجود  → تفريد النطاق لأيام فردية ثم نفس المزامنة الآمنة.
            $defaultCapacity = $data['capacity'] ?? ($variant->dynamic_attributes['capacity'] ?? 1);

            if (!empty($data['availabilities'])) {
                $this->syncAvailabilities->execute($variant, $data['availabilities'], $defaultCapacity);
            } elseif (!empty($data['date_range'])) {
                $availabilities = $this->syncAvailabilities->buildAvailabilitiesFromRange($data['date_range']);
                $this->syncAvailabilities->execute($variant, $availabilities, $defaultCapacity);
            }

            // ── إدارة الصور بنفس منطق الـ Listing تماماً (3 حالات) ─────────────
            // - images غير موجودة في الـ request  → لا تمس الصور.
            // - images: []                         → احذف كل الصور.
            // - images: [{id:...}, {path:...}]     → sync (احتفظ بالقديمة وارفع الجديدة).
            if (array_key_exists('images', $data)) {
                $images = $data['images'] ?? [];

                $keepIds = collect($images)
                    ->filter(fn ($img) => is_array($img) && !empty($img['id']))
                    ->pluck('id')
                    ->toArray();

                $listing->images()->whereNotIn('id', $keepIds)->delete();

                foreach ($images as $img) {
                    if (is_array($img) && empty($img['id']) && !empty($img['path'])) {
                        $cleanPath = Str::startsWith($img['path'], 'temp/')
                            ? $img['path']
                            : 'temp/' . $img['path'];

                        try {
                            $this->mediaService->moveAndAttach(
                                $cleanPath,
                                $listing,
                                "arrangements/{$listing->id}/main"
                            );
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error("خطأ أثناء نقل صورة الترتيب {$cleanPath}: " . $e->getMessage());
                        }
                    }
                }
            }

            return $listing->load([
                'variants.packageItems.includedVariant.listing',
                'variants.packageFreelancers.freelancer',
                'variants.availabilities.slots',
                'images', 'category', 'district',
            ]);
        });
    }

    /**
     * استخراج قائمة تواريخ التنسيق المسطّحة (Y-m-d) من البيانات المدخلة.
     */
    private function extractArrangementDates(array $data): array
    {
        if (!empty($data['availabilities'])) {
            return collect($data['availabilities'])
                ->flatMap(function ($availability) {
                    $date = \Illuminate\Support\Carbon::parse($availability['available_date'])->format('Y-m-d');
                    $slots = $availability['slots'] ?? [];

                    if (empty($slots)) {
                        return [['date' => $date, 'start_time' => null, 'end_time' => null]];
                    }

                    return collect($slots)->map(fn ($slot) => [
                        'date' => $date,
                        'start_time' => $slot['start_time'] ?? null,
                        'end_time' => $slot['end_time'] ?? null,
                    ]);
                })
                ->unique(fn ($w) => "{$w['date']}|{$w['start_time']}|{$w['end_time']}")
                ->values()
                ->toArray();
        }

        if (!empty($data['date_range'])) {
            return collect($this->syncAvailabilities->buildAvailabilitiesFromRange($data['date_range']))
                ->flatMap(function ($availability) {
                    $date = $availability['available_date'];
                    $slots = $availability['slots'] ?? [];

                    if (empty($slots)) {
                        return [['date' => $date, 'start_time' => null, 'end_time' => null]];
                    }

                    return collect($slots)->map(fn ($slot) => [
                        'date' => $date,
                        'start_time' => $slot['start_time'] ?? null,
                        'end_time' => $slot['end_time'] ?? null,
                    ]);
                })
                ->unique(fn ($w) => "{$w['date']}|{$w['start_time']}|{$w['end_time']}")
                ->values()
                ->toArray();
        }

        return [];
    }

    /**
     * جلب النوافذ الزمنية الحالية المسجلة فعلياً للتنسيق بقاعدة البيانات
     * (تاريخ + وقت كل slot موجود).
     */
    private function getCurrentArrangementDates(Listing $listing): array
    {
        $variant = $listing->variants()->first();
        if (!$variant) return [];

        return $variant->availabilities()
            ->with('slots')
            ->get()
            ->flatMap(function ($availability) {
                $date = $availability->available_date->format('Y-m-d');

                if ($availability->slots->isEmpty()) {
                    return [['date' => $date, 'start_time' => null, 'end_time' => null]];
                }

                return $availability->slots->map(fn ($slot) => [
                    'date' => $date,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                ]);
            })
            ->values()
            ->toArray();
    }
}