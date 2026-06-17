<?php

namespace App\Providers;

use App\Events\UserRegistered;
use App\Listeners\SendEmailVerification;
use App\Listeners\SendOtpNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
use Kreait\Laravel\Firebase\Facades\Firebase;

class AppServiceProvider extends ServiceProvider
{// app/Providers/AppServiceProvider.php
// AppServiceProvider.php
public function register(): void
{
    $this->app->singleton(Messaging::class, function () {
        $credentialsPath = storage_path('app/firebase/royal-event-app-firebase-adminsdk-fbsvc-02740649a6.json');
        
        if (!file_exists($credentialsPath)) {
            return null; // بدل throw
        }
        
        return (new \Kreait\Firebase\Factory)
            ->withServiceAccount($credentialsPath)
            ->createMessaging();
    });
}

    public function boot(): void
    {
        Event::forget(\Illuminate\Auth\Events\Registered::class);

        Event::listen(UserRegistered::class, SendEmailVerification::class);
        Event::listen(UserRegistered::class, SendOtpNotification::class);

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        RateLimiter::for('verify-otp', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('identity') ?: $request->ip());
        });

        Route::aliasMiddleware('is_admin', \App\Http\Middleware\EnsureUserIsAdmin::class);
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}