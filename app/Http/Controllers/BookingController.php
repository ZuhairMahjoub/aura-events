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
        $data = BookingData::fromRequest(
            $request->validated(),
            $request->user()->id
        );

        $booking = $this->bookingService->book($data);

        return response()->json([
            'message' => 'تم إرسال طلب الحجز بنجاح.',
            'data'    => $booking,
        ], 201);
    }

public function cancel(Request $request,string $bookingId): JsonResponse
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
}
