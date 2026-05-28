<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مخولاً لإجراء هذا الطلب.
     */
    public function authorize(): bool
    {
        // يجب تغييرها إلى true للسماح لعملية التسجيل بالمرور
        return true;
    }

    /**
     * قواعد التحقق من البيانات.
     */
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            // ملاحظة: نتحقق من فرادة الرقم في جدول المستخدمين 
            // لضمان عدم حجز رقم مسجل مسبقاً في الكاش
            'phone'      => 'required|string|unique:users,phone|regex:/^([0-9\s\-\+\(\)]*)$/|min:10',
            'password'   => 'required|min:8|confirmed',
            // 'city_id'    => 'required|exists:cities,id',
        ];
    }

    /**
     * تخصيص رسائل الخطأ (اختياري لتحسين تجربة المستخدم)
     */
    public function messages(): array
    {
        return [
            'phone.unique' => 'هذا الرقم مسجل لدينا بالفعل، يرجى تسجيل الدخول.',
            'phone.regex'  => 'صيغة رقم الهاتف غير صحيحة.',
        ];
    }
}