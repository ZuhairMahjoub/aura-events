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
        // جلب كائن الـ listing الحالي من الـ Route لضمان أمان الـ Scopes
        $listingId = $this->route('listing')?->id;

        return [
            'provider_id'   => ['sometimes', 'string'], 
            'category_id'   => ['sometimes', 'integer', 'exists:categories,id'],
            'district_id'   => ['sometimes', 'integer', 'exists:districts,id'],
            
            // 🖼️ فحص مصفوفات الصور الأساسية وصور المتغيرات
            'images'        => ['sometimes', 'array'],
            'images.*'      => ['string'],

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
            'secondary_contact_number' => ['sometimes', 'string', 'max:50'],
            'is_provider_location_based' => ['sometimes', 'boolean'],
            'material_composition'     => ['nullable', 'string', 'max:255'],
            'moderation_status'        => ['sometimes', Rule::in(['draft', 'pending_approval'])],

            // ── Variants Validation ───────────────────────────────────
            'variants'                => ['sometimes', 'array', 'min:1'],
            // 🔒 حماية الـ ID لكي يتبع للـ Listing الحالي حصراً
            'variants.*.id'           => [
                'nullable', 
                Rule::exists('listing_variants', 'id')->where('listing_id', $listingId)
            ], 
            
            'variants.*.variant_name' => ['required', 'array', function ($attribute, $value, $fail) {
                if (blank($value['ar'] ?? null) && blank($value['en'] ?? null)) {
                    $fail('يجب إدخال اسم الباقة باللغة العربية أو الإنجليزية على الأقل.');
                }
            }],
            'variants.*.variant_name.ar' => ['nullable', 'string', 'max:255'],
            'variants.*.variant_name.en' => ['nullable', 'string', 'max:255'],
            
            'variants.*.price'      => ['required', 'numeric', 'min:0'],
            'variants.*.currency'   => ['string', 'max:3'],
            'variants.*.price_type' => ['required', Rule::in(['fixed', 'hourly'])],
            'variants.*.capacity'   => ['nullable', 'integer', 'min:1'], 
            'variants.*.services'   => ['nullable', 'array'],
            'variants.*.images'     => ['sometimes', 'array'],
            'variants.*.images.*'   => ['string'],

            // ── Availabilities Validation ─────────────────────────────
            'variants.*.availabilities' => ['sometimes', 'array'],
            // 🔒 حماية الـ Availability ID لكي يتبع للـ Variant المرسل والـ Listing الحالي
            'variants.*.availabilities.*.id' => [
                'nullable', 
                Rule::exists('listing_availabilities', 'id')
            ], 
            'variants.*.availabilities.*.available_date' => ['required', 'date', 'after_or_equal:today'],
            'variants.*.availabilities.*.is_blocked'     => ['nullable', 'boolean'],

            // ── Slots Validation ──────────────────────────────────────
            'variants.*.availabilities.*.slots'              => ['nullable', 'array'],
            'variants.*.availabilities.*.slots.*.id'         => ['nullable', 'exists:listing_slots,id'], 
            'variants.*.availabilities.*.slots.*.slot_name'    => ['nullable', 'array'],
            'variants.*.availabilities.*.slots.*.slot_name.ar' => ['nullable', 'string'],
            'variants.*.availabilities.*.slots.*.slot_name.en' => ['nullable', 'string'],
            
            // ⏳ التأكد من صياغة الوقت وأن النهاية بعد البداية دائماً
            'variants.*.availabilities.*.slots.*.start_time' => ['required', 'date_format:H:i'],
            'variants.*.availabilities.*.slots.*.end_time'   => [
                'required', 
                'date_format:H:i', 
                'after:variants.*.availabilities.*.slots.*.start_time'
            ],
            'variants.*.availabilities.*.slots.*.remaining_capacity' => ['nullable', 'integer', 'min:1'],
        ];
    }
}