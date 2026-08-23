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
        return $this->isActiveOwningProvider($user, $booking);
    }

    public function reject(User $user, Booking $booking): bool
    {
        return $this->isActiveOwningProvider($user, $booking);
    }

    public function complete(User $user, Booking $booking): bool
    {
        return $this->isActiveOwningProvider($user, $booking);
    }

    public function view(User $user, Booking $booking): bool
    {
        $isOwner = $user->id === $booking->user_id;
        $isProvider = $user->providerProfile && $user->providerProfile->id === $booking->provider_id;

        return $isOwner || $isProvider;
    }

    /**
     * إصلاح: التحقق من ملكية provider_id وحدها لم يكن كافياً — لو عُلِّق
     * حساب Provider (is_active = false) بعد إنشاء حجوزات pending، كان
     * بإمكانه رغم ذلك قبول/رفض/إكمال تلك الحجوزات لأن الفحص القديم لا
     * يتحقق من حالة تفعيله الحالية. الآن نتحقق صراحةً من is_active.
     */
  private function isActiveOwningProvider(User $user, Booking $booking): bool
{
    $provider = $user->providerProfile;

    if (! $provider) {
        return false;
    }

    // تعديل: استخدام التحقق المرن للحالة لتجنب مشاكل الـ TinyInteger (1 أو true)
    return $provider->id === $booking->provider_id && (bool)$provider->is_active;
}
}