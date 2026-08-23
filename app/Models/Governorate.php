<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Governorate extends Model
{
    use HasTranslations;
    public $translatable=['name'];
    // الحقول القابلة للتعبئة بناءً على المايجريشن
    protected $fillable = [
        'name'
        
    ];

    /**
     * المحافظة تحتوي على العديد من المناطق (Areas)
     */
    // protected $casts = [
    //     'name' => 'array',
      
    // ];
    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }
}