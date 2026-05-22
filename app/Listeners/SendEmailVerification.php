<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendEmailVerification implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public $tries = 3;      // حاول 3 مرات قبل أن تعطي FAIL
    public $backoff = 10;
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
 public function handle(UserRegistered $event): void
{
    Log::info('--- بدأت عملية إرسال الإيميل للمستخدم: ' . $event->user->email);
    
    if (!$event->user->email) {
        return;
    }

    if ($event->user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail) {
        $event->user->sendEmailVerificationNotification();
    }
}
}
