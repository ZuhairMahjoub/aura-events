<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Governorate extends Model
{
    // الحقول القابلة للتعبئة بناءً على المايجريشن
    protected $fillable = [
        'name_ar',
        'name_en'
    ];

    /**
     * المحافظة تحتوي على العديد من المناطق (Areas)
     */
    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }
}