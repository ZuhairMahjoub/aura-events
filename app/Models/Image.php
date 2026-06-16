<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Image extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = ['path', 'alt_text'];

    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * الـ path في DB مخزَّن كـ "uploads/arrangements/.../photo.jpg"
     * url() يحوله لـ "http://127.0.0.1:8000/uploads/arrangements/.../photo.jpg"
     * بدون الحاجة لـ symlink أو Storage facade
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->path ? url($this->path) : null,
        );
    }
 
}
