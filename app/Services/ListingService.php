<?php

namespace App\Services;

use App\Models\Listing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Services\MediaService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;

class ListingService
{
    protected MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function getAllListings(int $perPage = 15)
    {
        return Listing::with([
            'variants.availabilities.slots',
            'variants.images',
            'images',
            'category',
            'district'
        ])
            ->latest()
            ->paginate($perPage);
    }

    public function getListingById(string $id): Listing
    {
        return Listing::with([
            'category',
            'district',
            'images',
            'variants.images',
            'variants.availabilities.slots'
        ])
            ->findOrFail($id);
    }

    public function createListingWithGraph(array $data): Listing
    {
        return DB::transaction(function () use ($data) {

            $listing = Listing::create([
                'provider_id'                => $data['provider_id'],
                'category_id'                => $data['category_id'],
                'district_id'                => $data['district_id'],
                'title'                      => $data['title'],
                'description'                => $data['description'],
                'listing_type'               => $data['listing_type'],
                'material_composition'       => $data['material_composition'] ?? null,
                'secondary_contact_number'   => $data['secondary_contact_number'] ?? null,
                'cancel_before_acceptance'   => $data['cancel_before_acceptance'] ?? false,
                'cancel_after_acceptance'    => $data['cancel_after_acceptance'] ?? false,
                'cancel_before_payment'      => $data['cancel_before_payment'] ?? false,
                'is_provider_location_based' => $data['is_provider_location_based'] ?? true,
                'moderation_status'          => $data['moderation_status'] ?? 'draft',
                'rejection_reason'           => $data['rejection_reason'] ?? null,
            ]);

            // 2. معالجة صور الـ Listing الأساسية
            if (isset($data['images']) && is_array($data['images'])) {
                foreach ($data['images'] as $tempPath) {
                    // الحل: التأكد من أن المسار يبدأ بـ 'temp/'
                    $fullTempPath = Str::startsWith($tempPath, 'temp/') ? $tempPath : 'temp/' . $tempPath;

                    // الآن نستخدم المسار الصحيح
                    $this->mediaService->moveAndAttach(
                        $fullTempPath,
                        $listing,
                        "listings/{$listing->id}/main"
                    );
                }
            }

            foreach ($data['variants'] as $variantData) {

                $variant = $listing->variants()->create(
                    $this->buildVariantPayload($variantData)
                );

                Log::info('Variant Processing:', ['variant_name' => $variantData['variant_name']['en'] ?? 'Unknown']);

                // تم توحيد منطق الإدخال هنا لتجنب التكرار (Double Insertion Bug)
                if (!empty($variantData['date_range'])) {
                    Log::info('SUCCESS: date_range detected');
                    $availabilities = $this->generateAvailabilitiesFromRange($variantData['date_range']);
                    $this->bulkInsertAvailabilitiesAndSlots($variant, $availabilities);
                } elseif (!empty($variantData['availabilities'])) {
                    Log::info('SUCCESS: specific availabilities detected');
                    $this->bulkInsertAvailabilitiesAndSlots($variant, $variantData['availabilities']);
                } else {
                    Log::warning('FAILURE: No dates or availabilities provided for variant', [
                        'keys_available' => array_keys($variantData)
                    ]);
                }
            }

            return $listing->load(['variants.availabilities.slots', 'images', 'variants.images', 'category', 'district']);
        });
    }

    public function updateListingWithGraph(Listing $listing, array $data): Listing
    {
        return DB::transaction(function () use ($listing, $data) {

            // ── 1. تحديث بيانات الـ Listing الأساسية بأمان ──────────────────────
            $listingPayload = collect($data)->only([
                'category_id',
                'district_id',
                'title',
                'description',
                'listing_type',
                'cancel_before_acceptance',
                'cancel_after_acceptance',
                'cancel_before_payment',
                'secondary_contact_number',
                'is_provider_location_based',
                'material_composition',
                'moderation_status'
            ])->toArray();

            if (!empty($listingPayload)) {
                $listing->update($listingPayload);
            }

            // ── 2. إدارة صور الـ Listing الأساسية ─────────────────────────
            if (isset($data['images'])) {
                foreach ($data['images'] as $tempPath) {
                    $this->mediaService->moveAndAttach(
                        $tempPath,
                        $listing,
                        "listings/{$listing->id}/main"
                    );
                }
            }

            if (!isset($data['variants'])) {
                return $listing->load('variants.availabilities.slots');
            }

            // ── 3. حذف الـ Variants غير المرسلة ───────────────────────────
            $sentVariantIds = collect($data['variants'])
                ->pluck('id')
                ->filter()
                ->values()
                ->toArray();

            $listing->variants()
                ->whereNotIn('id', $sentVariantIds)
                ->get()
                ->each->forceDelete();

            // ── 4. معالجة الـ Variants (تحديث أو إنشاء) ────────────────────
            foreach ($data['variants'] as $variantData) {

                if (!empty($variantData['id'])) {
                    $variant = $listing->variants()->findOrFail($variantData['id']);
                    $variant->update($this->buildVariantPayload($variantData, $variant));
                } else {
                    $variant = $listing->variants()->create(
                        $this->buildVariantPayload($variantData)
                    );
                }

                // ── 5. إدارة صور الـ Variant ────────────────────────────────
                if (isset($variantData['images'])) {
                    foreach ($variantData['images'] as $tempPath) {
                        $this->mediaService->moveAndAttach(
                            $tempPath,
                            $variant,
                            "listings/{$listing->id}/variants",
                            "Variant Image - " . ($variantData['variant_name']['en'] ?? 'Default')
                        );
                    }
                }

                if (!isset($variantData['availabilities'])) continue;

                $this->syncAvailabilities($variant, $variantData['availabilities']);
            }

            return $listing->load('variants.availabilities.slots');
        });
    }

