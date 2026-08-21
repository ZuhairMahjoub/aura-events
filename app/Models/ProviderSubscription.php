<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderSubscription extends Model
{
    use HasUlids;

    protected $fillable = [
        'provider_id',
        'current_period_starts_at',
        'current_period_ends_at',
        'payment_status',
        'amount',
        'currency',
        'confirmed_by',
        'confirmed_at',
        'admin_notes',
    ];

    protected $casts = [
        'current_period_starts_at' => 'date',
        'current_period_ends_at'   => 'date',
        'confirmed_at'             => 'datetime',
        'amount'                   => 'decimal:2',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isExpired(): bool
    {
        return $this->current_period_ends_at->isPast();
    }
}