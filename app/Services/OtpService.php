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
        $baseUrl    = rtrim(config('services.ultramsg.base_url', 'https://api.ultramsg.com'), '/');
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
        $attemptsKey = 'otp_attempts_' . $phone;
        $attempts    = Cache::get($attemptsKey, 0);

        // حظر توليد كود جديد إذا تجاوزت المحاولات 3 مرات
        if ($attempts >= 3) {
            throw new Exception('تجاوزت الحد المسموح لتوليد الأكواد، انتظر 15 دقيقة.');
        }

        Cache::put($attemptsKey, $attempts + 1, now()->addMinutes(15));

        // توليد كود آمن مكون من 6 أرقام
        $code = random_int(100000, 999999);
        
        // تخزين الكود الفعلي في الكاش بمفتاح مستقل
        Cache::put('otp_' . $phone, $code, now()->addMinutes(10));

        Log::info("Generated OTP for {$phone}: {$code}");

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