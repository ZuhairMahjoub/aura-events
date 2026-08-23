<?php

namespace App\Services\Booking\Strategies;

use App\Contracts\BookingStrategyInterface;
use App\DTOs\BookingData;
use App\Models\ListingSlot;
use App\Models\Booking;
use Illuminate\Validation\ValidationException;


class HallBookingStrategy implements BookingStrategyInterface
{
    private ?ListingSlot $lockedSlot = null;

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

    }

    public function reserveCapacity(BookingData $data): void
    {
    
        $slot = ListingSlot::with('availability')->lockForUpdate()->findOrFail($data->slotId);

        if (! $slot->availability || $slot->availability->listing_variant_id !== $data->variantId) {
            throw ValidationException::withMessages([
                'listing_slot_id' => 'الفترة الزمنية المحددة لا تنتمي لهذا العرض.',
            ]);
        }

        $hasConflict = Booking::where('listing_slot_id', $data->slotId)
            ->where('booked_date', $data->bookedDate)
            ->whereIn('status', ['pending', 'accepted', 'confirmed'])
            ->exists();

        if ($hasConflict) {
            throw ValidationException::withMessages([
                'listing_slot_id' => 'هذا الـ Slot محجوز بالفعل، يرجى اختيار وقت آخر.',
            ]);
        }
         if ($slot->remaining_capacity ==0 || $slot->remaining_capacity < $data->quantity) {
            throw ValidationException::withMessages([
                'listing_slot_id' => "الطاقة الاستيعابية غير كافية. المتاح: {$slot->remaining_capacity}.",
            ]);
        }

        $slot->decrement('remaining_capacity', $data->quantity);
         $this->lockedSlot = $slot->fresh();
    }

    public function buildTimeSnapshot(BookingData $data): array
    {
        $slot = $this->lockedSlot ?? ListingSlot::find($data->slotId);
$bookedDate = $slot?->availability?->available_date ?? $data->bookedDate;    
        return [
            'booked_date'       => $bookedDate,
            'booked_start_time' => $slot?->start_time,
            'booked_end_time'   => $slot?->end_time,
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

   
    public function release(Booking $booking): void
    {
        if ($booking->listing_slot_id) {
            ListingSlot::lockForUpdate()
                ->find($booking->listing_slot_id)
                ?->increment('remaining_capacity', $booking->quantity);
        }
    }
}