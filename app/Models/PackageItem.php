<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;

class PackageItem extends Model
{
    use HasUlids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['package_variant_id', 'included_variant_id', 'quantity'];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function packageVariant()
    {
        return $this->belongsTo(ListingVariant::class, 'package_variant_id');
    }

    public function includedVariant()
    {
        return $this->belongsTo(ListingVariant::class, 'included_variant_id');
    }
}
