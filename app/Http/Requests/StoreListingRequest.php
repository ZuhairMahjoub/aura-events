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
            // البيانات الأساسية والربط
            'provider_id'                => ['required', 'string'],
            'category_id'                => ['required', 'integer'],
            'district_id'                => ['required', 'integer'],
            
            // حقول الترجمة للعنوان والوصف
            'title'                      => ['required', 'array', function ($attribute, $value, $fail) {
                if (blank($value['ar'] ?? null) && blank($value['en'] ?? null)) {
                    $fail('يجب إدخال العنوان باللغة العربية أو الإنجليزية على الأقل.');
                }
            }],
            'title.ar'                   => ['nullable', 'string', 'max:255'],
            'title.en'                   => ['nullable', 'string', 'max:255'],
            
            'description'                => ['required', 'array', function ($attribute, $value, $fail) {
                if (blank($value['ar'] ?? null) && blank($value['en'] ?? null)) {
                    $fail('يجب إدخال الوصف باللغة العربية أو الإنجليزية على الأقل.');
                }
            }],
            'description.ar'             => ['nullable', 'string'],
            'description.en'             => ['nullable', 'string'],
            
            'listing_type'               => ['required', Rule::in(['physical_product', 'service', 'package'])],
            
            'material_composition'       => ['nullable', 'string', 'max:255'],
            'secondary_contact_number'   => ['nullable', 'string', 'max:50'],
            'cancel_before_acceptance'   => ['nullable', 'boolean'],
            'cancel_after_acceptance'    => ['nullable', 'boolean'],
            'cancel_before_payment'      => ['nullable', 'boolean'],
            'is_provider_location_based' => ['nullable', 'boolean'],
            'moderation_status'          => ['nullable', Rule::in(['draft', 'pending_approval'])], // المزود يرسلها فقط كمسودة أو طلب موافقة

            'images'                     => ['nullable', 'array'],
            'images.*'                   => ['nullable', 'string'], 

            'variants'                   => ['required', 'array', 'min:1'],
            'variants.*.variant_name'    => ['required', 'array', function ($attribute, $value, $fail) {
                if (blank($value['ar'] ?? null) && blank($value['en'] ?? null)) {
                    $fail('يجب إدخال اسم الباقة باللغة العربية أو الإنجليزية على الأقل.');
                }
            }],
            'variants.*.variant_name.ar' => ['nullable', 'string', 'max:255'],
            'variants.*.variant_name.en' => ['nullable', 'string', 'max:255'],
            
            'variants.*.images'          => ['nullable', 'array'],
            'variants.*.images.*'        => ['nullable', 'string'],

            'variants.*.price'            => ['required', 'numeric', 'min:0'],
            'variants.*.currency'         => ['nullable', 'string', 'size:3'], // تم تغييرها لـ size:3 لتطابق رموز العملات مثل USD
            'variants.*.price_type'       => ['nullable', Rule::in(['fixed', 'hourly'])],
            'variants.*.stock_quantity'   => ['nullable', 'integer', 'min:0'], 
            'variants.*.dynamic_attributes'=> ['nullable', 'array'],

            'variants.*.availabilities'                                 => ['nullable', 'array'],
            'variants.*.availabilities.*.available_date'                => ['required', 'date', 'after_or_equal:today'],
            'variants.*.availabilities.*.is_blocked'                    => ['nullable', 'boolean'],

            'variants.*.availabilities.*.slots'                         => ['nullable', 'array'],
            'variants.*.availabilities.*.slots.*.slot_name'             => ['nullable', 'array'],
            'variants.*.availabilities.*.slots.*.slot_name.ar'          => ['nullable', 'string', 'max:255'],
            'variants.*.availabilities.*.slots.*.slot_name.en'          => ['nullable', 'string', 'max:255'],
            'variants.*.availabilities.*.slots.*.start_time'            => ['required', 'date_format:H:i'], 
            'variants.*.availabilities.*.slots.*.end_time'              => ['required', 'date_format:H:i', 'after:variants.*.availabilities.*.slots.*.start_time'], // ميزة ذكية للتأكد أن وقت النهاية بعد البداية
            'variants.*.availabilities.*.slots.*.remaining_capacity'    => ['nullable', 'integer', 'min:1'],
        ];
    }
}