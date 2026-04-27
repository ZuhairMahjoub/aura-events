<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Governorate extends Model
{
    protected $fillable = [
        'name_en',
        'name_ar'];
    public function cities(){
        return $this->hasMany(City::class);
    }
}
