<?php


namespace App\Models;

use App\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Provider extends Model
{
    use HasUlids, HasFactory;

    protected $fillable = [
        'user_id',
        'brand_name',
        'provider_type',
        'rating',
        'is_verified',
        'district_id',
        'address_details',
        'is_active',
        'moderation_status',
        'rejection_reason',
        'qr_code_path',
        'wallet_balance',
        'policy_accepted_at',

    ];

    protected $casts = [
        'policy_accepted_at' => 'datetime',
        'is_active'          => 'boolean',
        'is_verified'        => 'boolean',
    ];

    public function hasAcceptedPolicy(): bool
    {
        return ! is_null($this->policy_accepted_at);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(ProviderSubscription::class);
    }

    // آخر فترة اشتراك (الأحدث حسب current_period_ends_at)، تُستخدم من
    // scheduler إيقاف التفعيل التلقائي عند انتهاء الاشتراك.
    public function latestSubscription(): HasOne
    {
        return $this->hasOne(ProviderSubscription::class)->latestOfMany('current_period_ends_at');
    }

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

    // الخدمات التي عرّفتها الشركة (Company فقط عملياً، ذات معنى لـ freelancer)
    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'company_id');
    }

    public function companyDetails(): HasOne
    {
        return $this->hasOne(CompanyDetail::class);
    }
    public function getProfileAttribute()
    {
        return $this->companyDetails ?? $this->freelancerDetails;
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
    // في كلا الموديلين
    public function reviewsReceived()
    {
        return $this->morphMany(Review::class, 'reviewee');
    }

    public function reviewsGiven()
    {
        return $this->morphMany(Review::class, 'reviewer');
    }
}