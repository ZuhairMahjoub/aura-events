<?php

namespace App\Http\Controllers;

use App\DTOs\BookingData;
use App\Http\Requests\StoreBookingRequest;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Booking;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\BookingResource;
use App\Services\FirebaseNotificationService;
use App\Http\Resources\BookResource;

class BookingController extends Controller
{
    protected $firebaseNotificationService;

    public function __construct(
        private readonly BookingService $bookingService,
        FirebaseNotificationService $firebaseNotificationService
    ) {
        $this->firebaseNotificationService = $firebaseNotificationService;
    }

    /**
     * =========================================================
     *  NOTIFICATION HELPERS
     * =========================================================
     */

    private function resolveProviderUserId(Booking $booking): ?string
    {
        $providerUserId = $booking->provider?->user_id;

        if (!$providerUserId) {
            Log::warning("Booking #{$booking->id}: no linked user_id found for provider #{$booking->provider_id}. Notification skipped.");
        }

        return $providerUserId;
    }

    private function notifyUser(Booking $booking, string $title, string $body, array $extraData = []): void
    {
        $result = $this->firebaseNotificationService->sendToUser(
            $booking->user_id,
            $title,
            $body,
            array_merge(['booking_id' => $booking->id], $extraData)
        );

        if (!($result['success'] ?? false)) {
            Log::warning("Booking #{$booking->id}: user notification failed - " . ($result['message'] ?? 'unknown reason'));
        }
    }

    private function notifyProvider(Booking $booking, string $title, string $body, array $extraData = []): void
    {
        $providerUserId = $this->resolveProviderUserId($booking);

        if (!$providerUserId) {
            return;
        }

        $result = $this->firebaseNotificationService->sendToUser(
            $providerUserId,
            $title,
            $body,
            array_merge(['booking_id' => $booking->id], $extraData)
        );

        if (!($result['success'] ?? false)) {
            Log::warning("Booking #{$booking->id}: provider notification failed - " . ($result['message'] ?? 'unknown reason'));
        }
    }

    /**
     * =========================================================
     *  BOOKING LIFECYCLE ACTIONS (كل حالة: رسالة لليوزر + رسالة للبروفايدر)
     * =========================================================
     */

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $data = Booking::fromRequest(
            $request->validated(),
            $request->user()->id
        );

        $booking = $this->bookingService->book($data);

        // 1) للمستخدم: تذكير بالدفع خلال 3 أيام
        $this->notifyUser(
            $booking,
            __('notif_booking_payment_due_title'),
            __('notif_booking_payment_due_body'),
            ['action' => 'booking_payment_due']
        );

        // 2) لمزود الخدمة: طلب حجز جديد
        $this->notifyProvider(
            $booking,
            __('notif_booking_new_title'),
            __('notif_booking_new_body', ['name' => $request->user()->first_name]),
            ['action' => 'new_booking']
        );

