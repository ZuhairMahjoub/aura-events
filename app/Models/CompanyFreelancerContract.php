<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyFreelancerContract extends Model
{
    use HasUlids;

    protected $fillable = [
        'company_id',
        'freelancer_id',
        'job_offer_id',
        'status',
    ];

    /**
     * علاقة الطلب بالشركة المستضيفة
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'company_id');
    }

    /**
     * علاقة الطلب بالفريلانسر المتقدم
     */
    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'freelancer_id');
    }

    /**
     * علاقة طلب التوظيف بالإعلان/الوظيفة الأصلية المتقدم عليها
     */
    public function jobOffer(): BelongsTo
    {
        return $this->belongsTo(JobOffer::class, 'job_offer_id');
    }

    /**
     * علاقة العقد بالباقات التي يشارك فيها الفريلانسر لاحقاً (اختياري مالي)
     */
    public function packageFreelancers(): HasMany
    {
        return $this->hasMany(PackageFreelancer::class, 'contract_id');
    }
}