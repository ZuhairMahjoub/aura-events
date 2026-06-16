<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class OtpService
{
    protected string $instanceId;
    protected string $token;
    protected string $baseUrl;
    protected int $ttlMinutes = 10; 

    public function __construct()
    {
        $this->instanceId = config('services.ultramsg.instance_id', '');
        $this->token      = config('services.ultramsg.token', '');
        $this->baseUrl    = rtrim(config('services.ultramsg.base_url', 'https://api.ultramsg.com'), '/');
    }

    
    public function formatPhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }

   
    public function getCacheKey(string $identity): string
    {
        $isEmail = filter_var($identity, FILTER_VALIDATE_EMAIL);
        $cleanIdentity = !$isEmail ? $this->formatPhone($identity) : $identity;
        $prefix = $isEmail ? 'otp_email_' : 'otp_phone_';

        return $prefix . $cleanIdentity;
    }


    public function generateOtp(string $identity): string
    {        $code = (string) rand(100000, 999999);
        $cacheKey = $this->getCacheKey($identity);

        Cache::put($cacheKey, $code, now()->addMinutes($this->ttlMinutes));

        return $code;
    }

    
    public function verifyOtp(string $identity, string $code): bool
    {
        $cacheKey = $this->getCacheKey($identity);
        $storedCode = Cache::get($cacheKey);

        if ($storedCode && hash_equals((string) $storedCode, (string) $code)) {
            Cache::forget($cacheKey);
            return true;
        }

        return false;
    }

    
    public function sendViaWhatsapp(string $phone, string $code): bool
    {
        $formattedPhone = $this->formatPhone($phone);
        $url = "{$this->baseUrl}/{$this->instanceId}/messages/chat";

        try {
            $response = Http::withOptions(['verify' => false]) 
                ->timeout(15) 
                ->asForm()
                ->post($url, [
                    'token' => $this->token,
                    'to'    => $formattedPhone,
                    'body'  => "كود التحقق الخاص بك لمشروع Aura Events هو: {$code}. صلاحية الكود {$this->ttlMinutes} دقائق.",
                ]);

            if (!$response->successful()) {
                Log::error("WhatsApp API Error: Status {$response->status()} | Response: " . $response->body());
                return false;
            }

            return true;

        } catch (Exception $e) {
            Log::error("WhatsApp Connection Failed: " . $e->getMessage());
            return false;
        }
    }
}