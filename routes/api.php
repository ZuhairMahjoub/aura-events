<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// --- مسارات Breeze الجديدة (Password Reset, Verification, etc) ---
require __DIR__.'/auth.php'; 

// --- مسارات الـ Socialite التي استرجعناها ---
Route::prefix('auth')->group(function () {
    Route::get('/google/redirect', [AuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [AuthController::class, 'handleGoogleCallBack']);
});

// --- مسارات محمية بـ Sanctum ---
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
Route::post('/register', [AuthController::class, 'store'])
    ->middleware('guest');

// مسار تسجيل الدخول (Login) للحصول على الـ Token
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('guest');