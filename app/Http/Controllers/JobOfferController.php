<?php

namespace App\Http\Controllers;

use App\Services\JobOfferService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class JobOfferController extends Controller
{
    protected JobOfferService $jobOfferService;

    // حقن السيرفيس داخل الكنترولر تلقائياً
    public function __construct(JobOfferService $jobOfferService)
    {
        $this->jobOfferService = $jobOfferService;
    }

    /**
     * [الشاشة الغامقة] نشر وظيفة جديدة
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'job_title' => ['required', 'string', 'max:255'],
            'time_condition' => ['required', 'in:Permanent,Temporary,Contract'],
            'event_type' => ['required', 'string'],
            'job_start_date' => ['required', 'date', 'after_or_equal:today'],
            'application_deadline' => ['required', 'date', 'after_or_equal:today'],
            'salary' => ['required', 'numeric', 'min:0'],
            'payment_system' => ['required', 'in:Per Event,Monthly,Hourly'],
            'specific_event_association' => ['nullable', 'string'],
            'experience_level' => ['required', 'in:Junior,Mid,Senior'],
            'company_equipment_provided' => ['required', 'boolean'],
            'job_requirements_and_scope' => ['required', 'string'],
            'contact_info' => ['required', 'string'],
        ]);

$company = $request->user()->providerProfile;       // التحقق من أن الحساب شركة ومسجل بشكل صحيح مع تنظيف النص
if (!$company || trim(strtolower($company->provider_type)) !== 'company') {
    return response()->json([
        'message' => 'عذراً، هذا الإجراء متاح فقط لحسابات الشركات.',
        'debug_current_type' => $company ? $company->provider_type : 'null' // سيكشف لكِ ماذا يقرأ السيرفر بالضبط لو فشل
    ], 403);
}
        // استدعاء السيرفيس للحفظ
        $jobOffer = $this->jobOfferService->createJobOffer($validated, $company->id);

        return response()->json([
            'message' => 'تم نشر عرض العمل بنجاح ونقله إلى صفحة الطلبات.',
            'data' => $jobOffer
        ], 201);
    }

    /**
     * [الشاشة الفاتحة] جلب المتقدمين
     */
    public function getApplicants(Request $request): JsonResponse
    {
$company = $request->user()->providerProfile;       // التحقق من أن الحساب شركة ومسجل بشكل صحيح مع تنظيف النص
        // التحقق من أن الحساب شركة ومسجل بشكل صحيح مع تنظيف النص
if (!$company || trim(strtolower($company->provider_type)) !== 'company') {
    return response()->json([
        'message' => 'عذراً، هذا الإجراء متاح فقط لحسابات الشركات.',
        'debug_current_type' => $company ? $company->provider_type : 'null' // سيكشف لكِ ماذا يقرأ السيرفر بالضبط لو فشل
    ], 403);
}

        $applicants = $this->jobOfferService->getCompanyApplicants($company->id);

        return response()->json(['data' => $applicants], 200);
    }

    /**
     * [أزرار الشاشة الفاتحة] قبول أو رفض طلب
     */
    public function updateApplicantStatus(Request $request, $contractId): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:active,rejected'],
        ]);

$company = $request->user()->providerProfile;       // التحقق من أن الحساب شركة ومسجل بشكل صحيح مع تنظيف النص
        if (!$company || $company->provider_type !== 'company') {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        $contract = $this->jobOfferService->updateApplicantStatus($contractId, $company->id, $request->status);
        $statusLabel = $request->status === 'active' ? 'مؤكد (Confirmed)' : 'مرفوض (Rejected)';

        return response()->json([
            'message' => "تم تحديث حالة المتقدم بنجاح إلى: {$statusLabel}.",
            'data' => $contract
        ], 200);
    }
// في App\Services\JobOfferService.php

// في App\Http\Controllers\JobOfferController.php

public function index(): JsonResponse
{
    $jobOffers = $this->jobOfferService->getAllJobOffers();

    return response()->json([
        'success' => true,
        'data' => $jobOffers
    ], 200);
}
    /**
     * [خاص بالتطبيق] فريلانسر يقدم على وظيفة
     */
    public function apply(Request $request, $jobOfferId): JsonResponse
    {
$freelancer = $request->user()->providerProfile;       // التحقق من أن الحساب شركة ومسجل بشكل صحيح مع تنظيف النص
        if (!$freelancer || $freelancer->provider_type !== 'freelancer') {
            return response()->json(['message' => 'يجب أن يكون حسابك من نوع فريلانسر لتقديم طلب توظيف.'], 403);
        }

        $application = $this->jobOfferService->applyToJob($jobOfferId, $freelancer->id);

        if (!$application) {
            return response()->json(['message' => 'لقد قمت بالتقديم على هذه الوظيفة مسبقاً.'], 400);
        }

        return response()->json([
            'message' => 'تم تقديم طلبك بنجاح، وظهر الآن في لوحة تحكم الشركة.',
            'data' => $application
        ], 201);
    }
}