    public function deleteListing(Listing $listing): bool
    {
        return $listing->delete();
    }

    private function buildVariantPayload(array $variantData, $existingVariant = null): array
    {
        $payload = [];

        if (!$existingVariant && empty($variantData['id'])) {
            $payload['id'] = (string) Str::ulid();
        }

        if (array_key_exists('variant_name', $variantData))   $payload['variant_name'] = $variantData['variant_name'];
        if (array_key_exists('price', $variantData))          $payload['price'] = $variantData['price'];
        if (array_key_exists('currency', $variantData))       $payload['currency'] = $variantData['currency'];
        if (array_key_exists('price_type', $variantData))     $payload['price_type'] = $variantData['price_type'];
        if (array_key_exists('stock_quantity', $variantData)) $payload['stock_quantity'] = $variantData['stock_quantity'];

        if (array_key_exists('services', $variantData)) {
            $payload['dynamic_attributes'] = $this->mapServicesToColumns($variantData['services']);
        }

        return $payload;
    }

    private function mapServicesToColumns(array $services): ?array
    {
        if (empty($services)) return null;

        if (isset($services[0]) && is_array($services[0]) && array_key_exists('key', $services[0])) {
            $mapped = [];
            foreach ($services as $service) {
                $mapped[$service['key']] = [
                    'value' => $service['value'],
                    'label' => $service['label'] ?? null,
                ];
            }
            return $mapped;
        }

        return $services;
    }
private function bulkInsertAvailabilitiesAndSlots($variant, array $availabilitiesData): void
{
    DB::table('listing_availabilities')
        ->where('listing_variant_id', $variant->id)
        ->delete();

    $availabilitiesToInsert = [];
    $slotsToInsert          = [];

    foreach ($availabilitiesData as $availabilityData) {
        $availabilityId = (string) Str::ulid();
        $formattedDate = Carbon::parse($availabilityData['available_date'])->format('Y-m-d');

        $availabilitiesToInsert[] = [
            'id'                 => $availabilityId,
            'listing_variant_id' => $variant->id,
            'available_date'     => $formattedDate,
            'is_blocked'         => $availabilityData['is_blocked'] ?? false,
            'created_at'         => now(),
            'updated_at'         => now(),
        ];

        foreach ($availabilityData['slots'] ?? [] as $slotData) {
            // ✅ حل مشكلة الاسم: دعم slot_name أو name والتعامل مع المصفوفات المترجمة
            $slotNameValue = $slotData['slot_name'] ?? $slotData['name'] ?? null;
            $encodedSlotName = is_array($slotNameValue) 
                ? json_encode($slotNameValue, JSON_UNESCAPED_UNICODE) 
                : $slotNameValue;

            // ✅ حل مشكلة الوقت: دمج تاريخ اليوم الفعلي المستهدف مع ساعات الشيفت
            $startTime = Carbon::parse($formattedDate . ' ' . $slotData['start_time'])->toDateTimeString();
            $endTime   = Carbon::parse($formattedDate . ' ' . $slotData['end_time'])->toDateTimeString();

            $slotsToInsert[] = [
                'id'                      => (string) Str::ulid(),
                'listing_availability_id' => $availabilityId,
                'slot_name'               => $encodedSlotName,
                'start_time'              => $startTime,
                'end_time'                => $endTime,
                'remaining_capacity'      => $slotData['remaining_capacity'] ?? 1,
                'created_at'              => now(),
                'updated_at'              => now(),
            ];
        }
    }

    if (!empty($availabilitiesToInsert)) {
        DB::table('listing_availabilities')->insert($availabilitiesToInsert);
    }

    if (!empty($slotsToInsert)) {
        DB::table('listing_slots')->insert($slotsToInsert);
    }
}
private function syncAvailabilities($variant, array $availabilitiesData): void
{
    // 1. استخراج الـ IDs الموجودة في الطلب (التي سيتم الاحتفاظ بها)
    $sentIds = collect($availabilitiesData)->pluck('id')->filter()->values()->toArray();

    // 2. تنظيف القاعدة: حذف أي سجل متعلق بهذا الـ Variant وغير موجود في الطلب الحالي
    // هذا يضمن إخلاء التواريخ قبل البدء في عمليات التحديث أو الإنشاء
    $variant->availabilities()->whereNotIn('id', $sentIds)->delete();

    // 3. المعالجة: المرور على البيانات المرسلة
    foreach ($availabilitiesData as $availabilityData) {
        
        $date = $availabilityData['available_date'];

        // 4. فحص الأمان: تأكد أن التاريخ ليس محجوزاً بسجل آخر في القاعدة
        // (باستثناء السجل الحالي الذي نقوم بتحديثه)
        $query = $variant->availabilities()->where('available_date', $date);
        
        if (!empty($availabilityData['id'])) {
            $query->where('id', '!=', $availabilityData['id']);
        }

        if ($query->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'availabilities' => "التاريخ {$date} محجوز مسبقاً لهذا الـ Variant."
            ]);
        }

