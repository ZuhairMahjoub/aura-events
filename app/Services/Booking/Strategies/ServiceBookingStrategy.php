<?php

namespace App\Services\Booking\Strategies;

use App\Contracts\BookingStrategyInterface;
use App\DTOs\BookingData;
use App\Models\ListingSlot;
use App\Models\Booking;
use Illuminate\Validation\ValidationException;

class ServiceBookingStrategy implements BookingStrategyInterface
{
    public function validate(BookingData $data): void
    {
        if (empty($data->slotId)) {
            throw ValidationException::withMessages([
                'listing_slot_id' => 'حجز الصالة يتطلب تحديد time slot.',
            ]);
        }

        if (empty($data->bookedDate)) {
            throw ValidationException::withMessages([
                'booked_date' => 'حجز الصالة يتطلب تحديد تاريخ.',
            ]);
        }

        // التحقق من عدم وجود حجز متعارض لنفس الصالة في نفس اليوم والوقت
        // هذا تحقق أولي قبل الـ LOCK — الـ LOCK في reserveCapacity يضمن الاتساق
        $overlapping = Booking::where('listing_variant_id', $data->variantId)
            ->where('booked_date', $data->bookedDate)
            ->whereIn('status', ['pending', 'accepted', 'confirmed'])
            ->where(function ($q) use ($data) {
                // هل يتداخل الوقت المطلوب مع حجز موجود؟
                $q->whereNotNull('listing_slot_id')
                  ->where('listing_slot_id', $data->slotId);
            })
            ->exists();

        if ($overlapping) {
            throw ValidationException::withMessages([
                'listing_slot_id' => 'هذا الـ Slot محجوز مسبقاً.',
            ]);
        }
    }

    public function reserveCapacity(BookingData $data): void
    {
        // LOCK الـ slot لمنع الحجز المتزامن
        $slot = ListingSlot::lockForUpdate()->findOrFail($data->slotId);

        if ($slot->remaining_capacity < $data->quantity) {
            throw ValidationException::withMessages([
                'listing_slot_id' => "الطاقة الاستيعابية للـ Slot غير كافية. المتاح: {$slot->remaining_capacity}.",
            ]);
        }

        $slot->decrement('remaining_capacity', $data->quantity);
    }

    public function buildTimeSnapshot(BookingData $data): array
    {
        // نحتاج لجلب الـ slot لنأخذ البيانات الزمنية منه
        // هذا السجل يُحفظ في الـ booking كـ snapshot
        $slot = ListingSlot::find($data->slotId);

        return [
            'booked_date'       => $data->bookedDate,
            'booked_start_time' => $slot?->start_time?->format('H:i:s'),
            'booked_end_time'   => $slot?->end_time?->format('H:i:s'),
        ];
    }

    public function buildTypeMetadata(BookingData $data): array
    {
        return [
            'event_type'  => $data->metadata['event_type'] ?? null,
            'guest_count' => $data->metadata['guest_count'] ?? null,
            'setup_needs' => $data->metadata['setup_needs'] ?? null,
        ];
    }
}