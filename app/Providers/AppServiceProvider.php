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
use Illuminate\Support\Facades\Route;
use App\Services\FirebaseNotificationService;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
{
    $this->app->singleton(Messaging::class, function ($app) {
        $credentials = config('firebase.projects.app.credentials');
        
        return (new Factory())
            ->withServiceAccount($credentials)
            ->createMessaging();
    });

    $this->app->singleton(FirebaseNotificationService::class, function ($app) {
        return new FirebaseNotificationService($app->make(Messaging::class));
    });
}
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
        Route::aliasMiddleware('is_admin', \App\Http\Middleware\EnsureUserIsAdmin::class);
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}