<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OtpService
{
    public function formatPhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }

    public function sendViaWhatsapp(string $phone, string $code): bool
    {
        $formattedPhone = $this->formatPhone($phone);
        $instanceId = config('services.ultramsg.instance_id');
        $token      = config('services.ultramsg.token');
        $baseUrl    = rtrim(config('services.ultramsg.base_url', 'https://api.ultramsg.com'), '/');

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

            return $response->successful();

        } catch (\Exception $e) {
            Log::error("WhatsApp connection failed: " . $e->getMessage());
            return false;
        }
    }

    public function generateForPhone(string $phone): int
    {
        $formattedPhone = $this->formatPhone($phone);
        $code = rand(100000, 999999);
        
        Cache::put('otp_' . $formattedPhone, $code, now()->addMinutes(10));
        
        return $code;
    }

    /**
     */
    public function verifyOtp(string $phone, string $code): bool
    {
        $formattedPhone = $this->formatPhone($phone);
        $cacheKey = 'otp_' . $formattedPhone;

        $storedCode = Cache::get($cacheKey);
        
        if ($storedCode && (string)$storedCode === (string)$code) {
            Cache::forget($cacheKey); 
            return true;
        }

        return false;
    }
}