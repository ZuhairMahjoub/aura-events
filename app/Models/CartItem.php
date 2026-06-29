<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory, HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'cart_id', 'listing_id', 'listing_variant_id', 'listing_slot_id',
        'quantity', 'price_snapshot', 'booked_date', 'metadata', 'converted_booking_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'price_snapshot' => 'decimal:2',
        'booked_date' => 'date',
    ];

    public function cart(): BelongsTo { return $this->belongsTo(Cart::class); }
    public function listing(): BelongsTo { return $this->belongsTo(Listing::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ListingVariant::class, 'listing_variant_id'); }
    public function slot(): BelongsTo { return $this->belongsTo(ListingSlot::class, 'listing_slot_id'); }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class, 'converted_booking_id'); }
}
