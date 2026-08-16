<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreelancerBlockedDate extends Model
{
    use HasUlids, HasFactory;

    protected $fillable = [
        'freelancer_id',
        'blocked_date',
        'start_time',
        'end_time',
        'source',
        'booking_id',
        'note',
    ];

    protected $casts = [
        'blocked_date' => 'date',
    ];

    /**
     * التاريخ المحجوز تابع لفريلانسر وحد
     */
    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'freelancer_id');
    }

    /**
     * لو كان source = booking، بيرتبط بالحجز المسبّب
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * فحص تداخل زمني حقيقي — مش مجرد تطابق تاريخ.
     *
     * $startTime = null يعني "أنا محتاج اليوم كامل" (حظر يدوي، أو حجز بدون
     * وقت محدد) → أي حظر موجود بنفس اليوم (كامل أو جزئي) يُعتبر تعارض.
     *
     * $startTime محدد يعني "أنا محتاج بس هالفترة" → تعارض فقط إذا:
     *   - في حظر يوم كامل موجود أصلاً بنفس التاريخ، أو
     *   - في حظر جزئي بيتقاطع فعلياً مع الفترة المطلوبة
     *     (standard interval overlap: existing.start < new.end AND existing.end > new.start)
     */
    public static function hasConflict(
        string $freelancerId,
        string $date,
        ?string $startTime = null,
        ?string $endTime = null,
    ): bool {
        $query = static::where('freelancer_id', $freelancerId)
            ->where('blocked_date', $date);

        if ($startTime === null || $endTime === null) {
            // محتاجين اليوم كامل → أي صف موجود أصلاً (جزئي أو كامل) = تعارض.
            return $query->exists();
        }

        return $query->where(function ($q) use ($startTime, $endTime) {
            $q->whereNull('start_time') // حظر يوم كامل موجود مسبقاً
                ->orWhere(function ($q2) use ($startTime, $endTime) {
                    // تداخل فترتين زمنيتين
                    $q2->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $startTime);
                });
        })->exists();
    }
}