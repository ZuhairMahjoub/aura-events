<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\AuthController as AuthAuthController; // تأكد من وجود هذا السطر فوق
use App\Http\Controllers\Auth\OtpController; // تأكد من وجود هذا السطر فوق




require __DIR__.'/auth.php'; 


// --- مسارات Breeze الجديدة (Password Reset, Verification, etc) ---
require __DIR__.'/auth.php'; 

// --- مسارات الـ Socialite التي استرجعناها ---
Route::prefix('auth')->group(function () {
    Route::get('/google/redirect', [AuthController::class, 'redirectToGoogle']);
    
    Route::get('/google/callback', [AuthController::class, 'handleGoogleCallBack']);



    Route::post('/google/mobile-login', [AuthController::class, 'handleGoogleMobileLogin']);
});
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
Route::post('/register', [AuthController::class, 'store'])
    ->middleware('guest');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('guest');

// --- مسارات محمية بـ Sanctum ---
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
// Route::post('/register', [AuthController::class, 'store'])
//     ->middleware('guest');

// // مسار تسجيل الدخول (Login) للحصول على الـ Token
// Route::post('/login', [AuthController::class, 'login'])
//     ->middleware('guest');

    // مسار التسجيل الأساسي
Route::post('/register', [AuthAuthController::class, 'register']);
// مسار التحقق من الكود (الذي كتبناه في OtpController)  requestRegistration
Route::post('/verify-otp', [AuthAuthController::class, 'verifyOtp']);
// مسار تسجيل الدخول
Route::post('/login', [AuthAuthController::class, 'login']);
