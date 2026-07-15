<?php

namespace App\Processors;

use App\Models\Booking;
use App\Models\Payment;

class BookingPaymentProcessor
{
    public function storeProof(string $bookingId, string $pdfPath)
    {
        $booking = Booking::findOrFail($bookingId);

        // حفظ طلب الدفع بحالة pending ليظهر عندك في لوحة التحكم
        return Payment::create([
            'booking_id'      => $booking->id,
            'provider'        => 'shamcash',
            'amount'          => $booking->total_price, // المبلغ المطلوب أصلاً
            'status'          => 'pending',
            'proof_file_path' => $pdfPath
        ]);
    }
}