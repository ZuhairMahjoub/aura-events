<?php

namespace App\Services\Booking\Strategies;

use App\Contracts\BookingStrategyInterface;
use App\DTOs\BookingData;
use App\Models\ListingVariant;
use App\Models\ListingSlot;
use App\Models\Booking;
use Illuminate\Validation\ValidationException;
use App\Models\ListingAvailability;


class PhysicalProductBookingStrategy implements BookingStrategyInterface
{
    private ?ListingSlot $lockedSlot = null;

    public function validate(BookingData $data): void
    {
        $variant = ListingVariant::findOrFail($data->variantId);

        if ($data->quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'الكمية يجب أن تكون 1 على الأقل.',
            ]);
        }

        if (!empty($data->slotId)) {
            if (empty($data->bookedDate)) {
                throw ValidationException::withMessages([
                    'booked_date' => 'حجز المنتج يتطلب تحديد تاريخ.',
                ]);
            }
        } elseif ($variant->price_type === 'hourly') {
            // حماية إضافية للبيانات
            throw ValidationException::withMessages([
                'listing_slot_id' => 'المنتج بالساعة يتطلب تحديد time slot.',
            ]);
        }
    }

  public function reserveCapacity(BookingData $data): void
    {
        $variant = ListingVariant::lockForUpdate()->findOrFail($data->variantId);

        if (!empty($data->slotId)) {
            $requestedSlot = ListingSlot::lockForUpdate()->findOrFail($data->slotId);

            // التحقق من سعة الفترة المطلوبة فقط
            if ($requestedSlot->remaining_capacity < $data->quantity) {
                throw ValidationException::withMessages([
                    'listing_slot_id' => "السعة المتبقية لهذه الفترة غير كافية. المتاح: {$requestedSlot->remaining_capacity}.",
                ]);
            }

            $requestedSlot->decrement('remaining_capacity', $data->quantity);
            $this->lockedSlot = $requestedSlot->fresh();

            return;
        }

        if ($variant->stock_quantity !== null) {
            if ($variant->stock_quantity < $data->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "الكمية المطلوبة تتجاوز المخزون المتاح.",
                ]);
            }
            $variant->decrement('stock_quantity', $data->quantity);
        }
    }

    public function buildTimeSnapshot(BookingData $data): array
    {
        $variant = ListingVariant::find($data->variantId);

        if (!$variant) {
            throw ValidationException::withMessages([
                'variant_id' => ['العرض أو المنتج المطلوب غير موجود.']
            ]);
        }

        $requestedDate = $data->bookedDate instanceof \Carbon\Carbon
            ? $data->bookedDate->format('Y-m-d')
            : \Carbon\Carbon::parse($data->bookedDate)->format('Y-m-d');

        if (!empty($data->slotId)) {

            $slot = $this->lockedSlot ?? ListingSlot::with('availability')->find($data->slotId);

            if (!$slot || !$slot->availability) {
                throw ValidationException::withMessages([
                    'slot_id' => ['الفترة الزمنية المطلوبة غير موجودة أو غير متاحة حالياً.']
                ]);
            }

            if ($slot->availability->listing_variant_id !== $variant->id) {
                throw ValidationException::withMessages([
                    'slot_id' => ['الفترة الزمنية المحددة لا تنتمي لهذا العرض.']
                ]);
            }

            if ($slot->availability->is_blocked) {
                throw ValidationException::withMessages([
                    'booked_date' => ['عذراً، هذا اليوم تم إغلاقه من قبل مقدم الخدمة.']
                ]);
            }

            $availableDate = $slot->availability->available_date instanceof \Carbon\Carbon
                ? $slot->availability->available_date->format('Y-m-d')
                : \Carbon\Carbon::parse($slot->availability->available_date)->format('Y-m-d');

            if ($requestedDate !== $availableDate) {
                throw ValidationException::withMessages([
                    'booked_date' => ['التاريخ المحدد لا يطابق يوم الفترة الزمنية المتاحة في قاعدة البيانات.']
                ]);
            }

            return [
                'booked_date'       => $availableDate,
                'booked_start_time' => $slot->start_time,
                'booked_end_time'   => $slot->end_time,
            ];
        }

        $availability = ListingAvailability::where('listing_variant_id', $variant->id)
            ->where('available_date', $requestedDate)
            ->first();

        if ($availability && $availability->is_blocked) {
            throw ValidationException::withMessages([
                'booked_date' => ['عذراً، هذا اليوم تم إغلاقه من قبل مقدم الخدمة ولا يستقبل حجوزات ثابتة.']
            ]);
        }

        return [
            'booked_date'       => $requestedDate,
            'booked_start_time' => null,
            'booked_end_time'   => null,
        ];
    }

    public function buildTypeMetadata(BookingData $data): array
    {
        $variant = ListingVariant::find($data->variantId);

        return [
            'is_rental'        => $data->metadata['is_rental'] ?? false,
            'rental_days'      => $data->metadata['rental_days'] ?? null,
            'delivery_address' => $data->metadata['delivery_address'] ?? null,
            'price_type'       => $variant?->price_type ?? 'fixed',
        ];
    }

    public function release(Booking $booking): void
    {
        if ($booking->listing_slot_id) {
            $bookedSlot = ListingSlot::lockForUpdate()->find($booking->listing_slot_id);

            if ($bookedSlot) {
                $bookedSlot->increment('remaining_capacity', $booking->quantity);
            }

            return;
        }

        $variant = ListingVariant::lockForUpdate()->find($booking->listing_variant_id);

        if ($variant && $variant->stock_quantity !== null) {
            $variant->increment('stock_quantity', $booking->quantity);
        }
    }
}