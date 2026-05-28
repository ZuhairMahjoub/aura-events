<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids; // 👈 تأكد من استدعاء هذا
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Image extends Model
{
    use HasUlids; // 👈 تفعيل الـ ULID تلقائياً

    public $timestamps = false; // لأن جدولك لا يحتوي على timestamps

    protected $fillable = ['path', 'alt_text'];

    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
}