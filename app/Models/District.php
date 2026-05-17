<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    // الحقول القابلة للتعبئة بناءً على المايجريشن "الأخضر"
    protected $fillable = [
        'governorate_id',
        'name_ar',
        'name_en'
    ];

    /**
     * الحي ينتمي لمنطقة واحدة (Area)
     */
    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    /**
     * الحي الواحد يمكن أن يتواجد فيه العديد من المزودين
     */
    public function providers(): HasMany
    {
        return $this->hasMany(Provider::class);
    }
}