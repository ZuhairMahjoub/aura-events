<?php

namespace App\Services\Booking\Strategies;

use App\Contracts\BookingStrategyInterface;
use App\DTOs\BookingData;
use App\Models\ListingSlot;
use App\Models\Booking;
use Illuminate\Validation\ValidationException;

/**
 * إصلاح 3.2:
 * - فحص التعارض نُقل إلى داخل reserveCapacity تحت lockForUpdate (TOCTOU-safe).
 * - الـ slot المُقفَل يُحفظ في property ويُعاد استخدامه في buildTimeSnapshot
 *   لتجنب استعلام ثانٍ على نفس السجل (إصلاح 1.11).
 */
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

        // ملاحظة: فحص التعارض الفعلي نُقل إلى reserveCapacity ليكون تحت Lock.
        // validate هنا للتحقق البنيوي فقط.
    }

    public function reserveCapacity(BookingData $data): void
    {
        // ── Lock هو نقطة التزامن الوحيدة والحقيقية (TOCTOU-safe) ──────────
        $slot = ListingSlot::lockForUpdate()->findOrFail($data->slotId);

        // ── فحص التعارض داخل الـ Lock ────────────────────────────────────
        $hasConflict = Booking::where('listing_slot_id', $data->slotId)
            ->where('booked_date', $data->bookedDate)
            ->whereIn('status', ['pending', 'accepted', 'confirmed'])
            ->exists();

        if ($hasConflict) {
            throw ValidationException::withMessages([
                'listing_slot_id' => 'هذا الـ Slot محجوز بالفعل، يرجى اختيار وقت آخر.',
            ]);
        }

        // ── فحص الطاقة الاستيعابية ────────────────────────────────────────
        if ($slot->remaining_capacity < $data->quantity) {
            throw ValidationException::withMessages([
                'listing_slot_id' => "الطاقة الاستيعابية غير كافية. المتاح: {$slot->remaining_capacity}.",
            ]);
        }

        $slot->decrement('remaining_capacity', $data->quantity);

        // حفظ الـ slot المُقفَل لإعادة استخدامه في buildTimeSnapshot
        $this->lockedSlot = $slot->fresh();
    }

    public function buildTimeSnapshot(BookingData $data): array
    {
        $slot = $this->lockedSlot ?? ListingSlot::find($data->slotId);
$bookedDate = $slot?->availability?->available_date ?? $data->bookedDate;
        // إصلاح حرج: start_time/end_time مُعرَّفان في ListingSlot::casts()
        // كـ 'string' (مخزَّنان كـ "HH:MM:SS") وليس Carbon — استدعاء ->format()
        // عليهما يرمي "Call to a member function format() on string" ويُفشل
        // كل عملية حجز لصالة بشكل دائم.
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
}