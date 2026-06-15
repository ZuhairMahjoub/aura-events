<?php

use App\Http\Controllers\AdminListingController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DistrictController;
use App\Http\Controllers\ProviderAuthController;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureAccountIsVerified;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\AdminProviderController; 
use App\Http\Controllers\Api\NotificationController;


Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'store']); 
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:verify-otp');

    Route::get('/google/redirect', [AuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [AuthController::class, 'handleGoogleCallBack']);
    Route::post('/google/mobile-login', [AuthController::class, 'handleGoogleMobileLogin']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::middleware('abilities:issue-access-token')->group(function () {
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    });

    Route::middleware('abilities:access-api')->group(function () {
        Route::get('/user', function (Request $request) { return $request->user(); });
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::post('forgot-password', [PasswordResetLinkController::class, 'store']);
Route::post('verify-otp', [NewPasswordController::class, 'verifyOtp']);
Route::post('reset-password', [NewPasswordController::class, 'store']);

Route::post('/otp/resend', [AuthController::class, 'resendOtp']);
Route::post('/auth/verify-email-otp', [EmailVerificationNotificationController::class, 'verifyOtp']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/districts', [DistrictController::class, 'index']);

    
Route::middleware(['auth:sanctum', EnsureAccountIsVerified::class])->group(function () {
    Route::post('/provider/complete-profile', [ProviderAuthController::class, 'store']);
    Route::get('/providers/{id}', [ProviderAuthController::class, 'showProvider']);});

Route::middleware(['auth:sanctum'])->group(function () {
    
    Route::prefix('listings')->group(function () {
        Route::get('/', [ListingController::class, 'index'])->middleware('permission:view listings');
        Route::get('/{listing}', [ListingController::class, 'show'])->middleware('permission:view listings');

        Route::middleware(['approved_provider'])->group(function () {
            Route::post('/', [ListingController::class, 'store'])->middleware('permission:create listings');
            Route::put('/{listing}', [ListingController::class, 'update'])->middleware('permission:update listings');
            Route::delete('/{listing}', [ListingController::class, 'destroy'])->middleware('permission:delete listings');
        });
    }); 
}); 
Route::middleware(['auth:sanctum', 'is_admin'])->prefix('admin')->group(function () {
    
    Route::put('/providers/{id}/approve', [AdminProviderController::class, 'approve']);
    Route::put('/providers/{id}/reject', [AdminProviderController::class, 'reject']);
    Route::put('/listings/{id}/approve', [AdminListingController::class, 'approve']);
    Route::put('/listings/{id}/reject', [AdminListingController::class, 'reject']);

});
Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/notifications', [NotificationController::class, 'all']);
    
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    
    Route::post('/notifications/test', [NotificationController::class, 'sendTestNotification']);
    Route::post('/device-token', [NotificationController::class, 'updateToken']);
    
});