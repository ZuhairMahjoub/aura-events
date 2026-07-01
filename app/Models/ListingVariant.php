<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ListingVariant extends Model
{
    use HasUlids, SoftDeletes, HasFactory;

    protected $keyType    = 'string';
    public    $incrementing = false;

    protected $fillable = [
        'listing_id',
        'variant_name',
        'price',
        'currency',
        'price_type',
        'stock_quantity',
        'dynamic_attributes',
            'currency',
            'stock_quantity',

    ];

    protected $casts = [
        'variant_name'       => 'array',
        'dynamic_attributes' => 'array',
        'price'              => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(ListingAvailability::class);
    }

    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'listing_variant_id');
    }

    // ── Package relationships ────────────────────────────────────────────────

    /**
     * Items that belong to this variant when it acts as a package container.
     * (This variant IS the package; packageItems are its contents.)
     */
    public function packageItems(): HasMany
    {
        return $this->hasMany(PackageItem::class, 'package_variant_id');
    }

    /**
     * Freelancers attached to this variant when it acts as a package container.
     */
    public function packageFreelancers(): HasMany
    {
        return $this->hasMany(PackageFreelancer::class, 'package_variant_id');
    }

    /**
     * The packages that include this variant as one of their items.
     * (This variant is a component, not the package itself.)
     */
    public function usageInPackages(): HasMany
    {
        return $this->hasMany(PackageItem::class, 'included_variant_id');
    }
}
