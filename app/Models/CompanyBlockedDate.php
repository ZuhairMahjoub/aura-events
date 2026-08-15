<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyBlockedDate extends Model
{
    use HasUlids, HasFactory;

    protected $fillable = [
    'company_id',
    'blocked_date',
    'start_time', // 💡 السماح بحفظ وقت البداية
    'end_time',   // 💡 السماح بحفظ وقت النهاية
    'source',
    'booking_id',
    'note',       // 💡 السر هنا! هذا ما سيحل مشكلة الـ null
];

    protected $casts = [
        'blocked_date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'company_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public static function hasConflict(
        string $companyId,
        string $date,
        ?string $startTime = null,
        ?string $endTime = null,
    ): bool {
        $query = static::where('company_id', $companyId)
            ->where('blocked_date', $date);

        if ($startTime === null || $endTime === null) {
            return $query->exists();
        }

        return $query->where(function ($q) use ($startTime, $endTime) {
            $q->whereNull('start_time')
                ->orWhere(function ($q2) use ($startTime, $endTime) {
                    $q2->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $startTime);
                });
        })->exists();
    }
}