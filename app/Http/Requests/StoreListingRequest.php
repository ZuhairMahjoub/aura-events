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
        // 1. نتحقق هل النوع المرسل هو منتج مادي أم لا
        $isPhysicalProduct = $this->input('listing_type') === 'physical_product';

        // 2. القواعد الأساسية (الثابتة لكل الأنواع)
        $rules = [
            'provider_id'                => ['required', 'string'],
            'category_id'                => ['required', 'integer'],
            'district_id'                => ['required', 'integer'],
            'title'                      => ['required', 'array'],
            'description'                => ['required', 'array'],
            'listing_type'               => ['required', Rule::in(['physical_product', 'service', 'package'])],
            
            'variants'                   => ['required', 'array', 'min:1'],
            'variants.*.variant_name'    => ['required', 'array'],
            'variants.*.price'           => ['required', 'numeric', 'min:0'],
        ];

        // 3. قواعد مخصصة بناءً على النوع
        if ($isPhysicalProduct) {
            // إذا كان منتجاً: المخزون إجباري، والتواريخ غير مطلوبة إطلاقاً
            $rules['variants.*.stock_quantity'] = ['required', 'integer', 'min:0'];
            $rules['variants.*.date_range']     = ['nullable', 'array'];
            $rules['variants.*.availabilities'] = ['nullable', 'array'];
        } else {
            // إذا كان خدمة أو قاعة: المخزون اختياري، ويجب إرسال أحد نظامي التواريخ
            $rules['variants.*.stock_quantity'] = ['nullable', 'integer', 'min:0'];
            
            // --- النظام الجديد: date_range ---
            $rules['variants.*.date_range']            = ['nullable', 'array', 'required_without:variants.*.availabilities'];
            $rules['variants.*.date_range.start_date'] = ['required_with:variants.*.date_range', 'date', 'after_or_equal:today'];
            $rules['variants.*.date_range.end_date']   = ['required_with:variants.*.date_range', 'date', 'after_or_equal:variants.*.date_range.start_date'];
            $rules['variants.*.date_range.slots']      = ['required_with:variants.*.date_range', 'array'];
            $rules['variants.*.date_range.slots.*.start_time'] = ['required_with:variants.*.date_range.slots', 'date_format:H:i'];
            $rules['variants.*.date_range.slots.*.end_time']   = ['required_with:variants.*.date_range.slots', 'date_format:H:i'];

            // --- النظام القديم: availabilities ---
            $rules['variants.*.availabilities']        = ['nullable', 'array', 'required_without:variants.*.date_range'];
            $rules['variants.*.availabilities.*.available_date'] = ['required_with:variants.*.availabilities', 'date', 'after_or_equal:today'];
            $rules['variants.*.availabilities.*.slots']          = ['required_with:variants.*.availabilities', 'array'];
            $rules['variants.*.availabilities.*.slots.*.start_time'] = ['required_with:variants.*.availabilities.*.slots', 'date_format:H:i'];
            $rules['variants.*.availabilities.*.slots.*.end_time']   = ['required_with:variants.*.availabilities.*.slots', 'date_format:H:i'];
        }

        return $rules;
    }
}