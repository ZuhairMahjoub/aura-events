<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
  protected $fillable = [
    'user_id',
    'company_name',
    'company_description',
    'portfolio_url',
    'is_verified',
    'onboarding_completed',
];

    public function city()
    {
        return $this->belongsTo(City::class);
    }
    public function addresable()
    {
        return $this->morphTo();
    }
}
