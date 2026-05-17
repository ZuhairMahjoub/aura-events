<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class FreelancerDetail extends Model
{
    use HasUlids;

    protected $fillable = ['provider_id', 'national_id', 'experience_years'];

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }
}