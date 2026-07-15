<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JobOffer extends Model
{
    use HasUlids , HasFactory;

    protected $fillable = [
        'company_id', 'service_id', 'job_title', 'time_condition', 'event_type', 
        'job_start_date', 'application_deadline', 'salary', 
        'payment_system', 'specific_event_association', 'experience_level', 
        'company_equipment_provided', 'job_requirements_and_scope', 'contact_info'
    ];

    // الوظيفة تابعة لشركة
    public function company(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'company_id');
    }

    // الوظيفة تقدم عليها العديد من الفريلانسرز
    public function applications(): HasMany
    {
        return $this->hasMany(CompanyFreelancerContract::class, 'job_offer_id');
    }
    // في App\Models\JobOffer.php
public function provider()
{
    return $this->belongsTo(\App\Models\Provider::class, 'company_id');
}
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}