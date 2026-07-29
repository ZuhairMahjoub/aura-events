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

Route::middleware(['set_locale'])->group(function () {


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
                Route::get('/provider/my-arrangements', [ArrangementController::class, 'getMyPackages']); // API جديد
                Route::get('my-services', [ArrangementController::class, 'getMyServices']);
            });

            Route::get('provider/my-products', [ListingController::class, 'getCompanyProducts']);
            Route::get('/provider/available-freelancers', [ArrangementController::class, 'getFreelancersList']);
            Route::get('/job-offers', [JobOfferController::class, 'index']);
        });
        Route::get('/job-offers/{id}', [JobOfferController::class, 'show']);
        Route::get('/my-applied-jobs', [JobOfferController::class, 'getAppliedJobs']);
    });
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('book/{id}', [BookingController::class, 'show']);        // عرض تفاصيل حجز محدد (للطرفين)
        Route::post('/bookings', [BookingController::class, 'store']);

        Route::put('/bookings/{bookingId}/cancel', [BookingController::class, 'cancel']);

        Route::get('/bookings', [BookingController::class, 'myBookings']); // عرض قائمة الحجوزات
        Route::get('/provider/bookings', [BookingController::class, 'providerBookings']); // عرض قائمة الحجوزات

        Route::put('/bookings/{bookingId}/reject', [BookingController::class, 'reject']);
        Route::put('/bookings/{bookingId}/complete', [BookingController::class, 'complete']);
        Route::get('/bookings/{bookingId}/history', [BookingController::class, 'statusHistory']);

        Route::post('/bookings/{bookingId}/cancel', [BookingController::class, 'cancel']);

        Route::put('/bookings/{bookingId}/accept', [BookingController::class, 'accept'])
            ->middleware('approved_provider');
    });



    Route::middleware(['auth:sanctum', 'is_admin'])->prefix('admin')->group(function () {
        Route::put('/{paymentId}/confirm', [PaymentController::class, 'confirmPayment']);
        Route::put('/{paymentId}/reject', [PaymentController::class, 'rejectPayment']);

        // رابط لرفض الدفع
        Route::put('/{paymentId}/reject', [PaymentController::class, 'rejectPayment']);
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
        // قائمة كل الحجوزات بالنظام، قابلة للفلترة بـ status/booking_type/تاريخ

        Route::get('/bookings/{id}', [AdminBookingController::class, 'show']);
        // يرجع: عدد providers لكل moderation_status، عدد listings لكل نوع/حالة，
        // عدد bookings لكل status، إجمالي الإيرادات (paid bookings)، إلخ.
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
        // تحديث brand_name، الصورة، إلخ من جدول providers نفسه
    });
    Route::middleware('auth:sanctum')->prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'show']);
        Route::post('/items', [CartController::class, 'addItem']);
        Route::patch('/items/{cartItemId}', [CartController::class, 'updateQuantity']);
        Route::delete('/items/{cartItemId}', [CartController::class, 'removeItem']);
        Route::post('/checkout', [CartController::class, 'checkout']);
        Route::delete('/cart', [CartController::class, 'clear']);
        Route::get('/cart/count', [CartController::class, 'itemsCount']);
        // حالياً العميل يحتاج يحذف كل عنصر لحاله (removeItem مرة بمرة) - لا يوجد "إفراغ الكل"
    });
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/chat/initialize', [ChatController::class, 'initializeChat']);
    });
    Route::get('/providers', [ProviderController::class, 'getProviders']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/favorites/{listingId}/toggle', [FavoriteController::class, 'toggle']);
        Route::get('/favorites', [FavoriteController::class, 'index']);

        Route::post('/reviews/provider', [ReviewController::class, 'reviewProvider']);   // المنظم يقيّم المزود
        Route::post('/reviews/organizer', [ReviewController::class, 'reviewOrganizer']); // المزود يقيّم المنظم
        Route::get('/providers/{providerId}/reviews', [ReviewController::class, 'providerReviews']);
        Route::put('/reviews/{id}', [ReviewController::class, 'update']);
        // تعديل تقييم خلال فترة سماح معينة (مثلاً 24 ساعة) - يحتاج منطق جديد في ReviewService

        Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
        Route::get('/bookings/{bookingId}/reviews', [ReviewController::class, 'bookingReviews']);
        // يرجع تقييمي هذا الحجز تحديداً (منظم→مزود ومزود→منظم) إن وجدا
        // حذف تقييم (يفضّل تقييده لصاحب التقييم فقط، مع إعادة حساب المتوسط تلقائياً)
    });
    Route::middleware(['auth:sanctum', 'approved_provider', 'provider_type:company'])->group(function () {
        Route::get('/services', [ServiceController::class, 'index']);
        Route::post('/services', [ServiceController::class, 'store']);
        Route::put('/services/{id}', [ServiceController::class, 'update']);
        Route::delete('/services/{id}', [ServiceController::class, 'destroy']);
        Route::patch('/services/{id}/toggle-active', [ServiceController::class, 'toggleActive']);
        // يتطلب إضافة عمود is_active لجدول services أولاً
    });
    Route::middleware(['auth:sanctum', 'approved_provider', 'provider_type:company'])->group(function () {
        Route::post('/job-offers', [JobOfferController::class, 'store']);
        Route::get('/company/applicants', [JobOfferController::class, 'getApplicants']);
        Route::put('/contracts/{id}/status', [JobOfferController::class, 'updateApplicantStatus']);
        // يرجع كل عقود الفريلانسر الحالي بكل الحالات (pending/active/rejected/expired)

        Route::get('/company/contracts', [ContractController::class, 'companyContracts']);
        // يرجع كل عقود الشركة الحالية مع فريلانسريها
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
        // تحديث بيانات CompanyDetail: العنوان، district_id، معلومات إضافية خاصة بالشركة
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


    // ضع مسارات العرض العام هنا لتعمل بدون تسجيل دخول

    // مسارات الـ listings
    Route::prefix('listings/show')->group(function () {
        Route::get('/', [ListingController::class, 'index']);
        Route::get('/{listing}', [ListingController::class, 'show']);
    });

    // مسار الـ districts (مستقل)
    Route::get('districts', [DistrictsController::class, 'index']);
});

Route::post('/payments/upload-proof', [PaymentController::class, 'uploadProof']);

Route::get('/admin/payments/{paymentId}/view', [PaymentController::class, 'viewProof']);

Route::middleware(['auth:sanctum', 'role:provider'])->group(function () {
    Route::post('/provider/upload-qr', [ProviderController::class, 'uploadQrCode']);
});



Route::get('/listings/physical_products', [ListingController::class, 'getProductsOffers']);
Route::get('/listings/hall', [ListingController::class, 'getHallsOffers']);
Route::get('/listings/service', [ListingController::class, 'getServicesOffers']);
Route::get('/listings/package', [ListingController::class, 'getPackagesOffers']);
