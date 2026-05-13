<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendEmailVerification implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
 public function handle(UserRegistered $event): void
{
    if (!$event->user->email) {
        return;
    }

    if ($event->user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail) {
        $event->user->sendEmailVerificationNotification();
    }
}
}
