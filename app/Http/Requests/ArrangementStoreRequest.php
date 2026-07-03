<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArrangementStoreRequest extends FormRequest
{
    /**
     * Only active company providers may create packages.
     */
    public function authorize(): bool
    {
        $provider = $this->user()->providerProfile;

        return $provider
            && $provider->is_active
            && $provider->provider_type === 'company';
    }

    public function rules(): array
    {
        return [
            // ── Core listing fields ──────────────────────────────────────────
            'category_id'              => 'required|exists:categories,id',
            'district_id'              => 'required|exists:districts,id',
            'title'                    => 'required|string|min:3|max:255',
            'description'              => 'required|string|min:10',
            'secondary_contact_number' => 'nullable|string|regex:/^[0-9\-\+\s()]+$/',

            // ── Variant / pricing ────────────────────────────────────────────
            'price'      => 'required|numeric|min:0|max:999999.99',
            'price_type' => 'required|in:fixed,hourly',
            'currency'   => 'required|string|size:3',
            'capacity'   => 'nullable|integer|min:1|max:10000',

            // ── Cancellation policies ────────────────────────────────────────
            'cancel_before_acceptance' => 'boolean',
            'cancel_after_acceptance'  => 'boolean',
            'cancel_before_payment'    => 'boolean',

            // ── Package items (products, halls, services) ─────────────────────
            // Each item is a ListingVariant belonging to the company.
            'items'              => 'nullable|array|min:1|max:100',
            'items.*.variant_id' => 'required_with:items|string|exists:listing_variants,id',
            'items.*.quantity'   => 'required_with:items|integer|min:1|max:1000',

            // ── Contract-linked freelancers ───────────────────────────────────
            // Freelancers added via their employment contract with the company.
            'freelancers'                     => 'nullable|array|max:50',
            'freelancers.*.freelancer_id'     => 'required_with:freelancers|string|exists:providers,id',
            'freelancers.*.contract_id'       => 'required_with:freelancers|string|exists:company_freelancer_contracts,id',

            // ── Images (temp paths from POST /uploads/temp) ──────────────────
           'images.*'             => ['nullable', 'array'],
            'images.*.id'          => ['nullable', 'string'],
            'images.*.path'        => ['nullable', 'string'],
            'currency'   => 'required|string|size:3',
            'availabilities'                       => 'nullable|array',
            'availabilities.*.date'                => 'required_with:availabilities|date_format:Y-m-d',
            'availabilities.*.slots'               => 'nullable|array',
            'availabilities.*.slots.*.start_time'  => 'required_with:availabilities.*.slots|date_format:H:i',
            'availabilities.*.slots.*.end_time'    => 'required_with:availabilities.*.slots|date_format:H:i',
            // داخل مصفوفة return [ ... ] في دالة rules()

            'date_range'                             => 'nullable|array',
            'date_range.start_date'                  => 'required_with:date_range|date_format:Y-m-d',
            'date_range.end_date'                    => 'required_with:date_range|date_format:Y-m-d|after_or_equal:date_range.start_date',
            'date_range.is_blocked'                  => 'boolean',
            'date_range.slots'                       => 'nullable|array',
            'date_range.slots.*.slot_name'           => 'required_with:date_range.slots|array',
            'date_range.slots.*.slot_name.en'        => 'string|max:255',
            'date_range.slots.*.slot_name.ar'        => 'string|max:255',
            'date_range.slots.*.start_time'          => 'required_with:date_range.slots|date_format:H:i',
            'date_range.slots.*.end_time'            => 'required_with:date_range.slots|date_format:H:i',
            'date_range.slots.*.remaining_capacity'  => 'integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'price_type.in'                      => 'نوع السعر يجب أن يكون: fixed, per_hour, أو per_day.',
            'title.min'                          => 'العنوان يجب أن يكون 3 أحرف على الأقل.',
            'description.min'                    => 'الوصف يجب أن يكون 10 أحرف على الأقل.',
            'secondary_contact_number.regex'     => 'صيغة رقم الهاتف غير صحيحة.',
            'items.min'                          => 'يجب أن تحتوي الباقة على عنصر واحد على الأقل.',
            'items.max'                          => 'لا يمكن إضافة أكثر من 100 عنصر للباقة.',
            'items.*.variant_id.exists'          => 'أحد عناصر الباقة غير موجود في النظام.',
            'items.*.quantity.min'               => 'الكمية يجب أن تكون 1 على الأقل.',
            'items.*.quantity.max'               => 'الكمية لا يمكن أن تتجاوز 1000.',
            'freelancers.*.freelancer_id.exists' => 'أحد الفريلانسرز غير موجود في النظام.',
            'freelancers.*.contract_id.exists'   => 'أحد العقود المرفقة غير موجود في النظام.',
            'images.*.regex'                     => 'مسار الصورة غير صالح. يرجى رفع الصورة أولاً عبر /uploads/temp.',
        ];
    }
}
