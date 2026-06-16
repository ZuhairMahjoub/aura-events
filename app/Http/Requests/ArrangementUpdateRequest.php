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
            'price_type' => 'sometimes|in:fixed,per_hour,per_day',
            'capacity'   => 'sometimes|nullable|integer|min:1|max:10000',

            // ── Cancellation policies ────────────────────────────────────────
            'cancel_before_acceptance' => 'sometimes|boolean',
            'cancel_after_acceptance'  => 'sometimes|boolean',
            'cancel_before_payment'    => 'sometimes|boolean',

            // ── Package items (full replacement / sync) ───────────────────────
            // When provided, replaces all existing package_items for this variant.
            'items'              => 'sometimes|array|min:1|max:100',
            'items.*.variant_id' => 'required_with:items|string|exists:listing_variants,id',
            'items.*.quantity'   => 'required_with:items|integer|min:1|max:1000',

            // ── Images to add (temp paths) ────────────────────────────────────
            'images'   => 'sometimes|array|max:10',
            'images.*' => 'string|regex:/^temp\/[a-zA-Z0-9\-_.]+$/',

            // ── Image IDs to remove ───────────────────────────────────────────
            'images_to_delete'   => 'sometimes|array',
            'images_to_delete.*' => 'string|exists:images,id',
        ];
    }

    public function messages(): array
    {
        return [
            'price_type.in'                  => 'نوع السعر يجب أن يكون: fixed, per_hour, أو per_day.',
            'title.min'                      => 'العنوان يجب أن يكون 3 أحرف على الأقل.',
            'description.min'                => 'الوصف يجب أن يكون 10 أحرف على الأقل.',
            'items.min'                      => 'يجب أن تحتوي الباقة على عنصر واحد على الأقل.',
            'items.*.variant_id.exists'      => 'أحد عناصر الباقة غير موجود في النظام.',
            'items.*.quantity.min'           => 'الكمية يجب أن تكون 1 على الأقل.',
            'images.*.regex'                 => 'مسار الصورة غير صالح. يرجى رفع الصورة أولاً عبر /uploads/temp.',
            'images_to_delete.*.exists'      => 'إحدى الصور المراد حذفها غير موجودة.',
        ];
    }
}
