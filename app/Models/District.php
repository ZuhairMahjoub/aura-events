<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class District extends Model
{
    use HasTranslations;
    
    public $translatable=['name'];

    protected $fillable = [
        'governorate_id',
       'name'
    ];
    // protected $casts = [
    //     'name' => 'array',
      
    // ];
    
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