<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
;
class CompanyDetail extends Model
{
    use HasFactory,HasUlids;



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