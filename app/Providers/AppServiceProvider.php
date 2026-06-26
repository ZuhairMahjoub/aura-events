<?php

namespace App\Providers;
use Kreait\Firebase\Contract\Firestore;
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

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Messaging::class, function ($app) {
            $credentials = env('FIREBASE_CREDENTIALS', 'storage/app/firebase/royal-event-app-firebase-adminsdk-fbsvc-02740649a6.json');
            
            $fullPath = is_array($credentials) ? $credentials : base_path($credentials);

            if (!is_array($fullPath) && !file_exists($fullPath)) {
                throw new \Exception("Firebase credentials file not found at: " . $fullPath);
            }

            return (new Factory)->withServiceAccount($fullPath)->createMessaging();
        });
     $this->app->singleton(\App\Services\FirestoreService::class, function ($app) {
        return new \App\Services\FirestoreService();
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