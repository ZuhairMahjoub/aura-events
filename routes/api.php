<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProviderAuthController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Middleware\EnsureAccountIsVerified;
use App\Http\Middleware\EnsureProfileIsCompleted;

Route::prefix('auth')->group(function () {
    Route::post('/register/organizer', [AuthController::class, 'registerOrganizer']);
    Route::post('/register/provider',  [AuthController::class, 'registerProvider']);

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');

    Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::get('/google/redirect', [AuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [AuthController::class, 'handleGoogleCallBack']);
    Route::post('/google/mobile-login', [AuthController::class, 'handleGoogleMobileLogin']);
});

Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp'])
        ->middleware(['ability:verify-account', 'throttle:verify-otp']);

    Route::post('/auth/resend-otp', [AuthController::class, 'resendOtp'])
        ->middleware(['ability:verify-account', 'throttle:5,1']); // يسمح بـ 5 طلبات كحد أقصى في الدقيقة لحماية السيرفر

    Route::post('/auth/refresh', [AuthController::class, 'refresh'])
        ->middleware('ability:issue-access-token');

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::middleware([EnsureAccountIsVerified::class])->group(function () {
        
        Route::middleware([EnsureProfileIsCompleted::class])->group(function () {
            Route::post('/provider/complete-profile', [ProviderAuthController::class, 'store']);
        });
        
    });
});