<?php

namespace App\Services\Booking\Strategies;

use App\Contracts\BookingStrategyInterface;
use App\DTOs\BookingData;
use App\Models\ListingSlot;
use App\Models\Booking;
use Illuminate\Validation\ValidationException;

/**
 * استراتيجية حجز الخدمات والصالات (Halls & Freelance Services)
 * 
 * تتعامل مع:
 * - الصالات (Halls): تحتاج slot زمني محدد
 * - خدمات الفريلانس (Services): تحتاج slot زمني محدد
 * 
 * كلاهما يعتمد على:
 * - listing_slot_id: الوقت المحدد
 * - booked_date: التاريخ
 * - remaining_capacity: الطاقة الاستيعابية للـ slot
 */
class ServiceBookingStrategy implements BookingStrategyInterface
{
    public function validate(BookingData $data): void
    {
        // التحقق من وجود slot محدد
        if (empty($data->slotId)) {
            throw ValidationException::withMessages([
                'listing_slot_id' => 'حجز الخدمة يتطلب تحديد time slot.',
            ]);
        }

        // التحقق من وجود تاريخ محدد
        if (empty($data->bookedDate)) {
            throw ValidationException::withMessages([
                'booked_date' => 'حجز الخدمة يتطلب تحديد تاريخ.',
            ]);
        }

        // التحقق من عدم وجود حجز متعارض لنفس الخدمة في نفس اليوم والوقت
        $overlapping = Booking::where('listing_variant_id', $data->variantId)
            ->where('booked_date', $data->bookedDate)
            ->whereIn('status', ['pending', 'accepted', 'confirmed'])
            ->where(function ($q) use ($data) {
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

        // التحقق من الطاقة الاستيعابية
        if ($slot->remaining_capacity < $data->quantity) {
            throw ValidationException::withMessages([
                'listing_slot_id' => "الطاقة الاستيعابية للـ Slot غير كافية. المتاح: {$slot->remaining_capacity}.",
            ]);
        }

        // تخفيض الطاقة الاستيعابية
        $slot->decrement('remaining_capacity', $data->quantity);
    }

    public function buildTimeSnapshot(BookingData $data): array
    {
        // جلب بيانات الـ slot للحصول على أوقات البداية والنهاية
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
