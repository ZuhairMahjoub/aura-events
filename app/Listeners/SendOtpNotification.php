<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\OtpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendOtpNotification implements ShouldQueue
{
    use InteractsWithQueue;
public $tries = 1;
    protected OtpService $otpService;

    /**
     * حقن خدمة الـ OTP
     */
    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     */
    public function handle(UserRegistered $event): void
    {
        if ($event->user->phone) {

            $phone = $event->user->phone;

            $code = $this->otpService->generateForPhone($phone);

            $this->otpService->sendViaWhatsapp($phone, $code);
        }
    }
}