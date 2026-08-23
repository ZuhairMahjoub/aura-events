<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BookingService;

class ExpireStaleBookings extends Command
{
    protected $signature = 'bookings:expire-stale';

    protected $description = 'إنهاء الحجوزات pending اللي تجاوزت مهلة رد المزوّد تلقائياً (Temporary Hold + Timeout) وإرجاع السعة/التاريخ المحجوزين';

    public function __construct(private readonly BookingService $bookingService)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $count = $this->bookingService->expireStalePendingBookings();

        if ($count > 0) {
            $this->info("تم بنجاح إنهاء {$count} حجز/حجوزات انتهت مهلتها تلقائياً وإرجاع سعتها.");
        } else {
            $this->info('لا توجد حجوزات pending منتهية المهلة حالياً.');
        }
    }
}