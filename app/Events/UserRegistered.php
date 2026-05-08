<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRegistered
{
    use Dispatchable, SerializesModels;

    public User $user;
    public string $channel;

    /**
     * @param User $user المستخدم الذي تم إنشاؤه
     * @param string $channel القناة المستخدمة (phone أو email)
     */
    public function __construct(User $user, string $channel = 'phone')
    {
        $this->user = $user;
        $this->channel = $channel;
    }
}