<?php
// app/Events/BookingExpired.php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * يُبعث لما حجز pending تنتهي مهلته تلقائياً (بدون رد من المزوّد).
 * حالياً حدث عادي (نفس نمط BookingCreated/BookingCancelled)، مش
 * Broadcast Event، لأنه Pusher/Reverb غير مفعّل بعد بالمشروع.
 */
class BookingExpired
{
    use Dispatchable, SerializesModels;

    public function __construct(public Booking $booking) {}
}