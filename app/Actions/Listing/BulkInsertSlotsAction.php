<?php

namespace App\Actions\Listing;

use App\Models\ListingAvailability;
use Illuminate\Validation\ValidationException;

class BulkInsertSlotsAction
{
    public function execute(ListingAvailability $availability, array $slotsData): void
    {
        $sentSlotIds = collect($slotsData)
            ->pluck('id')
            ->filter()
            ->values()
            ->toArray();

        // فحص الحجوزات فقط على الـ slots اللي ستُحذف
        foreach ($availability->slots()->whereNotIn('id', $sentSlotIds)->get() as $existingSlot) {
            $activeBookings = $existingSlot->bookings()
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->count();

            if ($activeBookings > 0) {
                throw ValidationException::withMessages([
                    'slots' => "لا يمكن حذف فترة زمنية عليها {$activeBookings} حجز نشط (من {$existingSlot->start_time} إلى {$existingSlot->end_time})."
                ]);
            }
        }

        $availability->slots()->whereNotIn('id', $sentSlotIds)->delete();

        if (empty($slotsData)) return;

        foreach ($slotsData as $slotData) {
            $startTime = $slotData['start_time'];
            if (substr_count($startTime, ':') === 1) $startTime .= ':00';

            $endTime = $slotData['end_time'];
            if (substr_count($endTime, ':') === 1) $endTime .= ':00';

            if (!empty($slotData['id'])) {
                // تحديث الـ slot الموجود
                $availability->slots()->where('id', $slotData['id'])->update([
                    'slot_name'          => $slotData['slot_name'] ?? $slotData['name'] ?? null,
                    'start_time'         => $startTime,
                    'end_time'           => $endTime,
                    'remaining_capacity' => $slotData['remaining_capacity'] ?? 1,
                ]);
            } else {
                // إنشاء slot جديد
                $availability->slots()->create([
                    'slot_name'          => $slotData['slot_name'] ?? $slotData['name'] ?? null,
                    'start_time'         => $startTime,
                    'end_time'           => $endTime,
                    'remaining_capacity' => $slotData['remaining_capacity'] ?? 1,
                ]);
            }
        }
    }
}