<?php
// app/Policies/BookingPolicy.php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /**
     * هل يحق للمستخدم إلغاء هذا الحجز؟
     */
    public function cancel(User $user, Booking $booking): bool
    {
        // فقط صاحب الحجز يمكنه الإلغاء
        return $user->id === $booking->user_id;
    }
}