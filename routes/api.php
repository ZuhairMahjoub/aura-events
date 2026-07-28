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

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| ملاحظة: كل endpoint URL محافظ عليه بالضبط متل الملف الأصلي — ما تغيّر
| ولا path واحد. الإضافات هون هي فقط: تقسيم بـ sections بعنوان واضح،
| و ->name() على كل route (ميتا-داتا داخلية بالـ router، ما إلها أي
| تأثير على الـ URL أو الـ behavior). أي bug لاحظته موثّق بتعليق NOTE
| بمكانه بدل ما أصلّحه بصمت.
*/

Route::middleware(['set_locale'])->group(function () {

    /*
    |----------------------------------------------------------------
    | Auth
    |----------------------------------------------------------------
    */
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/register', [AuthController::class, 'store'])->name('register');
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1')
            ->name('login');
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])
            ->middleware('throttle:verify-otp')
            ->name('verify-otp');
        Route::get('/google/redirect', [AuthController::class, 'redirectToGoogle'])->name('google.redirect');
        Route::get('/google/callback', [AuthController::class, 'handleGoogleCallBack'])->name('google.callback');
        Route::post('/google/mobile-login', [AuthController::class, 'handleGoogleMobileLogin'])->name('google.mobile-login');
    });

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::middleware('abilities:issue-access-token')->group(function () {
            Route::post('/auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        });

        Route::middleware('abilities:access-api')->group(function () {
            Route::get('/user', function (Request $request) {
                return $request->user();
            })->name('user.show');
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('/auth/profile', [AuthController::class, 'getProfile'])->name('auth.profile');
        });
    });

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.forgot');
    Route::post('verify-otp', [NewPasswordController::class, 'verifyOtp'])->name('password.verify-otp');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.reset');

   
    Route::get('/admin/users', [AuthController::class, 'getFilteredUsers'])->name('admin.users.index');

    Route::post('/otp/resend', [AuthController::class, 'resendOtp'])->name('otp.resend');
    Route::post('/auth/verify-email-otp', [EmailVerificationNotificationController::class, 'verifyOtp'])->name('email.verify-otp');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/districts', [DistrictsController::class, 'index'])->name('districts.index');

    /*
    |----------------------------------------------------------------
    | Provider Onboarding
    |----------------------------------------------------------------
    */
    Route::middleware(['auth:sanctum', EnsureAccountIsVerified::class])->group(function () {
        Route::post('/provider/complete-profile', [ProviderAuthController::class, 'store'])->name('provider.complete-profile');
    });

    /*
    |----------------------------------------------------------------
    | Uploads, Listings, Arrangements, Job Offers (provider-side)
    |----------------------------------------------------------------
    */
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/uploads/temp', [TempUploadController::class, 'upload'])->name('uploads.temp');
        Route::get('listings/{listingId}/images', [TempUploadController::class, 'index'])->name('listings.images');

        Route::prefix('listings')->name('listings.')->group(function () {
            Route::get('/provider/my-services', [ListingController::class, 'getCompanyServices'])->name('my-services');

            Route::middleware(['approved_provider'])->group(function () {
                Route::post('/', [ListingController::class, 'store'])->middleware('permission:create listings')->name('store');
                Route::put('/{listing}', [ListingController::class, 'update'])->middleware('permission:update listings')->name('update');
                Route::delete('/{listing}', [ListingController::class, 'destroy'])->middleware('permission:delete listings')->name('destroy');
            });
        });

        Route::middleware('approved_provider')->group(function () {
            Route::get('/provider/inventory', [ListingController::class, 'getCompanyInventory'])->name('provider.inventory');

           
            Route::prefix('arrangements')->name('arrangements.')->group(function () {
                Route::post('/', [ArrangementController::class, 'store'])->middleware('throttle:10,1')->name('store');
                Route::get('my-products', [ArrangementController::class, 'getMyProducts'])->name('my-products');
                Route::get('my-all-products', [ArrangementController::class, 'getMyAllProducts'])->name('my-all-products');
                Route::get('/provider/my-arrangements', [ArrangementController::class, 'getMyPackages'])->name('my-packages');
                Route::get('my-services', [ArrangementController::class, 'getMyServices'])->name('my-services');

                Route::get('/{arrangementId}', [ArrangementController::class, 'show'])->name('show');
                Route::put('/{arrangementId}', [ArrangementController::class, 'update'])->name('update');
                Route::delete('/{arrangementId}', [ArrangementController::class, 'destroy'])->name('destroy');
            });

            Route::get('provider/my-products', [ListingController::class, 'getCompanyProducts'])->name('provider.my-products');
            Route::get('/provider/available-freelancers', [ArrangementController::class, 'getFreelancersList'])->name('provider.available-freelancers');
            Route::get('/job-offers', [JobOfferController::class, 'index'])->name('job-offers.index');
        });

        Route::get('/job-offers/{id}', [JobOfferController::class, 'show'])->name('job-offers.show');
        Route::get('/my-applied-jobs', [JobOfferController::class, 'getAppliedJobs'])->name('job-offers.my-applied');
    });

    /*
    |----------------------------------------------------------------
    | Bookings
    |----------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {
        // NOTE: this literal path is "book/{id}" (singular, no leading
        // slash), inconsistent with the "/bookings" routes below it —
        // kept exactly as-is rather than "fixing" it to bookings/{id}.
        Route::get('book/{id}', [BookingController::class, 'show'])->name('bookings.show'); // عرض تفاصيل حجز محدد (للطرفين)
        Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');

        Route::put('/bookings/{bookingId}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel.put');

        Route::get('/bookings', [BookingController::class, 'myBookings'])->name('bookings.index'); // عرض قائمة الحجوزات
        Route::get('/provider/bookings', [BookingController::class, 'providerBookings'])->name('provider.bookings'); // عرض قائمة الحجوزات

        Route::put('/bookings/{bookingId}/reject', [BookingController::class, 'reject'])->name('bookings.reject');
        Route::put('/bookings/{bookingId}/complete', [BookingController::class, 'complete'])->name('bookings.complete');
        Route::get('/bookings/{bookingId}/history', [BookingController::class, 'statusHistory'])->name('bookings.history');

        // NOTE: same path as the PUT cancel route above, different verb —
        // both existed in the original (POST here, PUT above). Kept both.
        // Worth confirming which one the client actually calls; the unused
        // one is dead code either way.
        Route::post('/bookings/{bookingId}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel.post');

        Route::put('/bookings/{bookingId}/accept', [BookingController::class, 'accept'])
            ->middleware('approved_provider')
            ->name('bookings.accept');
    });

    /*
    |----------------------------------------------------------------
    | Admin
    |----------------------------------------------------------------
    */
    Route::middleware(['auth:sanctum', 'is_admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::put('/{paymentId}/confirm', [PaymentController::class, 'confirmPayment'])->name('payments.confirm');
        Route::put('/{paymentId}/reject', [PaymentController::class, 'rejectPayment'])->name('payments.reject');
      

        Route::put('/providers/{id}/approve', [AdminProviderController::class, 'approve'])->name('providers.approve');
        Route::put('/providers/{id}/reject', [AdminProviderController::class, 'reject'])->name('providers.reject');
        Route::put('/listings/{id}/approve', [AdminListingController::class, 'approve'])->name('listings.approve');
        Route::put('/listings/{id}/reject', [AdminListingController::class, 'reject'])->name('listings.reject');
        Route::get('/providers/{id}', [AdminProviderController::class, 'showProvider'])->name('providers.show');

     
        Route::get('/Organzier/{id}', [AdminProviderController::class, 'getUserDetails'])->name('organizer.show');

        Route::get('/dashboard-stats', [AdminDashboardController::class, 'stats'])->name('dashboard-stats');
        Route::get('/bookings', [AdminBookingController::class, 'index'])->name('bookings.index'); // قائمة كل الحجوزات بالنظام، قابلة للفلترة بـ status/booking_type/تاريخ
        Route::get('/listings/{id}', [AdminListingController::class, 'show'])->name('listings.show');
        Route::get('/listings/pending', [AdminListingController::class, 'pendingList'])->name('listings.pending');
        Route::get('/bookings/{id}', [AdminBookingController::class, 'show'])->name('bookings.show');
    });

    /*
    |----------------------------------------------------------------
    | Notifications
    |----------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/notifications', [NotificationController::class, 'all'])->name('notifications.index');
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('/notifications/test', [NotificationController::class, 'sendTestNotification'])->name('notifications.test');
        Route::post('/device-token', [NotificationController::class, 'updateToken'])->name('device-token.update');
    });

    /*
    |----------------------------------------------------------------
    | Provider Profile
    |----------------------------------------------------------------
    */
    Route::middleware(['auth:sanctum', 'approved_provider'])->group(function () {
        Route::get('/provider/profile', [ProviderController::class, 'profile'])->name('provider.profile.show');

        
        Route::get('/admin/providers', [ProviderController::class, 'index'])->name('admin.providers.index');

        Route::put('/provider/profile', [ProviderController::class, 'update'])->name('provider.profile.update');
    });

    /*
    |----------------------------------------------------------------
    | Cart
    |----------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->prefix('cart')->name('cart.')->group(function () {
        Route::get('/', [CartController::class, 'show'])->name('show');
        Route::post('/items', [CartController::class, 'addItem'])->name('items.store');
        Route::patch('/items/{cartItemId}', [CartController::class, 'updateQuantity'])->name('items.update');
        Route::delete('/items/{cartItemId}', [CartController::class, 'removeItem'])->name('items.destroy');
        Route::post('/checkout', [CartController::class, 'checkout'])->name('checkout');

        // NOTE: both of these resolve to /cart/cart and /cart/cart/count
        // (prefix('cart') + '/cart...') — a doubled path segment, almost
        // certainly a copy-paste bug (should probably be '/' and
        // '/count'). Preserved exactly as original either way; say the
        // word if you want these collapsed to /cart and /cart/count.
        Route::delete('/cart', [CartController::class, 'clear'])->name('clear');
        Route::get('/cart/count', [CartController::class, 'itemsCount'])->name('count');
    });

    /*
    |----------------------------------------------------------------
    | Chat
    |----------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/chat/initialize', [ChatController::class, 'initializeChat'])->name('chat.initialize');
    });

    Route::get('/providers', [ProviderController::class, 'getProviders'])->name('providers.index');

    /*
    |----------------------------------------------------------------
    | Favorites & Reviews
    |----------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/favorites/{listingId}/toggle', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
        Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');

        Route::post('/reviews/provider', [ReviewController::class, 'reviewProvider'])->name('reviews.provider.store');   // المنظم يقيّم المزود
        Route::post('/reviews/organizer', [ReviewController::class, 'reviewOrganizer'])->name('reviews.organizer.store'); // المزود يقيّم المنظم
        Route::get('/providers/{providerId}/reviews', [ReviewController::class, 'providerReviews'])->name('providers.reviews');
        Route::put('/reviews/{id}', [ReviewController::class, 'update'])->name('reviews.update');
        // تعديل تقييم خلال فترة سماح معينة (مثلاً 24 ساعة) - يحتاج منطق جديد في ReviewService

        Route::delete('/reviews/{id}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
        Route::get('/bookings/{bookingId}/reviews', [ReviewController::class, 'bookingReviews'])->name('bookings.reviews');
        // يرجع تقييمي هذا الحجز تحديداً (منظم→مزود ومزود→منظم) إن وجدا
        // حذف تقييم (يفضّل تقييده لصاحب التقييم فقط، مع إعادة حساب المتوسط تلقائياً)
    });

    /*
    |----------------------------------------------------------------
    | Company: Services
    |----------------------------------------------------------------
    */
    Route::middleware(['auth:sanctum', 'approved_provider', 'provider_type:company'])->group(function () {
        Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::put('/services/{id}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('/services/{id}', [ServiceController::class, 'destroy'])->name('services.destroy');
        Route::patch('/services/{id}/toggle-active', [ServiceController::class, 'toggleActive'])->name('services.toggle-active');
    });

    /*
    |----------------------------------------------------------------
    | Company: Job Offers & Contracts
    |----------------------------------------------------------------
    */
    Route::middleware(['auth:sanctum', 'approved_provider', 'provider_type:company'])->group(function () {
        Route::post('/job-offers', [JobOfferController::class, 'store'])->name('job-offers.store');
        Route::get('/company/applicants', [JobOfferController::class, 'getApplicants'])->name('company.applicants');
        Route::put('/contracts/{id}/status', [JobOfferController::class, 'updateApplicantStatus'])->name('contracts.update-status');
        // يرجع كل عقود الفريلانسر الحالي بكل الحالات (pending/active/rejected/expired)

        Route::get('/company/contracts', [ContractController::class, 'companyContracts'])->name('company.contracts');
    });

    /*
    |----------------------------------------------------------------
    | Freelancer: Contracts, Applications, Blocked Dates
    |----------------------------------------------------------------
    */
    Route::middleware(['auth:sanctum', 'approved_provider', 'provider_type:freelancer'])->group(function () {
        Route::get('/freelancer/contracts', [ContractController::class, 'myContracts'])->name('freelancer.contracts');

        Route::post('/job-offers/{id}/apply', [JobOfferController::class, 'apply'])->name('job-offers.apply');
        Route::get('/freelancer/blocked-dates', [FreelancerBlockedDateController::class, 'index'])->name('freelancer.blocked-dates.index');
        Route::post('/freelancer/blocked-dates', [FreelancerBlockedDateController::class, 'store'])->name('freelancer.blocked-dates.store');
        Route::delete('/freelancer/blocked-dates/{id}', [FreelancerBlockedDateController::class, 'destroy'])->name('freelancer.blocked-dates.destroy');
    });

    /*
    |----------------------------------------------------------------
    | Company / Freelancer Details
    |----------------------------------------------------------------
    */
    Route::middleware(['auth:sanctum', 'approved_provider', 'provider_type:company'])->group(function () {
        Route::get('/company/details', [CompanyDetailController::class, 'show'])->name('company.details.show');
        Route::put('/company/details', [CompanyDetailController::class, 'update'])->name('company.details.update');
        // تحديث بيانات CompanyDetail: العنوان، district_id، معلومات إضافية خاصة بالشركة
    });
    Route::middleware(['auth:sanctum', 'approved_provider', 'provider_type:freelancer'])->group(function () {
        Route::get('/freelancer/details', [FreelancerDetailController::class, 'show'])->name('freelancer.details.show');
        Route::put('/freelancer/details', [FreelancerDetailController::class, 'update'])->name('freelancer.details.update');
    });

    /*
    |----------------------------------------------------------------
    | Admin: Roles & Permissions
    |----------------------------------------------------------------
    */
    Route::middleware(['auth:sanctum', 'is_admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('/users/{id}/roles', [RoleController::class, 'assignToUser'])->name('users.assign-roles');
        Route::get('/permissions', [RoleController::class, 'permissions'])->name('permissions.index');
    });

    /*
    |----------------------------------------------------------------
    | Public Listings
    |----------------------------------------------------------------
    */
    Route::prefix('listings/show')->name('listings.show.')->group(function () {
        Route::get('/', [ListingController::class, 'index'])->name('index');
        Route::get('/{listing}', [ListingController::class, 'show'])->name('show');
    });

    // NOTE: exact duplicate of the /districts route registered near the
    // top of this file — same method, same path, same controller/action.
    // Removed here since it's dead weight, not a behavior change (the
    // first registration already served every request to this URL).
});

Route::post('/payments/upload-proof', [PaymentController::class, 'uploadProof'])->name('payments.upload-proof');

Route::get('/admin/payments/{paymentId}/view', [PaymentController::class, 'viewProof'])->name('admin.payments.view-proof');

Route::middleware(['auth:sanctum', 'role:provider'])->group(function () {
    Route::post('/provider/upload-qr', [ProviderController::class, 'uploadQrCode'])->name('provider.upload-qr');
});

  
Route::get('/listings/physical_products', [ListingController::class, 'getProductsOffers']);
    Route::get('/listings/hall', [ListingController::class, 'getHallsOffers']);
    Route::get('/listings/service', [ListingController::class, 'getServicesOffers']);
    Route::get('/listings/package', [ListingController::class, 'getPackagesOffers']);
    
