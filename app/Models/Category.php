<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
       protected $fillable = ['name_ar', 'name_en'];

    public function providers()
{
    return $this->belongsToMany(Provider::class, 'category_provider');
}
}
