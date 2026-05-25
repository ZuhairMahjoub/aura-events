<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    public function providers()
{
    return $this->belongsToMany(Provider::class, 'category_provider');
}
}
