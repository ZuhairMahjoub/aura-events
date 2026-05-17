<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Provider extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'district_id',
        'brand_name',
        'provider_type',
        'rating',
        'is_verified',
        'is_active'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function companyDetails(): HasOne
    {
        return $this->hasOne(CompanyDetail::class);
    }

    public function freelancerDetails(): HasOne
    {
        return $this->hasOne(FreelancerDetail::class);
    }

    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'mediable');
    }
}
