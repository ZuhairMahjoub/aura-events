<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasUlids;

    // لضمان أن الموديل يعرف أن المفتاح الأساسي ليس رقماً تلقائياً
    public $incrementing = false;
    protected $keyType = 'string';
}