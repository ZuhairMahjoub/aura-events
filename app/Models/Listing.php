<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

class Listing extends Model
{
use HasUlids, HasFactory,HasTranslations;
 public $translatable = ['title', 'description'];
    protected $keyType = 'string';
    public $incrementing = false;

   protected $fillable = [
    'provider_id',
    'category_id',
    'district_id',
    'title',
    'description',
    'listing_type',
    'moderation_status',
    // ✨ تأكد من إضافة هذه الحقول هنا:
    'cancel_before_acceptance',
    'cancel_after_acceptance',
    'cancel_before_payment',
    
    'material_composition',
    'secondary_contact_number',
    'is_provider_location_based',
    'status',
    'rejection_reason',];

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
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites', 'listing_id', 'user_id');
    }
}