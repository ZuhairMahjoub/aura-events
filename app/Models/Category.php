<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Category extends Model
{
        use HasTranslations;
    
    public $translatable=['name'];
     protected $fillable = [
        'governorate_id',
       'name'
    ];

    public function providers()
{
    return $this->belongsToMany(Provider::class, 'category_provider');
}
}
