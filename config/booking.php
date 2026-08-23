<?php

return [
    /*
     * مدة المهلة (بالساعات) اللي بيضل فيها الحجز pending قبل ما ينتهي
     * تلقائياً إذا المزوّد ما رد عليه (قبول/رفض).
     *
     * تقدر تغيّرها من .env بدون أي تعديل بالكود:
     * BOOKING_PENDING_TIMEOUT_HOURS=48
     */
    'pending_timeout_hours' => (int) env('BOOKING_PENDING_TIMEOUT_HOURS', 48),
    'payment_timeout_hours' => (int) env('BOOKING_PAYMENT_TIMEOUT_HOURS', 24), // ← أضف هذا

];
