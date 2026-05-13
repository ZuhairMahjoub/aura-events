<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRegistered
{
    use Dispatchable, SerializesModels;

    /**
     * جعلنا المستخدم public لكي يصل إليه الـ Listeners بسهولة.
     * تم حذف الـ channel لأن الـ Listeners ستعرف القناة من بيانات المستخدم نفسه.
     */
    public function __construct(public User $user)
    {
        //
    }
}