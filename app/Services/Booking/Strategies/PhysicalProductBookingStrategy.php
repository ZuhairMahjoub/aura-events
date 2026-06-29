<?php

namespace App\Services\Booking\Strategies;

use App\Contracts\BookingStrategyInterface;
use App\DTOs\BookingData;
use App\Models\ListingVariant;
use App\Models\ListingSlot;
use App\Models\Booking;
use Illuminate\Validation\ValidationException;

/**
 * استراتيجية حجز المنتجات المادية (Physical Products)
 * 
 * تدعم نوعين من الأسعار:
 * 1. Fixed: منتج مادي بسعر ثابت - يتطلب كمية فقط
 * 2. Hourly: منتج مادي بسعر بالساعة - يتطلب slot زمني محدد
 * 
 * في كلا الحالتين:
 * - يتم التحقق من المخزون (stock_quantity)
 * - يتم تخفيض المخزون عند الحجز
 */
class PhysicalProductBookingStrategy implements BookingStrategyInterface
{
    public function validate(BookingData $data): void
    {
        // جلب المتغير للتحقق من نوع السعر
        $variant = ListingVariant::findOrFail($data->variantId);

        // التحقق من الكمية
        if ($data->quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'الكمية يجب أن تكون 1 على الأقل.',
            ]);
        }

        // إذا كان السعر بالساعة (hourly)، يجب تحديد slot وتاريخ
        if ($variant->price_type === 'hourly') {
            if (empty($data->slotId)) {
                throw ValidationException::withMessages([
                    'listing_slot_id' => 'المنتج بالساعة يتطلب تحديد time slot.',
                ]);
            }

            if (empty($data->bookedDate)) {
                throw ValidationException::withMessages([
                    'booked_date' => 'المنتج بالساعة يتطلب تحديد تاريخ.',
                ]);
            }

            // التحقق من عدم وجود حجز متعارض
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
    }

    public function reserveCapacity(BookingData $data): void
    {
        // جلب المتغير للتحقق من نوع السعر والمخزون
        $variant = ListingVariant::lockForUpdate()->findOrFail($data->variantId);

        // التحقق من المخزون (null يعني مخزون غير محدود)
        if ($variant->stock_quantity !== null) {
            if ($variant->stock_quantity < $data->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "الكمية المطلوبة ({$data->quantity}) تتجاوز المخزون المتاح ({$variant->stock_quantity}).",
                ]);
            }
            // تخفيض المخزون
            $variant->decrement('stock_quantity', $data->quantity);
        }

        // إذا كان السعر بالساعة، احجز الـ slot أيضاً
        if ($variant->price_type === 'hourly' && !empty($data->slotId)) {
            $slot = ListingSlot::lockForUpdate()->findOrFail($data->slotId);

            if ($slot->remaining_capacity < $data->quantity) {
                throw ValidationException::withMessages([
                    'listing_slot_id' => "الطاقة الاستيعابية للـ Slot غير كافية. المتاح: {$slot->remaining_capacity}.",
                ]);
            }

            $slot->decrement('remaining_capacity', $data->quantity);
        }
    }

    public function buildTimeSnapshot(BookingData $data): array
    {
        // جلب المتغير للتحقق من نوع السعر
        $variant = ListingVariant::find($data->variantId);

        // إذا كان السعر بالساعة، احفظ بيانات الوقت
        if ($variant?->price_type === 'hourly' && !empty($data->slotId)) {
            $slot = ListingSlot::find($data->slotId);
            return [
                'booked_date'       => $data->bookedDate,
                'booked_start_time' => $slot?->start_time?->format('H:i:s'),
                'booked_end_time'   => $slot?->end_time?->format('H:i:s'),
            ];
        }

        // إذا كان السعر ثابت، لا تحفظ أوقات محددة
        return [
            'booked_date'       => $data->bookedDate, // تاريخ التسليم المطلوب إن وجد
            'booked_start_time' => null,
            'booked_end_time'   => null,
        ];
    }

    public function buildTypeMetadata(BookingData $data): array
    {
        // جلب المتغير للتحقق من نوع السعر
        $variant = ListingVariant::find($data->variantId);

        return [
            'is_rental'        => $data->metadata['is_rental'] ?? false,
            'rental_days'      => $data->metadata['rental_days'] ?? null,
            'delivery_address' => $data->metadata['delivery_address'] ?? null,
            'price_type'       => $variant?->price_type ?? 'fixed', // حفظ نوع السعر للمرجعية
        ];
    }
}
