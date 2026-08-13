<?php

namespace App\Services;

use App\Models\JobOffer;
use App\Models\CompanyFreelancerContract;
use Illuminate\Database\Eloquent\Collection;

class JobOfferService
{
    /**
     * إنشاء عرض عمل جديد للشركة (الشاشة الغامقة)
     */
    public function createJobOffer(array $data, string $companyId): JobOffer
    {
        return JobOffer::create(array_merge($data, [
            'company_id' => $companyId
        ]));
    }
    /**
     * Get a specific job offer by ID
     */
    public function getJobOfferById($id)
    {
        return JobOffer::with(['provider', 'service'])
            ->withCount('applications')
            ->find($id);
    }
    public function getFreelancerAppliedJobs(string $freelancerId)
    {
        return CompanyFreelancerContract::where('freelancer_id', $freelancerId)
            ->with([
                'jobOffer', // جلب تفاصيل الوظيفة التي تم التقديم عليها
                'jobOffer.provider:id,brand_name' // (اختياري) جلب اسم الشركة الناشرة للوظيفة أيضاً
            ])
            ->orderBy('created_at', 'desc')
            ->get();
    }
    /**
     * جلب المتقدمين لوظائف الشركة (الشاشة الفاتحة)
     */
    public function getCompanyApplicants(string $companyId): Collection
    {
        return JobOffer::where('company_id', $companyId)
            ->with([
                'service:id,name,description', 
                // 💡 التعديل هنا: فلترة الطلبات لتجلب فقط التي حالتهم pending
                'applications' => function ($query) {
                    $query->where('status', 'pending');
                },
                'applications.freelancer.user',             
                'applications.freelancer.freelancerDetails',  
                'applications.freelancer.categories'       
            ])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * تحديث حالة المتقدم (قبول / رفض من أزرار الشاشة الفاتحة)
     */
    public function updateApplicantStatus(string $contractId, string $companyId, string $status): CompanyFreelancerContract
    {
        $contract = CompanyFreelancerContract::where('id', $contractId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $contract->update(['status' => $status]);

        return $contract;
    }

    /**
     * تقديم الفريلانسر على وظيفة
     */
    public function applyToJob(string $jobOfferId, string $freelancerId): ?CompanyFreelancerContract
    {
        $jobOffer = JobOffer::findOrFail($jobOfferId);

        // لا يمكن التقديم إذا الشركة عطّلت العرض
        if (!$jobOffer->is_active) {
            return null;
        }

        // التحقق من عدم التقديم المسبق
        $exists = CompanyFreelancerContract::where('freelancer_id', $freelancerId)
            ->where('job_offer_id', $jobOffer->id)
            ->exists();

        if ($exists) {
            return null;
        }

        return CompanyFreelancerContract::create([
            'company_id' => $jobOffer->company_id,
            'freelancer_id' => $freelancerId,
            'job_offer_id' => $jobOffer->id,
            'status' => 'pending',
        ]);
    }
    public function getAllJobOffers()
{
    return \App\Models\JobOffer::with('provider:id,brand_name')
        // جلب الوظائف المفعّلة فقط
        ->where('is_active', true)
        ->latest()
        ->paginate(15);
}

    /**
     * تفعيل / تعطيل عرض العمل من قبل الشركة صاحبته
     */
    public function toggleActive(string $jobOfferId, string $companyId): JobOffer
    {
        $jobOffer = JobOffer::where('id', $jobOfferId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $jobOffer->update(['is_active' => !$jobOffer->is_active]);

        return $jobOffer;
    }
}
