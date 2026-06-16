<?php


namespace App\Models;

use App\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Provider extends Model
{
    use HasUlids , HasFactory;

    protected $fillable = [
        'user_id',
        'brand_name',
        'provider_type',
        'rating',
        'is_verified',
        'district_id',      // 👈 ضيف هاد الحقل فوراً
        'address_details',   // 👈
        'is_active'
    ];
// في app/Models/Provider.php
public function activeContracts()
{
    return $this->hasMany(CompanyFreelancerContract::class, 'freelancer_id')
                ->where('status', 'active');
}
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categories()
{
    return $this->belongsToMany(Category::class, 'category_provider');
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
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
