<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $fillable = [
        'name_en',
        'name_ar',
        'governorate_id'
    ];
    public function users()
    {
        return $this->hasMany(User::class);
    }
    public function governorate() {
        return $this->belongsTo(Governorate::class);  
    }
    public function addresses(){
        return $this->hasMany(Address::class);
    }
}
