<?php
namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\OtpService;
use Illuminate\Contracts\Queue\ShouldQueue; // استيراد الـ Queue
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendOtpNotification implements ShouldQueue // إضافة التوجيه للـ Queue
{
    use InteractsWithQueue;

    protected $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }


    public function handle(UserRegistered $event): void
    {
        $user = $event->user;

        if ($user->phone) {
            try {
                $phone =  $user->phone;

                $code = $this->otpService->generateForPhone($phone);

                // عند إرسال الـ OTP عبر الواتساب

                $this->otpService->sendViaWhatsapp($phone, $code);

                Log::info("OTP sent successfully to: {$phone}");

            } catch (\Exception $e) {
                // تسجيل الخطأ في حال فشل الإرسال لضمان عدم توقف النظام
                Log::error("Failed to send OTP to {$user->phone}: " . $e->getMessage());
                
                // يمكنك هنا إعادة المحاولة (Retry) إذا أردت
            }
        }
    }
}