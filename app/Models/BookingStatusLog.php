<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class BookingStatusLog extends Model
{
    use HasUlids;

    public $timestamps = false; // created_at فقط، لا تحديث على هذا الجدول إطلاقاً (Append-only)

    protected $fillable = [
        'booking_id',
        'from_status',
        'to_status',
        'actor_type',
        'actor_id',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}