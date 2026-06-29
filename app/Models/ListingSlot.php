<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ListingSlot extends Model
{
    use HasFactory, HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'listing_availability_id',
        'slot_name',
        'start_time',
        'end_time',
        'remaining_capacity',
    ];

    // Fix #7: Changed 'datetime:H:i' to 'string' for time-only columns
    // 'datetime' cast forces a full Carbon instance, mangling pure time values
    protected function casts(): array
    {
        return [
            'id'                 => 'string',
            'slot_name'          => 'array',
            'start_time'         => 'string',  // stored as "HH:MM:SS", returned as string
            'end_time'           => 'string',
            'remaining_capacity' => 'integer',
        ];
    }

    public function availability(): BelongsTo
    {
        return $this->belongsTo(ListingAvailability::class, 'listing_availability_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'listing_slot_id');
    }
}