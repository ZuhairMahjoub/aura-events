<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'district_id',
        'address_details',
        'tax_number',
        'registration_no',
        'created_at',
        'updated_at',
        
    ];
     public function provider()
    {
        return $this->belongsTo(Provider::class);
    }
    public function district()
    {
        return $this->belongsTo(District::class);
    }
}