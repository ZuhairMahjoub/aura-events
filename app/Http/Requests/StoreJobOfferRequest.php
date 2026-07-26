<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobOfferRequest extends FormRequest
{
    /**
     * التحقق من "نوع الحساب" (شركة) أصبح مسؤولية middleware
     * provider_type:company على مستوى الـ route، فلا حاجة لتكراره هنا.
     * نتأكد فقط من وجود ملف provider فعلي (احتياط، لأن rules() تعتمد عليه).
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->providerProfile;
    }

    public function rules(): array
    {
        $company = $this->user()->providerProfile;

        return [
            // اختيار حر: الشركة تقدر تربط عرض الوظيفة إما بخدمة (service_id)
            // أو بفئة عامة (category_id) — المهم إرسال واحد منهما على الأقل.
            // لا يوجد إجبار مسبق حسب امتلاك الشركة خدمات من عدمه؛ القرار
            // بالكامل للشركة نفسها وقت النشر.
            'service_id' => [
                'required_without:category_id',
                'nullable',
                'string',
                Rule::exists('services', 'id')->where('company_id', $company->id),
            ],
            'category_id' => [
                'required_without:service_id',
                'nullable',
                'integer',
                // الفئة لازم تكون من ضمن الفئات المسجَّلة فعلياً لهذه الشركة
                // (جدول category_provider)، لا أي فئة عشوائية بالنظام.
                Rule::exists('category_provider', 'category_id')->where('provider_id', $company->id),
            ],

            'job_title' => ['required', 'string', 'max:255'],
            'time_condition' => ['required', 'in:Permanent,Temporary,Contract'],
            'event_type' => ['required', 'string'],
            'job_start_date' => ['required', 'date', 'after_or_equal:today'],

            // application_deadline لازم تكون قبل أو تساوي job_start_date —
            // منطقياً غير مقبول أن يكون آخر موعد للتقديم بعد تاريخ بدء العمل.
            'application_deadline' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:job_start_date'],

            'salary' => ['required', 'numeric', 'min:0'],
            'payment_system' => ['required', 'in:Per Event,Monthly,Hourly'],
            'specific_event_association' => ['nullable', 'string'],
            'experience_level' => ['required', 'in:Junior,Mid,Senior'],
            'company_equipment_provided' => ['required', 'boolean'],
            'job_requirements_and_scope' => ['required', 'string'],
            'contact_info' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'service_id.required_without' => 'يجب اختيار خدمة أو فئة (تصنيف) على الأقل.',
            'category_id.required_without' => 'يجب اختيار فئة (تصنيف) أو خدمة على الأقل.',
            'service_id.exists' => 'الخدمة المختارة غير مسجَّلة لدى شركتك.',
            'category_id.exists' => 'الفئة المختارة غير مسجَّلة ضمن فئات شركتك.',
            'application_deadline.before_or_equal' => 'يجب أن يكون آخر موعد للتقديم قبل أو يساوي تاريخ بدء العمل.',
        ];
    }
}