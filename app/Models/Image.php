<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class Image extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = ['path', 'alt_text'];

    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
     protected static function booted(): void
    {
        static::deleting(function (Image $image) {
            if ($image->path && Storage::disk('public')->exists($image->path)) {
                Storage::disk('public')->delete($image->path);
            }
        });
    }

    protected function fullUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->path
                ? Storage::disk('public')->url($this->path)
                : null,
        );
    }
}