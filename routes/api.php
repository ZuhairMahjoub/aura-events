<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\ArrangementController;
use App\Http\Controllers\AdminListingController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProviderAuthController;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureAccountIsVerified;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\DistrictsController;
use App\Http\Controllers\JobOfferController;
use App\Http\Controllers\TempUploadController;
use App\Http\Controllers\AdminProviderController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizerController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\CompanyDetailController;


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
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
        Route::post('/logout', [AuthController::class, 'logout']);
         Route::get('/auth/profile', [AuthController::class, 'getProfile']);
    });
});

Route::post('forgot-password', [PasswordResetLinkController::class, 'store']);
Route::post('verify-otp', [NewPasswordController::class, 'verifyOtp']);
Route::post('reset-password', [NewPasswordController::class, 'store']);
Route::get('/admin/users', [AuthController::class, 'getFilteredUsers']);

Route::post('/otp/resend', [AuthController::class, 'resendOtp']);
Route::post('/auth/verify-email-otp', [EmailVerificationNotificationController::class, 'verifyOtp']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/districts', [DistrictsController::class, 'index']);


Route::middleware(['auth:sanctum', EnsureAccountIsVerified::class])->group(function () {
    Route::post('/provider/complete-profile', [ProviderAuthController::class, 'store']);
});


Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/uploads/temp', [TempUploadController::class, 'upload']);
    Route::get('listings/{listingId}/images', [TempUploadController::class, 'index']);


    Route::prefix('listings')->group(function () {

        Route::get('/', [ListingController::class, 'index'])->middleware('permission:view listings');
        Route::get('/{listing}', [ListingController::class, 'show'])->middleware('permission:view listings');
        Route::get('/provider/my-services', [ListingController::class, 'getCompanyServices']);

        Route::middleware(['approved_provider'])->group(function () {
            Route::post('/', [ListingController::class, 'store'])->middleware('permission:create listings');
            Route::put('/{listing}', [ListingController::class, 'update'])->middleware('permission:update listings');
            Route::delete('/{listing}', [ListingController::class, 'destroy'])->middleware('permission:delete listings');
        });
    });
    Route::middleware('approved_provider')->group(function () {
        Route::post('/job-offers', [JobOfferController::class, 'store']);
        Route::get('/company/applicants', [JobOfferController::class, 'getApplicants']);
        Route::put('/contracts/{id}/status', [JobOfferController::class, 'updateApplicantStatus']);

        Route::post('/job-offers/{id}/apply', [JobOfferController::class, 'apply']);
        Route::get('/provider/inventory', [ListingController::class, 'getCompanyInventory']);


        Route::prefix('arrangements')->group(function () {
            Route::post('/', [ArrangementController::class, 'store'])->middleware('throttle:10,1');
            Route::get('/{arrangementId}', [ArrangementController::class, 'show']);
            Route::put('/{arrangementId}', [ArrangementController::class, 'update']);
            Route::get('my-products', [ArrangementController::class, 'getMyProducts']);
            Route::get('my-all-products', [ArrangementController::class, 'getMyAllProducts']);
            Route::get('/provider/my-arrangements', [ArrangementController::class, 'getMyPackages']); // API جديد
        });

        // Route::get('/provider/my-products', [ArrangementController::class, 'getMyProducts']);
        Route::get('provider/my-products', [ListingController::class, 'getCompanyProducts']);
        Route::get('/provider/available-freelancers', [ArrangementController::class, 'getFreelancersList']);
        Route::get('/job-offers', [JobOfferController::class, 'index']);
    });
});
Route::middleware('auth:sanctum')->group(function () {

    Route::get('book/{id}', [BookingController::class, 'show']);        // عرض تفاصيل حجز محدد (للطرفين)
    Route::post('/bookings', [BookingController::class, 'store']);

    Route::put('/bookings/{bookingId}/cancel', [BookingController::class, 'cancel']);

    Route::get('/bookings', [BookingController::class, 'myBookings']); // عرض قائمة الحجوزات
    Route::get('/provider/bookings', [BookingController::class, 'providerBookings']); // عرض قائمة الحجوزات

    Route::put('/bookings/{bookingId}/accept', [BookingController::class, 'accept']);
    Route::put('/bookings/{bookingId}/reject', [BookingController::class, 'reject']);
    Route::put('/bookings/{bookingId}/complete', [BookingController::class, 'complete']);
    Route::post('/bookings', [BookingController::class, 'store']);

    Route::post('/bookings/{bookingId}/cancel', [BookingController::class, 'cancel']);

    Route::get('/bookings', [BookingController::class, 'index']); 
    Route::put('/bookings/{bookingId}/accept', [BookingController::class, 'accept'])
        ->middleware('approved_provider');
});



Route::middleware(['auth:sanctum', 'is_admin'])->prefix('admin')->group(function () {

    Route::put('/providers/{id}/approve', [AdminProviderController::class, 'approve']);
    Route::put('/providers/{id}/reject', [AdminProviderController::class, 'reject']);
    Route::put('/listings/{id}/approve', [AdminListingController::class, 'approve']);
    Route::put('/listings/{id}/reject', [AdminListingController::class, 'reject']);
    Route::get('/providers/{id}', [AdminProviderController::class, 'showProvider']);
    Route::get('/Organzier/{id}', [AdminProviderController::class, 'getUserDetails']);
});

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/notifications', [NotificationController::class, 'all']);

    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

    Route::post('/notifications/test', [NotificationController::class, 'sendTestNotification']);
    Route::post('/device-token', [NotificationController::class, 'updateToken']);
});


Route::middleware(['auth:sanctum', 'approved_provider'])->group(function () {
    Route::get('/provider/profile', [ProviderController::class, 'profile']);

    Route::get('/admin/providers', [ProviderController::class, 'index']);
});
Route::middleware('auth:sanctum')->prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'show']);
    Route::post('/items', [CartController::class, 'addItem']);
    Route::patch('/items/{cartItemId}', [CartController::class, 'updateQuantity']);
    Route::delete('/items/{cartItemId}', [CartController::class, 'removeItem']);
    Route::post('/checkout', [CartController::class, 'checkout']);
});
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/chat/initialize', [ChatController::class, 'initializeChat']);
});
Route::get('/providers', [ProviderController::class, 'getProviders']);
