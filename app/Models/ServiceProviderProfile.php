<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceProviderProfile extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'company_description',
        'portfolio_url',
        'is_verified',
        'onboarding_completed',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
