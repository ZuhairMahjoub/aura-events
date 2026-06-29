<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class StoreCartItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'listing_variant_id' => ['required', 'string', 'exists:listing_variants,id'],
            'listing_slot_id'    => ['nullable', 'string', 'exists:listing_slots,id'],
            'quantity'           => ['nullable', 'integer', 'min:1'],
            'booked_date'        => ['nullable', 'date', 'after_or_equal:today'],
            'metadata'           => ['nullable', 'array'],
        ];
    }
}
