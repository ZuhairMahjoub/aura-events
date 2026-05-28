<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    protected $fillable = [
        'governorate_id',
        'name_ar',
        'name_en'
    ];

   
    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function providers(): HasMany
    {
        return $this->hasMany(CompanyDetail::class);
    }
    public function Listing(){
        return $this->hasMany(Listing::class);
    }
}