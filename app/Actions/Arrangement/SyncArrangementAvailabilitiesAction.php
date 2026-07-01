<?php

namespace App\Actions\Arrangement;

use App\Models\ListingVariant;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncArrangementAvailabilitiesAction
{
    /**
     * استبدال كامل لتواريخ/أوقات الباقة بناءً على نطاق زمني (Date Range).
     */
    public function execute(ListingVariant $variant, ?array $dateRange, int $defaultCapacity = 1): void
    {
        // 1. حذف المواعيد والفترات السابقة
        $oldAvailabilityIds = $variant->availabilities()->pluck('id');
        DB::table('listing_slots')->whereIn('listing_availability_id', $oldAvailabilityIds)->delete();
        DB::table('listing_availabilities')->where('listing_variant_id', $variant->id)->delete();

        // 2. التحقق من وجود بيانات النطاق الزمني
        if (empty($dateRange) || empty($dateRange['start_date']) || empty($dateRange['end_date'])) {
            return;
        }

        $availabilityRows = [];
        $slotRows = [];

        // 3. تحديد نطاق الأيام باستخدام CarbonPeriod
        $startDate = Carbon::parse($dateRange['start_date']);
        $endDate   = Carbon::parse($dateRange['end_date']);
        $isBlocked = $dateRange['is_blocked'] ?? false;
        $slots     = $dateRange['slots'] ?? [];
        $now       = now();

        $period = CarbonPeriod::create($startDate, $endDate);

        // 4. الدوران على كل يوم داخل النطاق الزمني
        foreach ($period as $date) {
            $availabilityId = (string) Str::ulid();

            $availabilityRows[] = [
                'id'                 => $availabilityId,
                'listing_variant_id' => $variant->id,
                'available_date'     => $date->format('Y-m-d'),
                'is_blocked'         => $isBlocked,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];

            // 5. ربط الفترات (Slots) بهذا اليوم المحدد
            foreach ($slots as $slot) {
                // إذا كان جدولك لا يحتوي على حقل slot_name يمكنك إزالة السطر التالي
                $slotName = isset($slot['slot_name']) ? json_encode($slot['slot_name'], JSON_UNESCAPED_UNICODE) : null;

                $slotRows[] = [
                    'id'                      => (string) Str::ulid(),
                    'listing_availability_id' => $availabilityId,
                    'slot_name'               => $slotName, // أضفنا حقل الاسم بناءً على الـ JSON الجديد
                    'start_time'              => $slot['start_time'],
                    'end_time'                => $slot['end_time'],
                    'remaining_capacity'      => $slot['remaining_capacity'] ?? $defaultCapacity,
                    'created_at'              => $now,
                    'updated_at'              => $now,
                ];
            }
        }

        // 6. الحفظ في قاعدة البيانات على شكل دفعات (Chunks) لتجنب أخطاء الـ Memory
        if (!empty($availabilityRows)) {
            foreach (array_chunk($availabilityRows, 500) as $chunk) {
                DB::table('listing_availabilities')->insert($chunk);
            }
        }

        if (!empty($slotRows)) {
            foreach (array_chunk($slotRows, 500) as $chunk) {
                DB::table('listing_slots')->insert($chunk);
            }
        }
    }
}