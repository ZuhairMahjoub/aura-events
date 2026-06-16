<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasUlids;
    protected $fillable = [
        'name',
        'guard_name',
    ];
    protected $casts = [
        'id' => 'string',
    ];
    public $incrementing = false;
    protected $keyType = 'string';
    
}