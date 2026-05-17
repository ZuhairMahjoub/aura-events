<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        if ($this->has('identity')) {
            $identity = trim($this->identity);

            if (is_numeric(str_replace(['+', '-', ' ', '(', ')'], '', $identity))) {
                $cleaned = preg_replace('/\s+|-|\(|\)/', '', $identity);

                // إذا كان الرقم 9 خانات ويبدأ بـ 9 فهو جاهز
                if (strlen($cleaned) === 9 && str_starts_with($cleaned, '9')) {
                    $identity = $cleaned;
                } else {
                    // تجريد الزوائد الدولية
                    if (str_starts_with($cleaned, '+963')) {
                        $cleaned = substr($cleaned, 4);
                    } elseif (str_starts_with($cleaned, '00963')) {
                        $cleaned = substr($cleaned, 5);
                    } elseif (str_starts_with($cleaned, '963')) {
                        $cleaned = substr($cleaned, 3);
                    } elseif (str_starts_with($cleaned, '09')) {
                        $cleaned = substr($cleaned, 1);
                    }

                    if (strlen($cleaned) === 9 && str_starts_with($cleaned, '9')) {
                        $identity = $cleaned;
                    }
                }
            }

            $this->merge([
                'identity' => $identity,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'identity'   => [
                'required',
                function ($attribute, $value, $fail) {
                    // التحقق من الصيغة إذا كان المدخل رقم هاتف تالف
                    if (is_numeric(str_replace('+', '', $value)) && (strlen($value) !== 9 || !str_starts_with($value, '9'))) {
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            return $fail('صيغة رقم الهاتف غير صحيحة، يجب أن يتكون الرقم من 9 خانات ويبدأ بـ 9.');
                        }
                    }

                    // فحص إذا كان المستخدم موجود ومفعّل مسبقاً
                    $user = User::where('phone', $value)
                        ->orWhere('email', $value)
                        ->first();

                    if ($user && ($user->phone_verified_at !== null || $user->email_verified_at !== null)) {
                        $fail('هذا الحساب مسجل ومفعل لدينا بالفعل، يرجى تسجيل الدخول.');
                    }
                },
            ],
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'identity.required'  => 'حقل الهاتف أو البريد الإلكتروني مطلوب.',
            'password.required'  => 'حقل كلمة المرور مطلوب.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
        ];
    }
}