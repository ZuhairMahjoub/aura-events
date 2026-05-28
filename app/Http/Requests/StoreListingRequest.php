<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreListingRequest extends FormRequest
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
            'provider_id'   => ['required', 'string'],
            'category_id'   => ['required', 'integer'],
            'district_id'   => ['required', 'integer'],
            
            'title'         => ['required', 'array', function ($attribute, $value, $fail) {
                if (blank($value['ar'] ?? null) && blank($value['en'] ?? null)) {
                    $fail('يجب إدخال العنوان باللغة العربية أو الإنجليزية على الأقل.');
                }
            }],
            'title.ar'      => ['nullable', 'string', 'max:255'],
            'title.en'      => ['nullable', 'string', 'max:255'],
            
            'description'   => ['required', 'array', function ($attribute, $value, $fail) {
                if (blank($value['ar'] ?? null) && blank($value['en'] ?? null)) {
                    $fail('يجب إدخال الوصف باللغة العربية أو الإنجليزية على الأقل.');
                }
            }],
            'description.ar'=> ['nullable', 'string'],
            'description.en'=> ['nullable', 'string'],
            
            'listing_type'  => ['required', Rule::in(['physical_product', 'service', 'package'])],
            
            'variants'                      => ['required', 'array', 'min:1'],
            
            'variants.*.variant_name'       => ['required', 'array', function ($attribute, $value, $fail) {
                if (blank($value['ar'] ?? null) && blank($value['en'] ?? null)) {
                    $fail('يجب إدخال اسم الباقة باللغة العربية أو الإنجليزية على الأقل.');
                }
            }],
            'images'   => ['nullable', 'array'],
            'images.*' => ['nullable', 'string'], 

            'variants.*.images'   => ['nullable', 'array'],
            'variants.*.images.*' => ['nullable', 'string'],

            'variants.*.variant_name.ar'    => ['nullable', 'string'],
            'variants.*.variant_name.en'    => ['nullable', 'string'],
            
            'variants.*.price'              => ['required', 'numeric', 'min:0'],
            'variants.*.currency'           => ['string', 'max:3'],
            'variants.*.price_type'         => [Rule::in(['fixed', 'hourly'])],
            'variants.*.stock_quantity'     => ['nullable', 'integer', 'min:0'], 
            'variants.*.dynamic_attributes' => ['nullable', 'array'],

            'variants.*.availabilities'                  => ['nullable', 'array'],
            'variants.*.availabilities.*.available_date' => ['required', 'date', 'after_or_equal:today'],
            'variants.*.availabilities.*.is_blocked'     => ['nullable', 'boolean'],

            'variants.*.availabilities.*.slots'                      => ['nullable', 'array'],
            'variants.*.availabilities.*.slots.*.slot_name'          => ['nullable', 'array'],
            'variants.*.availabilities.*.slots.*.slot_name.ar'       => ['nullable', 'string'],
            'variants.*.availabilities.*.slots.*.slot_name.en'       => ['nullable', 'string'],
            'variants.*.availabilities.*.slots.*.start_time'         => ['required', 'date_format:H:i'], 
            'variants.*.availabilities.*.slots.*.end_time'           => ['required', 'date_format:H:i'],
            'variants.*.availabilities.*.slots.*.remaining_capacity' => ['nullable', 'integer', 'min:1'],
        ];
    }
}