<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles; // مكتبة Spatie

use App\Models\ServiceProviderProfile;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as AuthCanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail, AuthCanResetPassword
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasUlids, HasRoles, HasApiTokens, CanResetPassword;

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
        'phone_verified_at',
        'email_verified_at', // تمت إضافته لكي يسمح بتحديثه عند التسجيل عبر جوجل
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
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * تحديد الـ Guard الافتراضي لـ Spatie.
     * إذا كنت تستخدم Sanctum كـ API بالكامل، فثبيته على 'api' ممتاز.
     * نصيحة: إذا واجهتك مشكلة في التعرف على الأدوار مستقبلاً، يمكنك تحويلها إلى مصفوفة: ['web', 'api']
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
     * التحقق مما إذا كان الهاتف مفعلاً
     */
    public function hasVerifiedPhone(): bool
    {
        return ! is_null($this->phone_verified_at);
    }

    /**
     * علاقة المستخدم بالمدينة
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * علاقة المورفولوجيا للصور
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    /**
     * علاقة المورفولوجيا للعناوين
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    /**
     * توثيق رقم الهاتف وتحديث الوقت
     */
    public function markPhoneAsVerified()
    {
        return $this->forceFill([
            'phone_verified_at' => $this->freshTimestamp(),
        ])->save();
    }
}