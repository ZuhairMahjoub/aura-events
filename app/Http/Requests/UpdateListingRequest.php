<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

   
    protected function prepareForValidation()
    {
        if (!$this->has('provider_id') && $this->user()) {
            $providerProfile = $this->user()->providerProfile ?? $this->user()->provider;

            if ($providerProfile) {
                $this->merge([
                    'provider_id' => $providerProfile->id,
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'provider_id'   => ['sometimes', 'string'], 
            'category_id'   => ['sometimes', 'integer'],
            'district_id'   => ['sometimes', 'integer'],
            
            'title'         => ['sometimes', 'array', function ($attribute, $value, $fail) {
                if (blank($value['ar'] ?? null) && blank($value['en'] ?? null)) {
                    $fail('يجب إدخال العنوان باللغة العربية أو الإنجليزية على الأقل.');
                }
            }],
            'title.ar'      => ['nullable', 'string', 'max:255'],
            'title.en'      => ['nullable', 'string', 'max:255'],
            
            'description'   => ['sometimes', 'array', function ($attribute, $value, $fail) {
                if (blank($value['ar'] ?? null) && blank($value['en'] ?? null)) {
                    $fail('يجب إدخال الوصف باللغة العربية أو الإنجليزية على الأقل.');
                }
            }],
            'description.ar'=> ['nullable', 'string'],
            'description.en'=> ['nullable', 'string'],
            
            'listing_type'  => ['sometimes', Rule::in(['physical_product', 'service', 'package'])],
            
            'cancel_before_acceptance' => ['sometimes', 'boolean'],
            'cancel_after_acceptance'  => ['sometimes', 'boolean'],
            'cancel_before_payment'    => ['sometimes', 'boolean'],

            'variants'                      => ['sometimes', 'array', 'min:1'],
            'variants.*.id'                 => ['nullable', 'exists:listing_variants,id'], 
            
            'variants.*.variant_name'       => ['required_with:variants', 'array', function ($attribute, $value, $fail) {
                if (blank($value['ar'] ?? null) && blank($value['en'] ?? null)) {
                    $fail('يجب إدخال اسم الباقة باللغة العربية أو الإنجليزية على الأقل.');
                }
            }],
            'variants.*.variant_name.ar'    => ['nullable', 'string', 'max:255'],
            'variants.*.variant_name.en'    => ['nullable', 'string', 'max:255'],
            
            'variants.*.price'              => ['required_with:variants', 'numeric', 'min:0'],
            'variants.*.currency'           => ['string', 'max:3'],
            'variants.*.price_type'         => ['required_with:variants', Rule::in(['fixed', 'hourly'])],
            'variants.*.capacity'           => ['nullable', 'integer', 'min:1'], 
            'variants.*.services'           => ['nullable', 'array'],

            'variants.*.availabilities'                  => ['sometimes', 'array'],
            'variants.*.availabilities.*.id'             => ['nullable', 'exists:listing_availabilities,id'], 
            'variants.*.availabilities.*.available_date' => ['required_with:variants.*.availabilities', 'date', 'after_or_equal:today'],
            'variants.*.availabilities.*.is_blocked'     => ['nullable', 'boolean'],

            'variants.*.availabilities.*.slots'                      => ['nullable', 'array'],
            'variants.*.availabilities.*.slots.*.id'                 => ['nullable', 'exists:listing_slots,id'], 
            'variants.*.availabilities.*.slots.*.slot_name'          => ['nullable', 'array'],
            'variants.*.availabilities.*.slots.*.slot_name.ar'       => ['nullable', 'string'],
            'variants.*.availabilities.*.slots.*.slot_name.en'       => ['nullable', 'string'],
            'variants.*.availabilities.*.slots.*.start_time'         => ['required_with:variants.*.availabilities.*.slots', 'date_format:H:i'],
            'variants.*.availabilities.*.slots.*.end_time'           => ['required_with:variants.*.availabilities.*.slots', 'date_format:H:i'],
            'variants.*.availabilities.*.slots.*.remaining_capacity' => ['nullable', 'integer', 'min:1'],
        ];
    }
}