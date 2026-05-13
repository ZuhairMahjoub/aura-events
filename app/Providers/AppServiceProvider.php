<?php

namespace App\Providers;

use App\Events\UserRegistered;
use App\Listeners\SendOtpNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
// استدعاء الكلاسات الناقصة هنا 🌟
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // تخصيص رابط إعادة تعيين كلمة المرور
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        // تعريف محدد السرعة للـ OTP 🛡️
        RateLimiter::for('verify-otp', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('phone') ?: $request->ip());
        });
    }
}