        return response()->json([
            'message' => 'تم إرسال طلب الحجز بنجاح.',
            'data'    => $booking,
        ], 201);
    }

    public function accept(string $bookingId): JsonResponse
    {
        $booking = Booking::findOrFail($bookingId);
        Gate::authorize('accept', $booking);

        $providerId = request()->user()->providerProfile->id;
        $booking = $this->bookingService->accept($bookingId, $providerId);

        // 1) للمستخدم: تم قبول حجزك
        $this->notifyUser(
            $booking,
            __('notif_booking_accepted_title'),
            __('notif_booking_accepted_body'),
            ['action' => 'booking_accepted']
        );

        // 2) لمزود الخدمة: تأكيد أنه قَبِل الطلب بنجاح
        $this->notifyProvider(
            $booking,
            __('notif_booking_accepted_provider_title'),
            __('notif_booking_accepted_provider_body'),
            ['action' => 'booking_accepted_confirmation']
        );

        return response()->json([
            'success' => true,
            'message' => 'تم قبول الحجز بنجاح.',
            'data'    => $booking,
        ]);
    }

    public function reject(string $bookingId): JsonResponse
    {
        $booking = Booking::findOrFail($bookingId);
        Gate::authorize('reject', $booking);

        $providerProfile = request()->user()->providerProfile;
        if (!$providerProfile) {
            abort(403, 'يجب أن تمتلك ملف مزود خدمة لإجراء هذه العملية.');
        }

        $rejectedBooking = $this->bookingService->reject(
            $bookingId,
            $providerProfile->id,
            request()->input('reason')
        );

        // 1) للمستخدم: تم رفض حجزك
        $this->notifyUser(
            $rejectedBooking,
            __('notif_booking_rejected_title'),
            __('notif_booking_rejected_body'),
            ['action' => 'booking_rejected']
        );

        // 2) لمزود الخدمة: تأكيد أنه رفض الطلب
        $this->notifyProvider(
            $rejectedBooking,
            __('notif_booking_rejected_provider_title'),
            __('notif_booking_rejected_provider_body'),
            ['action' => 'booking_rejected_confirmation']
        );

        return response()->json([
            'message' => 'تم رفض الحجز بنجاح.',
            'booking' => $rejectedBooking,
        ]);
    }

    public function complete(string $bookingId): JsonResponse
    {
        $booking = Booking::findOrFail($bookingId);
        Gate::authorize('complete', $booking);

        $booking = $this->bookingService->complete($bookingId);

        // 1) للمستخدم: تم إتمام الخدمة
        $this->notifyUser(
            $booking,
            __('notif_booking_completed_title'),
            __('notif_booking_completed_body'),
            ['action' => 'booking_completed']
        );

        // 2) لمزود الخدمة: تأكيد إتمام الخدمة
        $this->notifyProvider(
            $booking,
            __('notif_booking_completed_provider_title'),
            __('notif_booking_completed_provider_body'),
            ['action' => 'booking_completed_provider']
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير حالة الحجز إلى مكتمل بنجاح.',
            'data'    => $booking,
        ]);
    }

    public function cancel(Request $request, string $bookingId): JsonResponse
    {
        $booking = Booking::findOrFail($bookingId);
        Gate::authorize('cancel', $booking);

        $cancelledBy = $request->user()->hasRole('provider') ? 'provider' : 'organizer';

        $booking = $this->bookingService->cancel(
            $bookingId,
            $cancelledBy,
            $request->input('reason')
        );

        // الطرف يلي ألغى ما بينشعر لحاله، بس الطرف التاني
        if ($cancelledBy === 'organizer') {
            $this->notifyProvider(
                $booking,
                __('notif_booking_cancelled_title'),
                __('notif_booking_cancelled_body', ['id' => $booking->id]),
                ['action' => 'booking_cancelled']
            );
        } else {
            $this->notifyUser(
                $booking,
                __('notif_booking_cancelled_title'),
                __('notif_booking_cancelled_body', ['id' => $booking->id]),
                ['action' => 'booking_cancelled']
            );
        }

        return response()->json([
            'message' => 'تم إلغاء الحجز.',
            'data'    => $booking,
        ]);
    }

    /**
     * =========================================================
     *  READ / LISTING ACTIONS (بدون تعديل منطقي)
     * =========================================================
     */

    public function myBookings(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'booking_type']);

        $bookings = $this->bookingService->getUserBookings(
            $request->user()->id,
            $filters,
            $request->input('per_page', 15)
        );

        return response()->json(
            array_merge(
                [
                    'success' => true,
                    'message' => 'تم استرجاع حجوزاتك بنجاح.',
                ],
                BookingResource::collection($bookings)->response()->getData(true)
            )
        );
    }

    public function providerBookings(Request $request): JsonResponse
    {
        if (!$request->user()->providerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'عذراً، هذا الحساب ليس حساب مزود خدمة.',
            ], 403);
        }

        $providerId = $request->user()->providerProfile->id;
        $filters = $request->only(['status', 'booking_type']);

        $bookings = $this->bookingService->getProviderBookings(
            $providerId,
            $filters,
            $request->input('per_page', 15)
        );

        return response()->json(
            array_merge(
                [
                    'success' => true,
                    'message' => 'تم استرجاع حجوزات مزود الخدمة بنجاح.',
                ],
                BookResource::collection($bookings)->response()->getData(true)
            )
        );
    }

    public function show(string $id): JsonResponse
    {
        $booking = Booking::with(['user', 'listing', 'variant', 'slot'])->findOrFail($id);
        Gate::authorize('view', $booking);

        return response()->json([
            'success' => true,
            'data'    => new BookingResource($booking),
        ]);
    }
}