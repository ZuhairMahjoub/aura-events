<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FreelancerDetail extends Model
{
    use HasUlids, HasFactory;

    protected $fillable = ['provider_id', 'national_id', 'experience_years'];

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }
}