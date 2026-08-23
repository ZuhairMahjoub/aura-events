<?php
// app/Events/BookingExpired.php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


class BookingExpired
{
    use Dispatchable, SerializesModels;

    public function __construct(public Booking $booking) {}
}