<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OtpService
{
    public function sendViaWhatsapp(string $phone, string $code): bool
    {
        $instanceId = config('services.ultramsg.instance_id');
        $token      = config('services.ultramsg.token');
        $baseUrl    = rtrim((string) config('services.ultramsg.base_url', 'https://api.ultramsg.com'), '/');
        $url        = "{$baseUrl}/{$instanceId}/messages/chat";
        $completePhone = '963' . $phone; // تأكد من إضافة رمز الدولة
        try {
            $response = Http::withOptions(['verify' => app()->isProduction()])
                ->timeout(30)
                ->asForm()
                ->post($url, [
                    'token' => $token,
                    'to'    => $completePhone,
                    'body'  => "كود التحقق الخاص بك لمشروع Aura Events هو: {$code}",
                ]);

            return $response->successful();

        } catch (\Exception $e) {
            Log::error("WhatsApp connection failed: " . $e->getMessage());
            return false;
        }
    }

   
   public function generateForPhone(string $phone): int
{
    $lockoutKey  = 'otp_lockout_' . $phone;
    $attemptsKey = 'otp_attempts_' . $phone;

    // 1. التحقق أولاً ما إذا كان رقم الهاتف محظوراً حالياً
    if (Cache::has($lockoutKey)) {
        throw new Exception('تجاوزت الحد المسموح لتوليد الأكواد، انتظر 15 دقيقة.');
    }

    $attempts = Cache::get($attemptsKey, 0);

    // 2. إذا تجاوز المستخدم 3 محاولات، يتم حظره لمدة 15 دقيقة
    if ($attempts >= 3) {
        Cache::put($lockoutKey, true, now()->addMinutes(15));
        Cache::forget($attemptsKey); // تصفير عداد المحاولات ليبدأ نظيفاً بعد انتهاء الحظر
        
        throw new Exception('تجاوزت الحد المسموح لتوليد الأكواد، انتظر 15 دقيقة.');
    }

    // زيادة العداد وجعل صلاحيته 15 دقيقة ليواكب فترة تجميع المحاولات
    Cache::put($attemptsKey, $attempts + 1, now()->addMinutes(15));

    // 3. توليد كود آمن
    $code = random_int(100000, 999999);
    
    // تخزين الكود الفعلي
    Cache::put('otp_' . $phone, $code, now()->addMinutes(10));

    // 4. أمان: لا تقم أبداً بتسجيل الكود الفعلي في الـ Logs!
    Log::info("Generated OTP request for phone: {$phone}");

    return $code;
}

    /**
     * التحقق من صحة الكود الممرر من المستخدم
     */
    public function verifyOtp(string $phone, string $code): bool
    {
        $otpKey      = 'otp_' . $phone;          // 👈 تم التصحيح: جلب مفتاح الكود الفعلي
        $attemptsKey = 'otp_attempts_' . $phone;   // مفتاح عدد محاولات التوليد

        $storedCode = Cache::get($otpKey);

        // مطابقة الكود المخزن مع الكود الممرر بدقة
        if ($storedCode && (string)$storedCode === trim((string)$code)) {
            
            // تصفير وتنظيف الكاش فوراً بعد النجاح لرفع الأمان
            Cache::forget($otpKey);
            Cache::forget($attemptsKey);
            
            return true;
        }

        return false;
    }
}