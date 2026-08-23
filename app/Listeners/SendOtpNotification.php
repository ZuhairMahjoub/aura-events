<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\OtpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

class SendOtpNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public $tries = 2; 
    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    public function handle(UserRegistered $event): void
    {
        $user = $event->user;

        if (!$user->phone) {
            return;
        }

        $sentCacheKey = 'otp_sent_' . $user->phone;
        if (Cache::has($sentCacheKey)) {
            return;
        }

        $code = $this->otpService->generateOtp($user->phone);

        Cache::put($sentCacheKey, true, now()->addSeconds(30));

        $this->otpService->sendViaWhatsapp($user->phone, $code);
    }
}