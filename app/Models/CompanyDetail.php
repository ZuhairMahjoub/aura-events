<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class CompanyDetail extends Model
{
    use HasUlids;

    protected $fillable = ['provider_id', 'tax_number', 'registration_no'];

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }
}