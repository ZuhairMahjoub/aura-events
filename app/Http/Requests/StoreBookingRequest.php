<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\ListingSlot;
use App\Models\Booking;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'listing_id'         => ['required', 'ulid', 'exists:listings,id'],
            'listing_variant_id' => ['required', 'ulid', 'exists:listing_variants,id'],
            'listing_slot_id'    => [
                'nullable', 
                'ulid', 
                'exists:listing_slots,id',
                function ($attribute, $value, $fail) {
                    if (!$value) {
                        return;
                    }

                    $slot = ListingSlot::find($value);
                    if (!$slot) {
                        return;
                    }

                    // حساب مجموع الكميات المحجوزة الحالية (مع استثناء المرفوضة والملغاة)
                    $bookedQuantitySum = Booking::where('listing_slot_id', $value)
                        ->whereNotIn('status', ['rejected', 'cancelled'])
                        ->sum('quantity');

                    $slotCapacity = $slot->capacity ?? 0; 
                    $remainingCapacity = $slotCapacity - $bookedQuantitySum;

                    $requestedQuantity = $this->input('quantity', 1);
                    if ($requestedQuantity > $remainingCapacity) {
                        $fail('هذا الـ Slot لا يحتوي على سعة كافية للكمية المطلوبة. السعة المتبقية فقط: ' . $remainingCapacity);
                    }
                },
            ],
            'booked_date'        => ['nullable', 'date', 'after_or_equal:today'],
            'quantity'           => ['required', 'integer', 'min:1'],
            'metadata'           => ['nullable', 'array'],
            'customer_notes'     => ['nullable', 'string', 'max:1000'],
            'booked_start_time'  => ['nullable', 'date_format:H:i'],
            'booked_end_time'    => ['nullable', 'date_format:H:i'],
        ];
    }
}