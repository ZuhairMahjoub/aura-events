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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;


class User extends Authenticatable implements AuthCanResetPassword
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
        'email_verified_at',
        'city_id',
        'password',
        'status',
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

    public function providerProfile()
{
    return $this->hasOne(Provider::class, 'user_id', 'id');
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
    // داخل ملف app/Models/User.php

public function provider()
{
    // المعامل الثاني هو اسم العمود الموجود في جدول providers والذي يربطه بالـ users
    return $this->hasOne(Provider::class, 'user_id'); 
}
public function isProvider(): bool
{
    return $this->provider()->exists();
}
   
    public function hasVerifiedPhone(): bool
    {
        return ! is_null($this->phone_verified_at);
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
  
    /**
     */
    public function markPhoneAsVerified()
    {
        return $this->forceFill([
            'phone_verified_at' => $this->freshTimestamp(),
        ])->save();
    }
public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
    /**
     */
    public function sendEmailVerificationNotification()
    {
    }public function notify($instance)
{
    $notificationClass = get_class($instance);

    if (str_contains($notificationClass, 'Verify') || str_contains($notificationClass, 'EmailVerification')) {
        return; 
    }

    parent::notify($instance);
}
    public function deviceTokens(){
        return $this->hasMany(DeviceToken::class);
    }
    public function notifications(){
        return $this->hasMany(Notification::class);
    }
     public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function chatRooms()
{
    return $this->belongsToMany(ChatRoom::class, 'chat_room_participants', 'user_id', 'chat_room_id');
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