<?php
// app/Services/Booking/BookingStrategyFactory.php

namespace App\Services\Booking;

use App\Contracts\BookingStrategyInterface;
use App\Services\Booking\Strategies\HallBookingStrategy;
use App\Services\Booking\Strategies\PhysicalProductBookingStrategy;
use App\Services\Booking\Strategies\ServiceBookingStrategy;
use InvalidArgumentException;

class BookingStrategyFactory 
{
    /**
     * خريطة النوع → الـ Strategy.
     * لإضافة نوع رابع: أضف سطراً واحداً هنا فقط.
     * لا تغيير في BookingService، لا تغيير في Schema.
     */
    private array $strategies = [
        'physical_product' => PhysicalProductBookingStrategy::class,
        'hall'             => HallBookingStrategy::class,
        'service'          => ServiceBookingStrategy::class,
        // 'package'          => PackageBookingStrategy::class, // مستقبلاً
    ];

    public function make(string $bookingType): BookingStrategyInterface
    {
        if (!isset($this->strategies[$bookingType])) {
            throw new InvalidArgumentException(
                "لا توجد Strategy مسجلة للنوع: [{$bookingType}]"
            );
        }

        // إذا كانت الـ Strategy تحتاج dependencies، استخدم app()->make()
        return app()->make($this->strategies[$bookingType]);
    }
}