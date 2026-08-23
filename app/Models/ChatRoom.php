<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids; 

class ChatRoom extends Model
{
    use HasUlids;  

    protected $fillable = ['firebase_chat_id']; 

    public function participants()
    {
        return $this->belongsToMany(User::class, 'chat_room_participants', 'chat_room_id', 'user_id');
    }
}