<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasUlids;
    protected $fillable = [
        'user_id',
        'title',
        'body',
        'data',
        'is_read',
    ];
    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
    ];
    public function user(){
        return $this->belongsTo(User::class);
    }
}
