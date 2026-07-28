<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArrangementUpdateRequest extends FormRequest
{
    /**
     * Only active company providers may update their own packages.
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
            // ── Core listing fields (all optional on update) ─────────────────
            'category_id'              => 'sometimes|exists:categories,id',
            'district_id'              => 'sometimes|exists:districts,id',
            'title'                    => 'sometimes|string|min:3|max:255',
            'description'              => 'sometimes|string|min:10',
            'secondary_contact_number' => 'sometimes|nullable|string|regex:/^[0-9\-\+\s()]+$/',

            // ── Variant / pricing ────────────────────────────────────────────
            'price'      => 'sometimes|numeric|min:0|max:999999.99',
            'price_type' => 'sometimes|in:fixed,hourly',
            'capacity'   => 'sometimes|nullable|integer|min:1|max:10000',
            'currency'   => 'sometimes|string|size:3',
            // ── Cancellation policies ────────────────────────────────────────
            'cancel_before_acceptance' => 'sometimes|boolean',
            'cancel_after_acceptance'  => 'sometimes|boolean',
            'cancel_before_payment'    => 'sometimes|boolean',

            // ── Package items (full replacement / sync) ───────────────────────
            // When provided, replaces all existing package_items for this variant.
            'items'              => 'sometimes|array|max:100',
            'items.*.variant_id' => 'required_with:items|string|exists:listing_variants,id',
            'items.*.quantity'   => 'required_with:items|integer|min:1|max:1000',

            // ── Contract-linked freelancers (full replacement / sync) ─────────
            // ⚠️ كانت غائبة بالكامل عن هذا الـ Request رغم وجودها في
            // ArrangementStoreRequest — وهذا سبب كون تعديل/حذف الفريلانسرز
            // عبر update() لم يكن يعمل إطلاقاً: أي مفتاح غير معرَّف بالـ rules()
            // يُسقطه Laravel بصمت من $request->validated() قبل ما يوصل الـ Action.
            'freelancers'                  => 'sometimes|array|max:50',
            'freelancers.*.freelancer_id'  => 'required_with:freelancers|string|exists:providers,id',
            'freelancers.*.contract_id'    => 'required_with:freelancers|string|exists:company_freelancer_contracts,id',

            // ── Images (aligned with Listing's sync format) ────────────────
            // images غير موجود بالطلب      → لا تُمس الصور إطلاقاً
            // images: []                    → احذف كل الصور
            // images: [{id}, {path}, ...]   → sync (احتفظ بيلي عندها id، ارفع يلي عندها path فقط)
            'images'         => 'sometimes|array|max:10',
            'images.*'       => ['nullable', 'array'],
            'images.*.id'    => ['nullable', 'string', 'exists:images,id'],
            'images.*.path'  => ['nullable', 'string', 'regex:/^(temp\/)?[a-zA-Z0-9\-_.\/]+$/'],

            // ── Availabilities (aligned with Listing's per-date sync format) ──
            // يُستخدم عند الحاجة لتعديل تواريخ محدَّدة فردياً مع الحفاظ على
            // IDs الموجودة (بعكس date_range اللي بيولّد نطاق كامل من جديد).
            'availabilities'                              => 'sometimes|array',
            'availabilities.*.id'                         => ['nullable', 'string', 'exists:listing_availabilities,id'],
            'availabilities.*.available_date'              => 'required_with:availabilities|date_format:Y-m-d',
            'availabilities.*.is_blocked'                  => 'sometimes|boolean',
            'availabilities.*.slots'                       => 'nullable|array',
            'availabilities.*.slots.*.id'                  => ['nullable', 'string', 'exists:listing_slots,id'],
            'availabilities.*.slots.*.slot_name'           => 'nullable|array',
            'availabilities.*.slots.*.slot_name.ar'        => 'nullable|string|max:255',
            'availabilities.*.slots.*.slot_name.en'        => 'nullable|string|max:255',
            'availabilities.*.slots.*.start_time'          => 'required_with:availabilities.*.slots|date_format:H:i',
            'availabilities.*.slots.*.end_time'            => 'required_with:availabilities.*.slots|date_format:H:i|after:availabilities.*.slots.*.start_time',
            'availabilities.*.slots.*.remaining_capacity'  => 'nullable|integer|min:0',

            // ── date_range (إصلاح: كانت مفقودة بالكامل من هنا، ما يعني أن
            // $request->validated() كانت تُسقط هذا الحقل بصمت وتحديث تواريخ
            // الباقة عبر date_range لم يكن يعمل فعلياً إطلاقاً) ─────────────
            'date_range'                             => 'sometimes|array',
            'date_range.start_date'                  => 'required_with:date_range|date_format:Y-m-d',
            'date_range.end_date'                    => 'required_with:date_range|date_format:Y-m-d|after_or_equal:date_range.start_date',
            'date_range.is_blocked'                  => 'sometimes|boolean',
            'date_range.slots'                       => 'nullable|array',
            'date_range.slots.*.slot_name'           => 'nullable|array',
            'date_range.slots.*.slot_name.ar'        => 'nullable|string|max:255',
            'date_range.slots.*.slot_name.en'        => 'nullable|string|max:255',
            'date_range.slots.*.start_time'          => 'required_with:date_range.slots|date_format:H:i',
            'date_range.slots.*.end_time'            => 'required_with:date_range.slots|date_format:H:i|after:date_range.slots.*.start_time',
            'date_range.slots.*.remaining_capacity'  => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'price_type.in'                  => 'نوع السعر يجب أن يكون: fixed, hourly.',
            'title.min'                      => 'العنوان يجب أن يكون 3 أحرف على الأقل.',
            'description.min'                => 'الوصف يجب أن يكون 10 أحرف على الأقل.',
            'items.*.variant_id.exists'      => 'أحد عناصر الباقة غير موجود في النظام.',
            'items.*.quantity.min'           => 'الكمية يجب أن تكون 1 على الأقل.',
            'freelancers.*.freelancer_id.exists' => 'أحد الفريلانسرز غير موجود في النظام.',
            'freelancers.*.contract_id.exists'   => 'أحد العقود المرفقة غير موجود في النظام.',
            'images.*.path.regex'            => 'مسار الصورة غير صالح. يرجى رفع الصورة أولاً عبر /uploads/temp.',
            'images.*.id.exists'             => 'إحدى الصور المُرسَلة غير موجودة.',
            'date_range.end_date.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد أو يساوي تاريخ البداية.',
            'availabilities.*.slots.*.end_time.after' => 'وقت النهاية يجب أن يكون بعد وقت البداية.',
        ];
    }
}
