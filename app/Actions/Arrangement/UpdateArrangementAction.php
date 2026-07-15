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
        if (isset($data['freelancers'])) {
            $this->validateFreelancers->execute($data['freelancers'], $providerId);
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
}