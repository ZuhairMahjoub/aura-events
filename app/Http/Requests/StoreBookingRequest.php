<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // التحقق من الصلاحية يتم في الـ Policy
    }

    public function rules(): array
    {
        return [
            'listing_id'         => ['required', 'ulid', 'exists:listings,id'],
            'listing_variant_id' => ['required', 'ulid', 'exists:listing_variants,id'],
            'listing_slot_id'    => ['nullable', 'ulid', 'exists:listing_slots,id'],
            'booked_date'        => ['nullable', 'date', 'after_or_equal:today'],
            'quantity'           => ['required', 'integer', 'min:1'],
            'metadata'           => ['nullable', 'array'],
            'customer_notes'     => ['nullable', 'string', 'max:1000'],
            'booked_start_time'          => ['nullable', 'date_format:H:i'],
            'booked_end_time'            => ['nullable', 'date_format:H:i'],
        ];
    }
}