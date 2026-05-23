<?php

namespace App\Providers;

use App\Events\UserRegistered;
use App\Listeners\SendEmailVerification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::forget(\Illuminate\Auth\Events\Registered::class);

        Event::listen(UserRegistered::class, SendEmailVerification::class);

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        RateLimiter::for('verify-otp', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('phone') ?: $request->ip());
        });
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}