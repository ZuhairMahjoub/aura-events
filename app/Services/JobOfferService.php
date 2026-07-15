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
        // Adjust model name or eager loading (e.g., with('company')) if needed
        return JobOffer::find($id); 
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
        return CompanyFreelancerContract::where('company_id', $companyId)
            ->with([
                'freelancer.user:id,first_name,last_name,email',
                'jobOffer:id,job_title,service_id',
                'jobOffer.service:id,name,description'
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

        // التحقق من عدم التقديم المسبق
        $exists = CompanyFreelancerContract::where('freelancer_id', $freelancerId)
            ->where('job_offer_id', $jobOffer->id)
            ->exists();

        if ($exists) {
            return null; // تعني أنه تقدم مسبقاً وسيتعامل معها الكنترولر ليعيد خطأ
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
    // جلب الوظائف مع بيانات الشركة الناشرة لها
    return \App\Models\JobOffer::with('provider:id,brand_name')
         // جلب الوظائف النشطة فقط
        ->latest()
        ->paginate(15);
}
}