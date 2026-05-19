<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as AuthCanResetPassword;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements MustVerifyEmail, AuthCanResetPassword
{
    use HasFactory, Notifiable, HasUlids, HasRoles, HasApiTokens, CanResetPassword,SoftDeletes;
public $incrementing = false; // لتعطيل الزيادة التلقائية للـ ID
public $keyType = 'string'; // لتحديد نوع الـ ID كـ string (UL
    /**
     * الحقول القابلة للتعبئة.
     * تم تحديثها لتشمل account_type و is_profile_completed.
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'account_type',       // [customer, provider]
        'status',             // [active, inactive, banned]
        'is_profile_completed', //
        'settings_language',
        'settings_theme',
        'provider',           // لدعم Social Login
        'provider_id',        // لدعم Social Login
        'phone_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_profile_completed' => 'boolean',
        ];
    }

    protected $guard_name = 'api';

    // --- العلاقات المحدثة (Relationships) ---

    /**
     * العلاقة مع موديل Provider الجديد بدلاً من ServiceProviderProfile القديم.
     */
    public function provider(): HasOne
    {
        // تم استبدال ServiceProviderProfile بـ Provider بناءً على التعديل الأخير
        return $this->hasOne(Provider::class);
    }

    /**
     * علاقة الصور المتعددة (Polymorphic).
     * تم تحديث الاسم ليتوافق مع المايجريشن (mediable).
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'mediable');
    }

    // --- وظائف مساعدة (Helpers) ---

    public function hasVerifiedPhone(): bool
    {
        return ! is_null($this->phone_verified_at);
    }

    public function markPhoneAsVerified()
    {
        return $this->forceFill([
            'phone_verified_at' => $this->freshTimestamp(),
        ])->save();
    }
}