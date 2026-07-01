<?php

namespace App\Http\Controllers;

use App\Services\JobOfferService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * إصلاحات مطبقة على هذا الملف:
 * - إزالة 'debug_current_type' من كل الردود (كان يسرّب قيمة provider_type
 *   الفعلية من قاعدة البيانات لأي طالب غير مصرح، بدون أي فائدة إنتاجية —
 *   بقايا تصحيح أخطاء نُسيت).
 * - إزالة فحص "نوع الحساب" المكرر يدوياً 4 مرات بصيغ غير متطابقة (بعضها
 *   trim(strtolower()) وبعضها لا) لصالح middleware واحد موحّد
 *   (provider_type:company / provider_type:freelancer) مطبَّق من routes/api.php.
 *   هذا يضمن نفس السلوك تماماً بكل مكان، ويمنع تكرار الخطأ لاحقاً.
 * - ربط application_deadline بـ job_start_date (انظر التحقق أدناه).
 */
class JobOfferController extends Controller
{
    protected JobOfferService $jobOfferService;

    public function __construct(JobOfferService $jobOfferService)
    {
        $this->jobOfferService = $jobOfferService;
    }

    /**
     * [الشاشة الغامقة] نشر وظيفة جديدة
     * ملاحظة: التحقق من أن الحساب "شركة" أصبح مسؤولية middleware
     * provider_type:company على مستوى الـ route، فلا حاجة لتكراره هنا.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'job_title' => ['required', 'string', 'max:255'],
            'time_condition' => ['required', 'in:Permanent,Temporary,Contract'],
            'event_type' => ['required', 'string'],
            'job_start_date' => ['required', 'date', 'after_or_equal:today'],
            // إصلاح: كان يُتحقق من application_deadline مقابل "اليوم" فقط，
            // دون أي علاقة بـ job_start_date. هذا كان يسمح بنشر وظيفة يكون
            // فيها الموعد النهائي للتقديم بعد تاريخ بدء العمل الفعلي —
            // منطقياً غير سليم رغم كونه مقبولاً تقنياً سابقاً.
            'application_deadline' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:job_start_date'],
            'salary' => ['required', 'numeric', 'min:0'],
            'payment_system' => ['required', 'in:Per Event,Monthly,Hourly'],
            'specific_event_association' => ['nullable', 'string'],
            'experience_level' => ['required', 'in:Junior,Mid,Senior'],
            'company_equipment_provided' => ['required', 'boolean'],
            'job_requirements_and_scope' => ['required', 'string'],
            'contact_info' => ['required', 'string'],
        ]);

        $company = $request->user()->providerProfile;

        $jobOffer = $this->jobOfferService->createJobOffer($validated, $company->id);

        return response()->json([
            'success' => true,
            'message' => 'تم نشر عرض العمل بنجاح ونقله إلى صفحة الطلبات.',
            'data' => $jobOffer,
        ], 201);
    }

    /**
     * [الشاشة الفاتحة] جلب المتقدمين
     */
    public function getApplicants(Request $request): JsonResponse
    {
        $company = $request->user()->providerProfile;

        $applicants = $this->jobOfferService->getCompanyApplicants($company->id);

        return response()->json([
            'success' => true,
            'data' => $applicants,
        ], 200);
    }

    /**
     * [أزرار الشاشة الفاتحة] قبول أو رفض طلب
     */
    public function updateApplicantStatus(Request $request, $contractId): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:active,rejected'],
        ]);

        $company = $request->user()->providerProfile;

        $contract = $this->jobOfferService->updateApplicantStatus($contractId, $company->id, $request->status);
        $statusLabel = $request->status === 'active' ? 'مؤكد (Confirmed)' : 'مرفوض (Rejected)';

        return response()->json([
            'success' => true,
            'message' => "تم تحديث حالة المتقدم بنجاح إلى: {$statusLabel}.",
            'data' => $contract,
        ], 200);
    }

    public function index(): JsonResponse
    {
        $jobOffers = $this->jobOfferService->getAllJobOffers();

        return response()->json([
            'success' => true,
            'data' => $jobOffers,
        ], 200);
    }

    /**
     * [خاص بالتطبيق] فريلانسر يقدم على وظيفة
     */
    public function apply(Request $request, $jobOfferId): JsonResponse
    {
        $freelancer = $request->user()->providerProfile;

        $application = $this->jobOfferService->applyToJob($jobOfferId, $freelancer->id);

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'لقد قمت بالتقديم على هذه الوظيفة مسبقاً.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تقديم طلبك بنجاح، وظهر الآن في لوحة تحكم الشركة.',
            'data' => $application,
        ], 201);
    }
}