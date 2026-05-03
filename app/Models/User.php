<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;


use App\Models\ServiceProviderProfile;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as AuthCanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail,AuthCanResetPassword
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasUlids, HasRoles,HasApiTokens,CanResetPassword;

    /**
     * الحقول القابلة للتعبئة.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'city_id',
        'password',
        'settings_language',
        'provider',
        'provider_id',
        'settings_theme',
    ];

    /**
     * الحقول المخفية عند التحويل لـ JSON.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * تحويل البيانات (Casting).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * تحديد الـ Guard الافتراضي لـ Spatie.
     */
    protected $guard_name = 'api';

    // --- العلاقات (Relationships) ---

    /**
     * علاقة مستخدم بملف مقدم الخدمة.
     */
    public function serviceProviderProfile(): HasOne
    {
        return $this->hasOne(ServiceProviderProfile::class);
    }

    /**
     * 
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * 
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    /**
     * 
     */
    public function addresses(): MorphMany
    {
        
        return $this->morphMany(Address::class, 'addressable');
    }
}