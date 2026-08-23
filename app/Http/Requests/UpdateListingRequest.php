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
        // جلب كائن الـ listing الحالي لضمان أمان الـ Scopes
        $listingId = $this->route('listing')?->id;

        return [
            'provider_id'  => ['sometimes', 'string'],
            'category_id'  => ['sometimes', 'integer', 'exists:categories,id'],
            'district_id'  => ['sometimes', 'integer', 'exists:districts,id'],

            'title'        => ['sometimes', 'array'],
            'title.ar'     => ['nullable', 'string', 'max:255'],
            'title.en'     => ['nullable', 'string', 'max:255'],

            'description'  => ['sometimes', 'array'],
            'description.ar' => ['nullable', 'string'],
            'description.en' => ['nullable', 'string'],

            'listing_type' => ['sometimes', Rule::in(['physical_product', 'service', 'package', 'hall'])],

            'cancel_before_acceptance' => ['sometimes', 'boolean'],
            'cancel_after_acceptance'  => ['sometimes', 'boolean'],
            'cancel_before_payment'    => ['sometimes', 'boolean'],
            'secondary_contact_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_provider_location_based' => ['sometimes', 'boolean'],
            'material_composition'     => ['nullable', 'string', 'max:255'],
            'moderation_status'        => ['sometimes', Rule::in(['draft', 'pending_approval'])],

            // ── Variants Validation ───────────────────────────────────
            'variants'                => ['sometimes', 'array', 'min:1'],
            'variants.*.id'           => [
                'nullable',
                Rule::exists('listing_variants', 'id')->where('listing_id', $listingId)
            ],
            'variants.*.variant_name' => ['required', 'array'],
            'variants.*.variant_name.ar' => ['nullable', 'string', 'max:255'],
            'variants.*.variant_name.en' => ['nullable', 'string', 'max:255'],

            'variants.*.price'      => ['required', 'numeric', 'min:0'],
            'variants.*.currency'   => ['nullable', 'string', 'max:10'],
            'variants.*.price_type' => ['required', Rule::in(['fixed', 'hourly'])],
            'variants.*.capacity'   => ['nullable', 'integer', 'min:1'],
            'variants.*.services'   => ['nullable', 'array'],
            'variants.*.stock_quantity' => ['nullable', 'integer', 'min:0'],

            // 🖼 صور الـ Listing والـ Variants
            'images'         => ['nullable', 'array'],
            'images.*'       => ['nullable', 'array'],
            'images.*.id'    => ['nullable', 'string'],
            'images.*.path'  => ['nullable', 'string'],

            'variants.*.images'       => ['nullable', 'array'],
            'variants.*.images.*'     => ['nullable', 'array'],
            'variants.*.images.*.id'  => ['nullable', 'string'],
            'variants.*.images.*.path' => ['nullable', 'string'],
            'variants.*.date_range' => ['nullable', 'array'],
            'variants.*.date_range.start_date' => ['nullable', 'date'],
            'variants.*.date_range.end_date' => ['nullable', 'date'],
            'variants.*.date_range.slots' => ['nullable', 'array'],
            
            // 💡 تصحيح نوع بيانات اسم الشفت ليقبل مصفوفة اللغات
            'variants.*.date_range.slots.*.slot_name' => ['nullable', 'array'],
            'variants.*.date_range.slots.*.slot_name.ar' => ['nullable', 'string'],
            'variants.*.date_range.slots.*.slot_name.en' => ['nullable', 'string'],
            
            'variants.*.date_range.slots.*.start_time' => ['nullable', 'date_format:H:i'],
            'variants.*.date_range.slots.*.end_time' => ['nullable', 'date_format:H:i'],
            'variants.*.date_range.slots.*.remaining_capacity' => ['nullable', 'integer', 'min:1'],

            'variants.*.availabilities' => ['nullable', 'array'],
            'variants.*.availabilities.*.id' => ['nullable', 'string'],
            'variants.*.availabilities.*.available_date' => ['nullable', 'date'],
            'variants.*.availabilities.*.is_blocked' => ['nullable', 'boolean'],

            'variants.*.availabilities.*.slots' => ['nullable', 'array'],
            'variants.*.availabilities.*.slots.*.id' => ['nullable', 'string'],
            
            // 💡 تصحيح نوع بيانات اسم الشفت ليقبل مصفوفة اللغات
            'variants.*.availabilities.*.slots.*.slot_name' => ['nullable', 'array'],
            'variants.*.availabilities.*.slots.*.slot_name.ar' => ['nullable', 'string'],
            'variants.*.availabilities.*.slots.*.slot_name.en' => ['nullable', 'string'],
            
            'variants.*.availabilities.*.slots.*.start_time' => ['nullable', 'date_format:H:i'],
            'variants.*.availabilities.*.slots.*.end_time' => ['nullable', 'date_format:H:i'],
            'variants.*.availabilities.*.slots.*.remaining_capacity' => ['nullable', 'integer', 'min:1'],
        ];
    }
}