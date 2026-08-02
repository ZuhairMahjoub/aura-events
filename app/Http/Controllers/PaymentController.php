<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Processors\BookingPaymentProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
{
    $payments = Payment::with(['booking.user', 'booking.provider', 'booking.listing', 'booking.variant'])
        ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
        ->latest()
        ->paginate($request->input('per_page', 20));

    return response()->json([
        'success' => true,
        'data' => $payments,
    ]);
}
    // دالة الزبون: لرفع الملف فقط
   public function uploadProof(Request $request, BookingPaymentProcessor $processor)
{
    $request->validate([
        'booking_id' => 'required|exists:bookings,id',
        'proof_file' => 'required|file|mimes:pdf|max:2048',
    ]);

    // نستخدم التخزين الافتراضي
    $path = $request->file('proof_file')->store('payments', 'public'); 
    
    // تأكد أن هذا السطر موجود ويعمل
    $processor->storeProof($request->booking_id, $path);

    return response()->json(['message' => 'تم استلام الملف بنجاح.']);
}

    // دالة الأدمن: تأكيد الدفع يدوياً
    public function confirmPayment($paymentId)
    {
        $payment = Payment::findOrFail($paymentId);

        // 1. تحديث حالة الدفع إلى مكتملة
        $payment->update(['status' => 'confirmed']);

        // 2. تحديث الحجز ليكون مؤكداً
        $payment->booking->update([
            'status' => 'confirmed',
            'payment_status' => 'paid'
        ]);

        return response()->json(['message' => 'تم تأكيد الدفع بنجاح.']);
    }

    // دالة الأدمن: رفض الدفع في حال كان المبلغ غير مطابق أو الملف غير صحيح
    public function rejectPayment(Request $request, $paymentId)
    {
        $payment = Payment::findOrFail($paymentId);
        
        $payment->update([
            'status' => 'failed',
            'admin_notes' => $request->notes // ملاحظاتك ليش رفضته (مثلاً: المبلغ ناقص)
        ]);

        return response()->json(['message' => 'تم رفض الدفع وتنبيه المستخدم.']);
    }
   public function viewProof(string $paymentId)
{
    $payment = Payment::with(['booking.provider'])->find($paymentId);

    if (!$payment) {
        return response()->json(['message' => 'عملية الدفع غير موجودة'], 404);
    }

    if (!$payment->proof_file_path || !Storage::disk('public')->exists($payment->proof_file_path)) {
        return response()->json([
            'message'    => 'الملف غير موجود',
            'payment_id' => $payment->id,
            'booking_id' => $payment->booking_id,
            'db_path'    => $payment->proof_file_path,
        ], 404);
    }

    $response = response()->file(Storage::disk('public')->path($payment->proof_file_path));

    $response->headers->set('X-Booking-Id', $payment->booking_id);
    $response->headers->set('X-Payment-Id', $payment->id);

    $providerName = $payment->booking?->provider?->brand_name ?? $payment->booking?->provider?->name ?? 'Unknown';
    $response->headers->set('X-Provider-Name', $providerName);

    if ($payment->booking?->provider_id) {
        $response->headers->set('X-Provider-Id', $payment->booking->provider_id);
    }

    return $response;
}
 
}