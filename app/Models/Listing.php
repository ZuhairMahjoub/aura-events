<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Listing extends Model
{
use HasUlids, HasFactory;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'provider_id', 'category_id', 'district_id', 'title', 
        'description', 'listing_type', 'material_composition', 
        'secondary_contact_number', 'cancel_before_acceptance', 
        'cancel_after_acceptance', 'cancel_before_payment', 
        'is_provider_location_based', 'moderation_status', 'rejection_reason'
    ];

    protected $casts = [
        'title' => 'array',
        'description' => 'array',
        'cancel_before_acceptance' => 'boolean',
        'cancel_after_acceptance' => 'boolean',
        'cancel_before_payment' => 'boolean',
        'is_provider_location_based' => 'boolean',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(\App\Models\District::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ListingVariant::class);
    }

    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }
}