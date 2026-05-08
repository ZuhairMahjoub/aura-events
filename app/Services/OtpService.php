<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OtpService
{
    /**
     * تنظيف وتوحيد صيغة رقم الهاتف.
     */
    public function formatPhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }

    /**
     * إرسال رمز التحقق عبر WhatsApp باستخدام UltraMsg.
     */
    public function sendViaWhatsapp(string $phone, string $code): bool
    {
        $formattedPhone = $this->formatPhone($phone);

        $instanceId = env('ULTRAMSG_INSTANCE_ID');
        $token = env('ULTRAMSG_TOKEN');
        $baseUrl = rtrim(env('ULTRAMSG_BASE_URL', 'https://api.ultramsg.com'), '/');

        $url = "{$baseUrl}/{$instanceId}/messages/chat";

        try {
            $response = Http::withOptions(['verify' => false])
                ->timeout(30)
                ->asForm()
                ->post($url, [
                    'token' => $token,
                    'to'    => $formattedPhone,
                    'body'  => "كود التحقق الخاص بك لمشروع Aura Events هو: {$code}",
                ]);

            if ($response->successful()) {
                Log::info("OTP sent successfully to {$formattedPhone}");
                return true;
            }

            Log::error("UltraMsg API error: " . $response->body());
            return false;

        } catch (\Exception $e) {
            Log::error("WhatsApp connection failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * توليد رمز عشوائي وتخزينه.
     */
    public function generateForPhone(string $phone): int
    {
        $formattedPhone = $this->formatPhone($phone);
        $code = rand(100000, 999999);
        
        Cache::put('otp_' . $formattedPhone, $code, now()->addMinutes(10));
        
        return $code;
    }

    /**
     * التحقق من صحة الكود.
     */
    public function verifyOtp(string $phone, string $code): bool
    {
        $formattedPhone = $this->formatPhone($phone);
        $cacheKey = 'otp_' . $formattedPhone;

        $storedCode = Cache::get($cacheKey);
        
        // استخدام == مع تحويل النوع لضمان المطابقة حتى لو أرسل الفرونت آند string
        if ($storedCode && (string)$storedCode === (string)$code) {
            Cache::forget($cacheKey);
            return true;
        }

        return false;
    }
}