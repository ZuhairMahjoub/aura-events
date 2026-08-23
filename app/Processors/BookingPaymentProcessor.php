<?php

namespace App\Processors;

use App\Models\Booking;
use App\Models\Payment;

class BookingPaymentProcessor
{
    public function storeProof(string $bookingId, string $pdfPath, float $amount)
    {
        $booking = Booking::findOrFail($bookingId);

        return Payment::create([
            'booking_id'      => $booking->id,
            'provider'        => 'shamcash',
            'amount'          => $amount,    
            'status'          => 'pending',
            'proof_file_path' => $pdfPath
        ]);
    }
}