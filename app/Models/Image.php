<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Image extends Model
{
    use HasUlids;

    protected $fillable = ['path', 'alt_text'];

    public function imageable()
    {
        return $this->morphTo();
    }
}
