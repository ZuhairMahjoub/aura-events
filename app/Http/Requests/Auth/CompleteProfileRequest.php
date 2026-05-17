<?php

namespace App\Http\Requests\Auth; // تأكد من المسار حسب مجلداتك

use Illuminate\Foundation\Http\FormRequest;

class CompleteProfileRequest extends FormRequest
{
    /**
     * السماح لليوزر المسجل فقط بإرسال الطلب
     */
    public function authorize(): bool
    {
        return true; // غيرها لـ true عشان يشتغل الريكويست
    }

    /**
     * قواعد التحقق الذكية
     */
  public function rules(): array
{
    return [
    

        // بيانات مشتركة للطرفين
        'brand_name' => 'required|string|unique:providers,brand_name',
        'district_id'   => 'required|exists:districts,id',
        'provider_type' => 'required|in:freelancer,company', 

        // --- بيانات الشركة (تصبح مطلوبة فقط إذا كان النوع company) ---
        'tax_number' => [
            'required_if:provider_type,company', 
            'nullable', 
            'string', 
            'unique:company_details,tax_number'
        ],
        'registration_no' => [
            'required_if:provider_type,company', 
            'nullable', 
            'string'
        ],

        // --- بيانات الفريلانسر (تصبح مطلوبة فقط إذا كان النوع freelancer) ---
        'national_id' => [
            'required_if:provider_type,freelancer', 
            'nullable', 
            'string', 
            'unique:freelancer_details,national_id'
        ],
    ];
}
    /**
     * رسائل خطأ مخصصة (اختياري لليوزر إكسبيرينس)
     */
    public function messages(): array
{
    return [
        'user_id.required'        => 'معرّف المستخدم مطلوب.',
        'user_id.exists'          => 'المستخدم المحدد غير موجود في النظام.',
        'user_id.unique'          => 'عذراً، هذا الحساب يمتلك ملف مزود خدمة مفعّل مسبقاً ولا يمكن تكراره.', // 🌟 القفل
        'tax_number.required_if'  => 'الرقم الضريبي مطلوب للشركات.',
        'national_id.required_if' => 'رقم الهوية مطلوب للأفراد (Freelancer).',
    ];
}
}