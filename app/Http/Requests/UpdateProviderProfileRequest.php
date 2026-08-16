<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProviderProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // التحقق من الملكية والصلاحية أصلاً يتم بالكنترولر (نفس نمط
        // profile() الحالي)، فما في داعي نكرره هون.
        return true;
    }

    public function rules(): array
    {
        $providerType = $this->user()?->providerProfile?->provider_type;

        return [
            // ─── بيانات المستخدم (users) — مشتركة بين الشركة والفريلانسر ───
            'first_name'         => ['sometimes', 'string', 'max:255'],
            'last_name'          => ['sometimes', 'string', 'max:255'],
            // unique مع تجاهل صف المستخدم الحالي نفسه (تحديث، مش تسجيل جديد)
            'phone'              => [
                'sometimes', 'string', 'min:10',
                'regex:/^([0-9\s\-\+\(\)]*)$/',
                Rule::unique('users', 'phone')->ignore($this->user()?->id),
            ],
            'email'              => [
                'sometimes', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
            'settings_language'  => ['sometimes', 'string', 'in:ar,en'],
            'settings_theme'     => ['sometimes', 'string', 'in:light,dark'],

            // ─── بيانات المزوّد (providers) — مشتركة ───
            'brand_name'         => ['sometimes', 'string', 'max:255'],

            // ─── حقول خاصة بالشركة فقط (company_details) ───
            'tax_number'         => [
                'sometimes', 'string', 'max:255',
                Rule::prohibitedIf($providerType !== 'company'),
            ],
            'registration_no'    => [
                'sometimes', 'string', 'max:255',
                Rule::prohibitedIf($providerType !== 'company'),
            ],
            'district_id'        => [
                'sometimes', 'integer', 'exists:districts,id',
                Rule::prohibitedIf($providerType !== 'company'),
            ],
            'address_details'    => [
                'sometimes', 'string', 'max:1000',
                Rule::prohibitedIf($providerType !== 'company'),
            ],

            // ─── حقول خاصة بالفريلانسر فقط (freelancer_details) ───
            'national_id'        => [
                'sometimes', 'string', 'max:50',
                Rule::prohibitedIf($providerType !== 'freelancer'),
            ],
            'experience_years'   => [
                'sometimes', 'integer', 'min:0', 'max:60',
                Rule::prohibitedIf($providerType !== 'freelancer'),
            ],

            // ─── التصنيفات (categories) — مشتركة، جدول pivot category_provider ───
            // نرسل قائمة IDs كاملة تحل محل التصنيفات الحالية (sync، وليست إضافة).
            'category_ids'        => ['sometimes', 'array'],
            'category_ids.*'      => ['integer', 'exists:categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.prohibited'  => 'هذا الحقل غير متاح لنوع حسابك الحالي.',
            'phone.unique'  => 'هذا الرقم مسجل لدى مستخدم آخر بالفعل.',
            'phone.regex'   => 'صيغة رقم الهاتف غير صحيحة.',
            'email.unique'  => 'هذا البريد الإلكتروني مسجل لدى مستخدم آخر بالفعل.',
        ];
    }
}