        // 5. التحديث أو الإنشاء
        if (!empty($availabilityData['id'])) {
            $availability = $variant->availabilities()->findOrFail($availabilityData['id']);
            $availability->update([
                'available_date' => $date,
                'is_blocked'     => $availabilityData['is_blocked'] ?? false,
            ]);
        } else {
            $availability = $variant->availabilities()->create([
                'id'             => (string) \Illuminate\Support\Str::ulid(),
                'available_date' => $date,
                'is_blocked'     => $availabilityData['is_blocked'] ?? false,
            ]);
        }

        // 6. مزامنة الـ Slots الخاصة بهذا التاريخ
        if (isset($availabilityData['slots'])) {
            $this->syncSlots($availability, $availabilityData['slots']);
        }
    }
}
private function syncSlots($availability, array $slotsData)
{
    $sentSlotIds = collect($slotsData)->pluck('id')->filter()->toArray();

    $availability->slots()
        ->whereNotIn('id', $sentSlotIds)
        ->forceDelete();

    $formattedDate = Carbon::parse($availability->available_date)->format('Y-m-d');

    foreach ($slotsData as $slotData) {

        // ✅ بدون ترميز يدوي — مرر القيمة كما هي (array أو string)
        $slotNameValue = $slotData['slot_name'] ?? $slotData['name'] ?? null;

        $startTime = Carbon::parse($formattedDate . ' ' . $slotData['start_time'])->toDateTimeString();
        $endTime   = Carbon::parse($formattedDate . ' ' . $slotData['end_time'])->toDateTimeString();

        $availability->slots()->updateOrCreate(
            ['id' => $slotData['id'] ?? null],
            [
                'slot_name'          => $slotNameValue,   // الـ cast يتولى الترميز
                'start_time'         => $startTime,
                'end_time'           => $endTime,
                'remaining_capacity' => $slotData['remaining_capacity'] ?? 1,
            ]
        );
    }
}
    private function assertSlotsNotBooked(array $slotIds): void
    {
        // تم إزالة return; العشوائية التي كانت تعطل الدالة تماماً
        if (empty($slotIds)) return;

        // ⚠️ الكود معلق حالياً كما اتفقنا حتى تقوم بإنشاء جدول الـ orders الفعلي في النظام
        // // تأكد من أن اسم الجدول هنا ('orders') يطابق جدول الحجوزات الفعلي في نظامك
        // $hasActiveOrders = DB::table('orders') 
        //     ->whereIn('listing_slot_id', $slotIds)
        //     ->whereIn('status', ['pending', 'accepted', 'confirmed'])
        //     ->exists();

        // if ($hasActiveOrders) {
        //     throw new Exception('لا يمكن تعديل أو حذف الساعات المختارة لوجود حجوزات مؤكدة أو معلقة.');
        // }
    }

    private function assertNoOverlap($existingSlots, array $newSlot): void
    {
        if (!isset($newSlot['start_time']) || !isset($newSlot['end_time'])) return;

        $newStart = date('H:i', strtotime($newSlot['start_time']));
        $newEnd   = date('H:i', strtotime($newSlot['end_time']));

        foreach ($existingSlots as $existSlot) {
            $existStartRaw = is_object($existSlot) ? $existSlot->start_time : $existSlot['start_time'];
            $existEndRaw   = is_object($existSlot) ? $existSlot->end_time : $existSlot['end_time'];

            $existStart = date('H:i', strtotime($existStartRaw));
            $existEnd   = date('H:i', strtotime($existEndRaw));

            if ($newStart < $existEnd && $newEnd > $existStart) {
                throw new Exception("خطأ في الجدولة: الوقت ({$newStart} - {$newEnd}) يتداخل مع شيفت موجود ({$existStart} - {$existEnd}).");
            }
        }
    }

    // تم دمج دالة generateRangeAvailabilities هنا لتجنب التكرار
    private function generateAvailabilitiesFromRange(array $dateRange): array
    {
        $startDate = new \DateTime($dateRange['start_date']);
        $endDate = new \DateTime($dateRange['end_date']);
        $interval = new \DateInterval('P1D');
        $dateRangePeriod = new \DatePeriod($startDate, $interval, $endDate->modify('+1 day'));

        $generatedAvailabilities = [];

        foreach ($dateRangePeriod as $date) {
            $generatedAvailabilities[] = [
                'available_date' => $date->format('Y-m-d'),
                'is_blocked'     => $dateRange['is_blocked'] ?? false,
                'slots'          => $dateRange['slots'] ?? []
            ];
        }

        return $generatedAvailabilities;
    }
}
