<?php

use App\Http\Controllers\PaymentController;
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
use Illuminate\Support\Facades\Storage;
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
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ServiceController;
use App\Models\FreelancerBlockedDate;
use App\Http\Controllers\FreelancerBlockedDateController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\FreelancerDetailController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\AdminBookingController;
use App\Http\Controllers\CompanyBlockedDateController;
use App\Http\Controllers\AdminJobOfferController;

Route::middleware(['set_locale'])->group(function () {

    // ==========================================
    // 1. مسارات الـ Listings العامة (توضع بالأعلى دائماً لمنع اعتراضها)
    // ==========================================
    Route::get('/listings/physical_products', [ListingController::class, 'getProductsOffers']);
    Route::get('/listings/hall', [ListingController::class, 'getHallsOffers']);
    Route::get('/listings/service', [ListingController::class, 'getServicesOffers']);
    Route::get('/listings/package', [ListingController::class, 'getPackagesOffers']);

    Route::prefix('listings')->group(function () {
        Route::get('/', [ListingController::class, 'index']);
        Route::get('/{listing}', [ListingController::class, 'show']);
    });

    // بقية مسارات العرض العام المساعدة
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/districts', [DistrictsController::class, 'index']);
    Route::get('/providers', [ProviderController::class, 'getProviders']);
    Route::get('/job-offers/{id}', [JobOfferController::class, 'show']);


    // ==========================================
    // 2. مسارات المصادقة (Auth)
    // ==========================================
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


    Route::middleware(['auth:sanctum', EnsureAccountIsVerified::class])->group(function () {
        Route::post('/provider/complete-profile', [ProviderAuthController::class, 'store']);
    });


    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/uploads/temp', [TempUploadController::class, 'upload']);
        Route::get('listings/{listingId}/images', [TempUploadController::class, 'index']);

        Route::prefix('listings')->group(function () {
            Route::get('/provider/my-services', [ListingController::class, 'getCompanyServices']);

            Route::middleware(['approved_provider'])->group(function () {
                Route::post('/', [ListingController::class, 'store'])->middleware('permission:create listings');
                Route::put('/{listing}', [ListingController::class, 'update'])->middleware('permission:update listings');
                Route::delete('/{listing}', [ListingController::class, 'destroy'])->middleware('permission:delete listings');
            });
        });

        Route::middleware('approved_provider')->group(function () {
            Route::get('/provider/inventory', [ListingController::class, 'getCompanyInventory']);

            Route::prefix('arrangements')->group(function () {
                Route::post('/', [ArrangementController::class, 'store'])->middleware('throttle:10,1');
                Route::get('/{arrangementId}', [ArrangementController::class, 'show']);
                Route::put('/{arrangementId}', [ArrangementController::class, 'update']);
                Route::delete('/{arrangementId}', [ArrangementController::class, 'destroy']);
                Route::get('my-products', [ArrangementController::class, 'getMyProducts']);
                Route::get('my-all-products', [ArrangementController::class, 'getMyAllProducts']);
                Route::get('/provider/my-arrangements', [ArrangementController::class, 'getMyPackages']);
                Route::get('my-services', [ArrangementController::class, 'getMyServices']);
            });

            Route::get('provider/my-products', [ListingController::class, 'getCompanyProducts']);
            Route::get('/provider/available-freelancers', [ArrangementController::class, 'getFreelancersList']);
            Route::get('/job-offers', [JobOfferController::class, 'index']);
        });

        Route::get('/my-applied-jobs', [JobOfferController::class, 'getAppliedJobs']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('book/{id}', [BookingController::class, 'show']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::put('/bookings/{bookingId}/cancel', [BookingController::class, 'cancel']);
        Route::get('/bookings', [BookingController::class, 'myBookings']);
        Route::get('/provider/bookings', [BookingController::class, 'providerBookings']);
        Route::put('/bookings/{bookingId}/reject', [BookingController::class, 'reject']);
        Route::put('/bookings/{bookingId}/complete', [BookingController::class, 'complete']);
        Route::get('/bookings/{bookingId}/history', [BookingController::class, 'statusHistory']);

        Route::put('/bookings/{bookingId}/accept', [BookingController::class, 'accept'])
            ->middleware('approved_provider');
    });

    Route::middleware(['auth:sanctum', 'is_admin'])->prefix('admin')->group(function () {
        Route::put('/{paymentId}/confirm', [PaymentController::class, 'confirmPayment']);
        Route::put('/{paymentId}/reject', [PaymentController::class, 'rejectPayment']);
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::put('/providers/{id}/approve', [AdminProviderController::class, 'approve']);
        Route::put('/providers/{id}/reject', [AdminProviderController::class, 'reject']);
        Route::put('/listings/{id}/approve', [AdminListingController::class, 'approve']);
        Route::put('/listings/{id}/reject', [AdminListingController::class, 'reject']);
        Route::get('/providers/{id}', [AdminProviderController::class, 'showProvider']);
        Route::get('/Organzier/{id}', [AdminProviderController::class, 'getUserDetails']);
        Route::get('/dashboard-stats', [AdminDashboardController::class, 'stats']);
        Route::get('/bookings', [AdminBookingController::class, 'index']);
        Route::get('/listings/{id}', [AdminListingController::class, 'show']);
        Route::get('/listings/pending', [AdminListingController::class, 'pendingList']);
        Route::get('/bookings/{id}', [AdminBookingController::class, 'show']);
        Route::get('/job-offers/pending', [AdminJobOfferController::class, 'pendingList']);
        Route::put('/job-offers/{id}/approve', [AdminJobOfferController::class, 'approve']);
        Route::put('/job-offers/{id}/reject', [AdminJobOfferController::class, 'reject']);
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
        Route::put('/provider/profile', [ProviderController::class, 'update']);
    });
    Route::get('/providers/{id}', [ProviderController::class, 'show']);
    Route::middleware('auth:sanctum')->prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'show']);
        Route::post('/items', [CartController::class, 'addItem']);
        Route::patch('/items/{cartItemId}', [CartController::class, 'updateQuantity']);
        Route::delete('/items/{cartItemId}', [CartController::class, 'removeItem']);
        Route::post('/checkout', [CartController::class, 'checkout']);
        Route::delete('/cart', [CartController::class, 'clear']);
        Route::get('/cart/count', [CartController::class, 'itemsCount']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/chat/initialize', [ChatController::class, 'initializeChat']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/favorites/{listingId}/toggle', [FavoriteController::class, 'toggle']);
        Route::get('/favorites', [FavoriteController::class, 'index']);
        Route::post('/reviews/provider', [ReviewController::class, 'reviewProvider']);
        Route::post('/reviews/organizer', [ReviewController::class, 'reviewOrganizer']);
        Route::get('/providers/{providerId}/reviews', [ReviewController::class, 'providerReviews']);
        Route::put('/reviews/{id}', [ReviewController::class, 'update']);
        Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
        Route::get('/bookings/{bookingId}/reviews', [ReviewController::class, 'bookingReviews']);
    });

    Route::middleware(['auth:sanctum', 'approved_provider', 'provider_type:company'])->group(function () {
        Route::get('/services', [ServiceController::class, 'index']);
        Route::post('/services', [ServiceController::class, 'store']);
        Route::put('/services/{id}', [ServiceController::class, 'update']);
        Route::delete('/services/{id}', [ServiceController::class, 'destroy']);
        Route::patch('/services/{id}/toggle-active', [ServiceController::class, 'toggleActive']);
    });

    Route::middleware(['auth:sanctum', 'approved_provider', 'provider_type:company'])->group(function () {
        Route::post('/job-offers', [JobOfferController::class, 'store']);
        Route::patch('/job-offers/{id}/toggle-active', [JobOfferController::class, 'toggleActive']);
        Route::get('/company/applicants', [JobOfferController::class, 'getApplicants']);
        Route::put('/contracts/{id}/status', [JobOfferController::class, 'updateApplicantStatus']);
        Route::get('/company/contracts', [ContractController::class, 'companyContracts']);
    });

    Route::middleware(['auth:sanctum', 'approved_provider', 'provider_type:freelancer'])->group(function () {
        Route::get('/freelancer/contracts', [ContractController::class, 'myContracts']);
        Route::post('/job-offers/{id}/apply', [JobOfferController::class, 'apply']);
        Route::get('/freelancer/blocked-dates', [FreelancerBlockedDateController::class, 'index']);
        Route::post('/freelancer/blocked-dates', [FreelancerBlockedDateController::class, 'store']);
        Route::delete('/freelancer/blocked-dates/{id}', [FreelancerBlockedDateController::class, 'destroy']);
    });

    Route::middleware(['auth:sanctum', 'approved_provider', 'provider_type:company'])->group(function () {
        Route::get('/company/details', [CompanyDetailController::class, 'show']);
        Route::put('/company/details', [CompanyDetailController::class, 'update']);
    });

    Route::middleware(['auth:sanctum', 'approved_provider', 'provider_type:freelancer'])->group(function () {
        Route::get('/freelancer/details', [FreelancerDetailController::class, 'show']);
        Route::put('/freelancer/details', [FreelancerDetailController::class, 'update']);
    });

    Route::middleware(['auth:sanctum', 'is_admin'])->prefix('admin')->group(function () {
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/users/{id}/roles', [RoleController::class, 'assignToUser']);
        Route::get('/permissions', [RoleController::class, 'permissions']);
    });

    Route::post('/payments/upload-proof', [PaymentController::class, 'uploadProof']);
    Route::get('/admin/payments/{paymentId}/view', [PaymentController::class, 'viewProof']);

    Route::middleware(['auth:sanctum', 'role:provider'])->group(function () {
        Route::post('/provider/upload-qr', [ProviderController::class, 'uploadQrCode']);
    });
});
Route::middleware(['auth:sanctum', 'provider_type:company'])->group(function () {

    Route::get('/company/blocked-dates', [CompanyBlockedDateController::class, 'index']);
    Route::post('/company/blocked-dates', [CompanyBlockedDateController::class, 'store']);
    Route::delete('/company/blocked-dates/{id}', [CompanyBlockedDateController::class, 'destroy']);
});
