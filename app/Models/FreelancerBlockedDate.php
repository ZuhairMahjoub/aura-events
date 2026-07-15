<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreelancerBlockedDate extends Model
{
    use HasUlids, HasFactory;

    protected $fillable = [
        'freelancer_id',
        'blocked_date',
        'source',
        'booking_id',
    ];

    protected $casts = [
        'blocked_date' => 'date',
    ];

    /**
     * التاريخ المحجوز تابع لفريلانسر وحد
     */
    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'freelancer_id');
    }

    /**
     * لو كان source = booking، بيرتبط بالحجز المسبّب
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}