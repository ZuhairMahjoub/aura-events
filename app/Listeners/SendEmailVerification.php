<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerifyEmailOtpMail;

class SendEmailVerification implements ShouldQueue
{
    use InteractsWithQueue;

    // public $tries = 1; 

    public function handle(UserRegistered $event): void
    {
        $user = $event->user;
        if (!$user->email) return;

        $sentCacheKey = 'otp_sent_' . $user->email;
        if (Cache::has($sentCacheKey)) return;

        $otp = rand(100000, 999999);
        Cache::put('otp_email_' . $user->email, $otp, now()->addMinutes(10));
        Cache::put($sentCacheKey, true, now()->addSeconds(30));

        Mail::to($user->email)->send(new VerifyEmailOtpMail($otp));
    }
}