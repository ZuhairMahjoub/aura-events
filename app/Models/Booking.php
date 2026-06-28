<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Booking extends Model
{
    use HasUlids, SoftDeletes, HasFactory;

    // أضف هذه المصفوفة إلى الموديل
    protected $fillable = [

        'user_id',
        'provider_id',
        'listing_id',
        'listing_variant_id',
        'listing_slot_id',
        'booking_type',
        'status',
        'payment_status',
        'quantity',
        'total_price',
        'currency',
        'booked_date',
        'booked_start_time',
        'booked_end_time',
        'metadata',
        'customer_notes',
        'cancelled_at',
        'cancellation_reason',
        'cancelled_by',
        'payment_reference',
    ];

    // إذا كنت تستخدم JSON في metadata، يفضل إضافة هذا الكاست
    protected $casts = [
        'metadata' => 'array',
    ];

    // الـ Relationships
    public function provider() {
        return $this->belongsTo(Provider::class);
    }

    public function listing() {
        return $this->belongsTo(Listing::class);
    }
    public function variant() {
        return $this->belongsTo(ListingVariant::class, 'listing_variant_id');
    }
    public function slot() {
        return $this->belongsTo(ListingSlot::class, 'listing_slot_id');
    }
    public function user() {
        return $this->belongsTo(User::class);  }
            /**
     * تحويل بيانات الطلب إلى DTO.
     * هذا هو الحل للخطأ الذي يظهر لك حالياً.
     */
    public static function fromRequest(array $validated, string $userId): \App\DTOs\BookingData
    {
        return \App\DTOs\BookingData::fromRequest($validated, $userId);
    }

}