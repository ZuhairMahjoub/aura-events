<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes - Aura Events Project (Professional Auth System)
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    
    Route::post('/register', [AuthController::class, 'store']); 
    
    Route::post('/login', [AuthController::class, 'login']);
    
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle:verify-otp');

    Route::get('/google/redirect', [AuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [AuthController::class, 'handleGoogleCallBack']);
    Route::post('/google/mobile-login', [AuthController::class, 'handleGoogleMobileLogin']);
});

Route::middleware(['auth:sanctum'])->group(function () {

    /**
     */
    Route::middleware('abilities:issue-access-token')->group(function () {
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    });

    /**
     */
    Route::middleware('abilities:access-api')->group(function () {
        
        Route::get('/user', function (Request $request) {
            return $request->user();
        });

        // تسجيل الخروج (إتلاف التوكن الحالي)
        Route::post('/logout', [AuthController::class, 'logout']);

    
    });
});