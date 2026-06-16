<?php

namespace App\Services\Booking\Strategies;

use App\Contracts\BookingStrategyInterface;
use App\DTOs\BookingData;
use App\Models\ListingVariant;
use Illuminate\Validation\ValidationException;

class PhysicalProductBookingStrategy implements BookingStrategyInterface
{
    public function validate(BookingData $data): void
    {
        // المنتج المادي لا يحتاج slot، لكن يجب أن يكون له كمية صالحة
        if ($data->quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'الكمية يجب أن تكون 1 على الأقل.',
            ]);
        }
    }

    public function reserveCapacity(BookingData $data): void
    {
        // SELECT FOR UPDATE يمنع أي transaction أخرى من قراءة/تعديل هذا السجل
        // حتى تنتهي الـ transaction الحالية — هذا هو Pessimistic Locking
        $variant = ListingVariant::lockForUpdate()->findOrFail($data->variantId);

        // null يعني "مخزون غير محدود" (مثل: خدمة رقمية)
        if ($variant->stock_quantity !== null) {
            if ($variant->stock_quantity < $data->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "الكمية المطلوبة ({$data->quantity}) تتجاوز المخزون المتاح ({$variant->stock_quantity}).",
                ]);
            }
            // تخفيض المخزون atomically داخل نفس الـ transaction
            $variant->decrement('stock_quantity', $data->quantity);
        }
    }

    public function buildTimeSnapshot(BookingData $data): array
    {
        // المنتجات المادية لا تعتمد على وقت محدد
        return [
            'booked_date'       => $data->bookedDate, // تاريخ التسليم المطلوب إن وجد
            'booked_start_time' => null,
            'booked_end_time'   => null,
        ];
    }

    public function buildTypeMetadata(BookingData $data): array
    {
        return [
            'is_rental'        => $data->metadata['is_rental'] ?? false,
            'rental_days'      => $data->metadata['rental_days'] ?? null,
            'delivery_address' => $data->metadata['delivery_address'] ?? null,
        ];
    }
}