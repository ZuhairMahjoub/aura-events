<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBookingController extends Controller
{
    /**
     * GET /admin/bookings
     * قائمة كل الحجوزات بالنظام، قابلة للفلترة بـ status/booking_type/تاريخ.
     * مثال: /admin/bookings?status=cancelled&booking_type=hall&date_from=2026-01-01&date_to=2026-12-31
     */
    public function index(Request $request): JsonResponse
    {
        $bookings = Booking::with(['user', 'provider', 'listing'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('booking_type'), fn ($q) => $q->where('booking_type', $request->input('booking_type')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('booked_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('booked_date', '<=', $request->input('date_to')))
            ->latest()
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $bookings,
        ]);
    }

    /**
     * GET /admin/bookings/{id}
     * تفاصيل حجز واحد كاملة، مع سجل تحويلات الحالة إن وُجد.
     */
    public function show(string $id): JsonResponse
    {
        $booking = Booking::with(['user', 'provider', 'listing', 'variant'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $booking,
        ]);
    }
}