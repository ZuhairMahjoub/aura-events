<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    use HasUlids;
    protected $fillable=[
        'user_id',
        'device_token'
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }
}
