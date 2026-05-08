<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\OtpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendOtpNotification
{
    use InteractsWithQueue;

    protected OtpService $otpService;

    /**
     * إنشاء الـ Listener وحقن خدمة الـ OTP
     */
    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * معالجة الحدث وإرسال الرمز
     */
    public function handle(UserRegistered $event)
    {
        // التأكد أن القناة هي الهاتف وأن المستخدم لديه رقم هاتف فعلاً
        if ($event->channel === 'phone' && $event->user->phone) {

            // تأكد من تنظيف الرقم قبل التعامل معه (تنسيق دولي)
            // إذا كانت الدالة في الـ OtpService استخدمها مباشرة
            $phone = $event->user->phone;

            // 1. توليد الرمز وتخزينه
            $code = $this->otpService->generateForPhone($phone);

            // 2. إرسال الرمز عبر الواتساب
            $this->otpService->sendViaWhatsapp($phone, $code);
        }
    }
}
