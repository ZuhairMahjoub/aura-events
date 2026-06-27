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
            'price_type' => 'required|in:fixed,per_hour,per_day',
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
            'images'   => 'nullable|array|max:10',
            'images.*' => 'string|regex:/^temp\/[a-zA-Z0-9\-_.]+$/',

            'currency'   => 'required|string|size:3',
            'availabilities'                       => 'nullable|array',
            'availabilities.*.date'                => 'required_with:availabilities|date_format:Y-m-d',
            'availabilities.*.slots'               => 'nullable|array',
            'availabilities.*.slots.*.start_time'  => 'required_with:availabilities.*.slots|date_format:H:i',
            'availabilities.*.slots.*.end_time'    => 'required_with:availabilities.*.slots|date_format:H:i',
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
