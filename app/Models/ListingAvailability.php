<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ListingAvailability extends Model
{
    use HasUlids,SoftDeletes;

    protected $keyType = 'string';
public $incrementing = false;
    protected $fillable = [
        'listing_variant_id', 
        'available_date', 
        'end_date',
        'start_time', 
        'end_time', 
        'remaining_capacity', 
        'is_blocked'
    ];

    protected $casts = [
        'available_date' => 'date',
        'end_date'       => 'date',
        'is_blocked'     => 'boolean',
    ];

   
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ListingVariant::class, 'listing_variant_id');
    }
    public function slots()
    {
        return $this->hasMany(ListingSlot::class, 'listing_availability_id');
    }   
}