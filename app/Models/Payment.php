<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $booking_id
 * @property string|null $transaction_id
 * @property string $provider
 * @property float $amount
 * @property string $currency
 * @property string $status
 * @property array|null $raw_response
 * @property string|null $proof_file_path
 * @property string|null $admin_notes
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\Booking $booking
 */
class Payment extends Model
{
    use HasUlids;

    // داخل Payment.php
    protected $fillable = [
        'booking_id',
        'transaction_id',
        'provider',
        'amount',
        'currency',
        'status',
        'raw_response',
        'proof_file_path', // افصل بينهما بفاصلة
    ];

    protected $casts = [
        'raw_response' => 'array', // ضروري جداً لأن الحقل من نوع JSON
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}