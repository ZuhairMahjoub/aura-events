<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\OtpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerifyEmailOtpMail;

class SendEmailVerification implements ShouldQueue
{
    use InteractsWithQueue;

    public $tries = 3; 
    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    public function handle(UserRegistered $event): void
    {
        $user = $event->user;
        
        if (!$user->email) {
            return;
        }

        $sentCacheKey = 'otp_sent_' . $user->email;
        if (Cache::has($sentCacheKey)) {
            return;
        }

        $otp = $this->otpService->generateOtp($user->email);
        
        Cache::put($sentCacheKey, true, now()->addSeconds(30));

        Mail::to($user->email)->send(new VerifyEmailOtpMail($otp));
    }
}