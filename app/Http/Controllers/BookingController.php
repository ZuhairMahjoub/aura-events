<?php

namespace App\Http\Controllers;

use App\DTOs\BookingData;
use App\Http\Requests\StoreBookingRequest;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Booking;
use Illuminate\Support\Facades\Gate;
use App\Http\Resources\BookingResource;
use Illuminate\Support\Facades\DB;
use App\Services\FirebaseNotificationService;


class BookingController extends Controller
{
        protected $firebaseNotificationService;

    public function __construct(
        private readonly BookingService $bookingService,FirebaseNotificationService $firebaseNotificationService
    ) {
              $this->firebaseNotificationService = $firebaseNotificationService;

    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $data = \App\Models\Booking::fromRequest(
            $request->validated(),
            $request->user()->id
        );

        $booking = $this->bookingService->book($data);
         $this->firebaseNotificationService->sendToUser(
        $booking->provider_id,
           __('notif_booking_new_title'),
           __('notif_booking_new_body', ['name' => $request->user()->first_name]),
           ['action' => 'new_booking', 'booking_id' => $booking->id]
    );
        return response()->json([
            'message' => 'تم إرسال طلب الحجز بنجاح.',
            'data'    => $booking,
        ], 201);
    }

    public function cancel(Request $request, string $bookingId): JsonResponse
    {
        // نتحقق من وجود الحجز والصلاحية أولاً (بدون lock — فقط للـ Authorization)
        $booking = \App\Models\Booking::findOrFail($bookingId);
        Gate::authorize('cancel', $booking);

        // إصلاح: القيمة يجب أن تطابق الـ ENUM المُوسَّع (organizer|provider|admin|system)
        $cancelledBy = $request->user()->hasRole('provider') ? 'provider' : 'organizer';

        $booking = $this->bookingService->cancel(
            $bookingId,
            $cancelledBy,
            $request->input('reason')
        );
      $this->firebaseNotificationService->sendToUser(
    $booking->user_id, // هون حطينا رقم المستخدم مباشرة
    __('notif_booking_cancelled_title'),
    __('notif_booking_cancelled_body', ['id' => $booking->id]),
     ['action' => 'booking_cancelled', 'booking_id' => $booking->id]
);

        return response()->json([
            'message' => 'تم إلغاء الحجز.',
            'data'    => $booking,
        ]);
    }

