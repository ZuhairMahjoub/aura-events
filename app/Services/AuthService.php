<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthService
{
    /**
     * إنشاء مستخدم جديد في قاعدة البيانات باستخدام ULID.
     */
    public function createUser(array $data): User
    {
        return User::create([
            'id'                => (string) Str::ulid(), // استخدام ULID كـ Primary Key
            'first_name'        => $data['first_name'],
            'last_name'         => $data['last_name'],
            'email'             => $data['email'] ?? null, // مرونة لإضافة الإيميل لاحقاً
            'phone'             => $data['phone'], // الحقل الفريد للـ OTP حالياً
            'password'          => Hash::make($data['password']),
            // 'city_id'           => $data['city_id'],
            'settings_language' => $data['settings_language'] ?? 'ar',
            'settings_theme'    => $data['settings_theme'] ?? 'light',
        ]);
    }

    public function formatPhone(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }
    
   public function login(array $data)
{
    // تنظيف الرقم القادم من الطلب لضمان أنه بصيغة أرقام فقط
    $cleanPhone = preg_replace('/\D/', '', $data['phone']); 

    // البحث عن الرقم سواء كان مخزناً بـ + أو بدونها في قاعدة البيانات
    $user = User::where('phone', $data['phone']) // البحث كما جاء من Postman
                ->orWhere('phone', $cleanPhone)  // البحث بدون الزائد
                ->first();

    if (!$user || !Hash::check($data['password'], $user->password)) {
        return null; 
    }

    // الشغل الصح: حذف التوكنات القديمة
    $user->tokens()->delete();

    $token = $user->createToken('mobile_token', ['*'], now()->addMonth())->plainTextToken;

    return [
        'user'  => $user,
        'token' => $token
    ];
}
}