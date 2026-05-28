<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ListingVariant extends Model
{
    use HasUlids, SoftDeletes;
protected $keyType = 'string';
public $incrementing = false; 
    protected $fillable = [
        'listing_id', 'variant_name',  'price', 
        'currency', 'price_type', 'stock_quantity', 'dynamic_attributes'
    ];

    protected $casts = [
        'variant_name' => 'array',
        'dynamic_attributes' => 'array',
        'price' => 'decimal:2',
    ];

   
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
}