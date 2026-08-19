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
        $isPhysicalProduct = $this->input('listing_type') === 'physical_product';

        $rules = [
            'provider_id'                => ['required', 'string'],
            'category_id'                => ['required', 'integer'],
            'district_id'                => ['required', 'integer'],
            'title'                      => ['required', 'array'],
            'description'                => ['required', 'array'],
            'listing_type'               => ['required', Rule::in(['physical_product', 'service', 'package', 'hall'])],
            'cancel_before_acceptance'   => ['nullable', 'boolean'],
            'cancel_after_acceptance'    => ['nullable', 'boolean'],
            'cancel_before_payment'      => ['nullable', 'boolean'],
            'is_provider_location_based' => ['nullable', 'boolean'],
            'secondary_contact_number'   => ['nullable', 'string', 'max:20'],
            'material_composition'       => ['nullable', 'string', 'max:255'],
            'moderation_status'          => ['nullable', 'string', Rule::in(['draft', 'pending_approval'])],
            'variants'                   => ['required', 'array', 'min:1'],
            'variants.*.id'              => ['nullable', 'string'],
            'variants.*.variant_name'    => ['required', 'array'],
            'variants.*.price'           => ['required', 'numeric', 'min:0'],
            'variants.*.currency'        => ['nullable', 'string', 'max:10'],
            'variants.*.price_type'      => ['nullable', 'string'],
            'variants.*.services'        => ['nullable', 'array'],
            'variants.*.capacity' => ['required', 'integer', 'min:1'],
            'images.*'             => ['nullable', 'array'],
            'images.*.id'          => ['nullable', 'string'],
            'images.*.path'        => ['nullable', 'string'],
            'images'               => ['nullable', 'array'],

            'variants.*.images'        => ['nullable', 'array'],
            'variants.*.images.*'      => ['nullable', 'array'],
            'variants.*.images.*.id'   => ['nullable', 'string'],
            'variants.*.images.*.path' => ['nullable', 'string'],
        ];

        if ($isPhysicalProduct) {
            $rules['variants.*.stock_quantity'] = ['required', 'integer', 'min:0'];
            $rules['variants.*.date_range']     = ['nullable', 'array'];
            $rules['variants.*.availabilities'] = ['nullable', 'array'];
        } elseif ($this->input('listing_type') === 'service' || $this->input('listing_type') === 'package' || $this->input('listing_type') === 'hall') {
            // Fix #4: was a bare { } block, now correctly an elseif
            $rules['variants.*.stock_quantity'] = ['nullable', 'integer', 'min:0'];
            $rules['variants.*.capacity'] = ['nullable', 'integer', 'min:1'];
            $rules['variants.*.date_range']                    = ['nullable', 'array', 'required_without:variants.*.availabilities'];
            $rules['variants.*.date_range.start_date']         = ['required_with:variants.*.date_range', 'date', 'after_or_equal:today'];
            $rules['variants.*.date_range.end_date']           = ['required_with:variants.*.date_range', 'date', 'after_or_equal:variants.*.date_range.start_date'];
            $rules['variants.*.date_range.slots']              = ['required_with:variants.*.date_range', 'array'];
            $rules['variants.*.date_range.slots.*.start_time'] = ['required_with:variants.*.date_range.slots', 'date_format:H:i'];
            $rules['variants.*.date_range.slots.*.end_time']   = ['required_with:variants.*.date_range.slots', 'date_format:H:i'];

            $rules['variants.*.availabilities'] = [
                'nullable',
                'array',
                'required_without:variants.*.date_range',
                function ($attribute, $value, $fail) {
                    $dates = collect($value)->pluck('available_date');
                    if ($dates->duplicates()->isNotEmpty()) {
                        $fail('تحتوي قائمة التواريخ على قيم مكررة، يرجى إدخال كل تاريخ مرة واحدة فقط.');
                    }
                },
            ];

            $rules['variants.*.availabilities.*.id']                          = ['nullable', 'string'];
            $rules['variants.*.availabilities.*.available_date']              = ['required_with:variants.*.availabilities', 'date', 'after_or_equal:today'];
            $rules['variants.*.availabilities.*.is_blocked']                  = ['nullable', 'boolean'];
            $rules['variants.*.availabilities.*.slots']                       = ['required_with:variants.*.availabilities', 'array'];
            $rules['variants.*.availabilities.*.slots.*.id']                  = ['nullable', 'string'];
            $rules['variants.*.availabilities.*.slots.*.slot_name']           = ['nullable'];
            $rules['variants.*.availabilities.*.slots.*.start_time']          = ['required_with:variants.*.availabilities.*.slots', 'date_format:H:i'];
            $rules['variants.*.availabilities.*.slots.*.end_time']            = ['required_with:variants.*.availabilities.*.slots', 'date_format:H:i'];
            $rules['variants.*.availabilities.*.slots.*.remaining_capacity']  = ['nullable', 'integer', 'min:1'];
            $rules['variants.*.date_range.slots.*.start_time']                = ['required_with:variants.*.date_range.slots', 'date_format:H:i'];
            $rules['variants.*.date_range.slots.*.end_time']                  = ['required_with:variants.*.date_range.slots', 'date_format:H:i'];
            $rules['variants.*.date_range.slots.*.slot_name']                 = ['nullable'];
            $rules['variants.*.date_range.slots.*.remaining_capacity']        = ['nullable', 'integer', 'min:1'];
        }

        return $rules;
    }
}
