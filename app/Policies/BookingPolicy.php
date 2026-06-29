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
        return $user->id === $booking->user_id;
    }
    public function accept(User $user, Booking $booking): bool
    {
        // تحقق أولاً إذا كان المستخدم يملك بروفايل مزود خدمة
        if (!$user->providerProfile) {
            return false;
        }

        return $user->providerProfile->id === $booking->provider_id;
    }
    public function reject(User $user, Booking $booking): bool
    {
        return $user->providerProfile && $user->providerProfile->id === $booking->provider_id;
    }

    public function complete(User $user, Booking $booking): bool
    {
        return $user->providerProfile && $user->providerProfile->id === $booking->provider_id;
    }

    public function view(User $user, Booking $booking): bool
    {
        $isOwner = $user->id === $booking->user_id;
        $isProvider = $user->providerProfile && $user->providerProfile->id === $booking->provider_id;

        return $isOwner || $isProvider;
    }
}
