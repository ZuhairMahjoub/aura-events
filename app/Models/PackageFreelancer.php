<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageFreelancer extends Model
{
    use HasUlids;

    protected $fillable = [
        'package_variant_id',
        'freelancer_id',
        'contract_id',
    ];

    /**
     * يتبع لمتغير باقة معين (Listing Variant من نوع Package)
     */
    public function packageVariant(): BelongsTo
    {
        return $this->belongsTo(ListingVariant::class, 'package_variant_id');
    }

    /**
     * يتبع للفريلانسر المشترك في الباقة
     */
    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'freelancer_id');
    }

    /**
     * يتبع لعقد التوظيف الأصلي المبرم بين الفريلانسر والشركة
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(CompanyFreelancerContract::class, 'contract_id');
    }
}