    public function myBookings(Request $request): JsonResponse
    {
        // 1. استقبال الفلاتر التي قد يرسلها اليوزر
        $filters = $request->only(['status', 'booking_type']);

        // 2. جلب الحجوزات الخاصة باليوزر الحالي (الأورجانيزر)
        $bookings = $this->bookingService->getUserBookings(
            $request->user()->id,
            $filters,
            $request->input('per_page', 15)
        );

        // 3. إرجاع النتيجة مع الحفاظ على هيكلية الـ Pagination
        return response()->json(
            array_merge(
                [
                    'success' => true,
                    'message' => 'تم استرجاع حجوزاتك بنجاح.'
                ],
                BookingResource::collection($bookings)->response()->getData(true)
            )
        );
    }
    public function accept(string $bookingId): JsonResponse
    {
        $booking = \App\Models\Booking::findOrFail($bookingId);

        Gate::authorize('accept', $booking);


        $providerId = request()->user()->providerProfile->id;

        $booking = $this->bookingService->accept($bookingId, $providerId);
        $this->firebaseNotificationService->sendToUser(
        $booking->user_id,
        __('notif_booking_accepted_title'),
        __('notif_booking_accepted_body'),
        ['action' => 'booking_accepted', 'booking_id' => $booking->id]
    );

        return response()->json([
            'success' => true,
            'message' => 'تم قبول الحجز بنجاح.',
            'data'    => $booking,
        ]);
    }
    public function providerBookings(Request $request): JsonResponse
    {
        // 1. التحقق من أن المستخدم يملك بروفايل مزود خدمة
        if (!$request->user()->providerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'عذراً، هذا الحساب ليس حساب مزود خدمة.'
            ], 403);
        }

        // 2. جلب معرف المزود من العلاقة
        $providerId = $request->user()->providerProfile->id;

        // 3. استقبال الفلاتر (مثل الستيتس ونوع الحجز)
        $filters = $request->only(['status', 'booking_type']);

        // 4. استدعاء السيرفيس لجلب البيانات المفلترة والمقسمة لصفحات
        $bookings = $this->bookingService->getProviderBookings(
            $providerId,
            $filters,
            $request->input('per_page', 15)
        );

        // 5. إرجاع الاستجابة بصيغة JSON مع الـ Pagination والـ Resource
        return response()->json(
            array_merge(
                [
                    'success' => true,
                    'message' => 'تم استرجاع حجوزات مزود الخدمة بنجاح.'
                ],
                BookingResource::collection($bookings)->response()->getData(true)
            )
        );
    }
    public function show(string $id): JsonResponse
    {
        $booking = \App\Models\Booking::findOrFail($id);
        Gate::authorize('view', $booking); // يجب أن تسمح الـ Policy للمنظم والمزود الخاص بالحجز برؤيته

        return response()->json([
            'success' => true,
            'data'    => new BookingResource($booking),
        ]);
    }
    public function complete(string $bookingId): JsonResponse
    {
        $booking = \App\Models\Booking::findOrFail($bookingId);
        Gate::authorize('complete', $booking); // تأكد من حمايتها في الـ Policy

        $booking = $this->bookingService->complete($bookingId);
        $this->firebaseNotificationService->sendToUser(
        $booking->user_id,
        __('notif_booking_completed_title'),
        __('notif_booking_completed_body'),
        ['action' => 'booking_completed', 'booking_id' => $booking->id]    );
        return response()->json([
            'success' => true,
            'message' => 'تم تغيير حالة الحجز إلى مكتمل بنجاح.',
            'data'    => $booking,
        ]);
    }
 public function reject(string $bookingId)
{
    // إصلاح حرج: كان هذا الميثود يكرر منطق BookingService::reject() بشكل
    // خاطئ ومستقل تماماً عنه، ما تسبب في:
    // 1) عدم التحقق من ملكية provider_id للحجز قبل الرفض.
    // 2) عدم التحقق من أن status = 'pending' قبل السماح بالرفض.
    // 3) increment('remaining_capacity') بقيمة 1 ثابتة، متجاهلاً quantity
    //    الفعلية للحجز — يُفسد السعة الاستيعابية لأي حجز بكمية > 1.
    // 4) عدم استدعاء releaseCapacity() الموحدة، فلا يُعاد stock_quantity
    //    في حالة physical_product إطلاقاً.
    //
    // الحل: تفويض المنطق بالكامل إلى BookingService::reject() الذي يطبّق
    // كل هذه الفحوصات تحت lockForUpdate صحيح.
    $booking = Booking::findOrFail($bookingId);

    Gate::authorize('reject', $booking);

    $providerId = request()->user()->providerProfile->id;

    $booking = $this->bookingService->reject(
        $bookingId,
        $providerId,
        request()->input('reason')
    );}

//     public function reject(string $bookingId, string $providerId, ?string $reason)
//     {
//         return DB::transaction(function () use ($bookingId, $providerId, $reason) {
//             $booking = Booking::findOrFail($bookingId);   //  بدون lockForUpdate

//             Gate::authorize('reject', $booking); // تأكد من إضافة دالة reject في الـ BookingPolicy

//             $booking->update([
//                 'status' => 'rejected',
//                 'cancelled_by' => 'provider',
//                 'cancellation_reason' => $reason,
//                 'rejected_at' => now()   //  عمود غير موجود
//             ]);
//             $this->firebaseNotificationService->sendToUser(
//         $booking->user_id,
//          __('notif_booking_rejected_title'),
//          __('notif_booking_rejected_body', ['reason' => $reason]),
//           ['action' => 'booking_rejected', 'booking_id' => $booking->id]    );

//     return response()->json([
//         'success' => true,
//         'message' => 'تم رفض الحجز بنجاح.',
//         'data'    => $booking,
//     ]);
// }
//     ); }
}