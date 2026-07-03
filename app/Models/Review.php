<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Review extends Model
{
    use HasUlids;

    protected $fillable = ['booking_id', 'reviewer_type', 'reviewer_id', 'reviewee_type', 'reviewee_id', 'rating', 'comment'];

    protected $casts = ['rating' => 'integer'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function reviewer(): MorphTo
    {
        return $this->morphTo();
    }

    public function reviewee(): MorphTo
    {
        return $this->morphTo();
    }
}