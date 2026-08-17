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
  public function uploadProof(Request $request, BookingPaymentProcessor $processor)
{
    $request->validate([
        'booking_id' => 'required|exists:bookings,id',
        'proof_file' => 'required|file|mimes:pdf|max:2048',
        'amount'     => 'required|numeric|min:1',
    ]);

    $path = $request->file('proof_file')->store('payments', 'public');

    $processor->storeProof($request->booking_id, $path, $request->input('amount'));

    return response()->json(['message' => 'تم استلام الملف بنجاح.']);
}

   public function confirmPayment($paymentId)
{
    $payment = Payment::with('booking.provider')->findOrFail($paymentId);

    if ($payment->status === 'completed') {
        return response()->json([
            'message' => 'تم تأكيد الدفع مسبقاً.',
        ], 200);
    }

    \Illuminate\Support\Facades\DB::transaction(function () use ($payment) {
        $payment->update(['status' => 'completed']);

        $payment->booking->update([
            'status'         => 'completed',
            'payment_status' => 'paid',
        ]);

        $payment->booking->provider->increment('wallet_balance', $payment->amount);
    });

    return response()->json(['message' => 'تم تأكيد الدفع وإضافة المبلغ لمحفظة المزوّد بنجاح.']);
}

    public function rejectPayment(Request $request, $paymentId)
    {
        $payment = Payment::findOrFail($paymentId);
        
        $payment->update([
            'status' => 'failed',
            'admin_notes' => $request->notes 
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


    $response->headers->set('X-Payment-Amount', $payment->amount);
    $response->headers->set('X-Payment-Currency', $payment->currency);

    $providerName = $payment->booking?->provider?->brand_name ?? $payment->booking?->provider?->name ?? 'Unknown';
    $response->headers->set('X-Provider-Name', $providerName);

    if ($payment->booking?->provider_id) {
        $response->headers->set('X-Provider-Id', $payment->booking->provider_id);
    }

    return $response;
}
 
}