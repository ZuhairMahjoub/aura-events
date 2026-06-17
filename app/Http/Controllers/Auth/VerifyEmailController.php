<?php

namespace App\Http\Controllers\Auth;

use App\Events\UserRegistered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerifyEmailOtpMail; // سنقوم بإنشائه في الخطوة التالية

class VerifyEmailController implements ShouldQueue
{
    use InteractsWithQueue;

    // public $tries = 3;      
    // public $backoff = 10;

    public function __construct()
    {
        //
    }

    public function handle(UserRegistered $event): void
    {
        Log::info('--- بدأت عملية توليد وإرسال كود OTP للمستخدم: ' . $event->user->email);
        
        $user = $event->user;

        if (!$user->email) {
            return;
        }

        $otp = rand(100000, 999999);

        $cacheKey = 'otp_email_' . $user->email;
        Cache::put($cacheKey, $otp, now()->addMinutes(10));

        Mail::to($user->email)->send(new VerifyEmailOtpMail($otp));
    }
}