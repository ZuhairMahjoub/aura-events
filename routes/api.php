<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProviderAuthController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Middleware\EnsureAccountIsVerified;
use App\Http\Middleware\EnsureProfileIsCompleted;

Route::prefix('auth')->group(function () {

Route::post('/register/organizer', [AuthController::class, 'registerOrganizer'])->middleware('check.account.exists');
Route::post('/register/provider',  [AuthController::class, 'registerProvider'])->middleware('check.account.exists');

    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::get('/google/redirect', [AuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [AuthController::class, 'handleGoogleCallBack']);
    Route::post('/google/mobile-login', [AuthController::class, 'handleGoogleMobileLogin']);
});

Route::post('/reset-password', [NewPasswordController::class, 'store']);


Route::middleware(['auth:sanctum'])->group(function () {

    Route::prefix('auth')->group(function () {
Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])
    ->middleware(['ability:verify-account', 'throttle:5,1']);

Route::post('/resend-otp', [AuthController::class, 'resendOtp'])
    ->middleware(['ability:verify-account', 'throttle:3,1']);

        Route::post('/refresh', [AuthController::class, 'refresh'])
            ->middleware('ability:issue-access-token');

        Route::post('/logout', [AuthController::class, 'logout']);
    });

    // جلب بيانات المستخدم الحالي
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    /*
    |-- روابط تشترط أن يكون الحساب مؤكداً (Account Is Verified)
    */
    Route::middleware([EnsureAccountIsVerified::class])->group(function () {
        
        // 1. رابط إكمال الملف الشخصي (يجب أن يكون خارج ميدل وير الفحص لإتاحة الدخول إليه)
        Route::post('/provider/complete-profile', [ProviderAuthController::class, 'store']);

        /*
        |-- روابط تشترط أن يكون الملف الشخصي مكتملاً (Profile Is Completed)
        */
        Route::middleware([EnsureProfileIsCompleted::class])->group(function () {
            // أضف هنا روابط النظام التي لا تفتح إلا بعد إكمال البروفايل بالكامل
            // مثال: Route::get('/dashboard', [DashboardController::class, 'index']);
        });

    });
});