<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BookingService;

class ExpireUnpaidAcceptedBookings extends Command
{
    protected $signature = 'bookings:expire-unpaid-accepted';

    protected $description = 'إلغاء الحجوزات accepted اللي تجاوزت مهلة الدفع بعد قبول المزوّد دون تأكيد الدفع تلقائياً';

    public function __construct(private readonly BookingService $bookingService)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $count = $this->bookingService->expireUnpaidAcceptedBookings();

        if ($count > 0) {
            $this->info("تم إلغاء {$count} حجز/حجوزات accepted تجاوزت مهلة الدفع دون تأكيد.");
        } else {
            $this->info('لا توجد حجوزات accepted منتهية مهلة الدفع حالياً.');
        }
    }
}