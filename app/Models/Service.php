<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Service extends Model
{
    use HasUlids, HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'description',
    ];

    /**
     * الخدمة تابعة لشركة وحدة (owner)
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'company_id');
    }

    /**
     * الخدمة ممكن يرتبط فيها عدة عروض وظائف
     */
    public function jobOffers(): HasMany
    {
        return $this->hasMany(JobOffer::class);
    }
}