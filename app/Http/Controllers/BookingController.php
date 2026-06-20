<?php

namespace App\Http\Controllers;

use App\DTOs\BookingData;
use App\Http\Requests\StoreBookingRequest;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Booking;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {}

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $data =  \App\Models\Booking::fromRequest(
            $request->validated(),
            $request->user()->id
        );

        $booking = $this->bookingService->book($data);

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

        $cancelledBy = $request->user()->hasRole('provider') ? 'provider' : 'organizer';

        $booking = $this->bookingService->cancel(
            $bookingId,
            $cancelledBy,
            $request->input('reason')
        );

        return response()->json([
            'message' => 'تم إلغاء الحجز.',
            'data'    => $booking,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'booking_type']);

        $bookings = $this->bookingService->getUserBookings(
            $request->user()->id,
            $filters,
            $request->input('per_page', 15)
        );

        return response()->json([
            'success' => true,
            'data'    => $bookings,
        ]);
    }
    public function accept(string $bookingId): JsonResponse
{
    $booking = \App\Models\Booking::findOrFail($bookingId);
    Gate::authorize('accept', $booking);

    $providerId = request()->user()->providerProfile->id;

    $booking = $this->bookingService->accept($bookingId, $providerId);

    return response()->json([
        'success' => true,
        'message' => 'تم قبول الحجز بنجاح.',
        'data'    => $booking,
    ]);
}
}
