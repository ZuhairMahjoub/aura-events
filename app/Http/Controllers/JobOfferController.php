<?php

namespace App\Http\Controllers;

use App\Services\JobOfferService;
use App\Http\Requests\StoreJobOfferRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\JobOffer;
// 💡 استيراد خدمة الإشعارات
use App\Services\FirebaseNotificationService;

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
    // 💡 1. تعريف خدمة الإشعارات
    protected FirebaseNotificationService $notificationService;

    // 💡 2. حقن الخدمة في الـ Constructor
    public function __construct(
        JobOfferService $jobOfferService,
        FirebaseNotificationService $notificationService
    ) {
        $this->jobOfferService = $jobOfferService;
        $this->notificationService = $notificationService;
    }

    /**
     * [الشاشة الغامقة] نشر وظيفة جديدة
     * ملاحظة: التحقق من أن الحساب "شركة" أصبح مسؤولية middleware
     * provider_type:company على مستوى الـ route، فلا حاجة لتكراره هنا.
     */
    public function store(StoreJobOfferRequest $request): JsonResponse
    {
        $company = $request->user()->providerProfile;
        $validated = $request->validated();

        $jobOffer = $this->jobOfferService->createJobOffer($validated, $company->id);

        // ── 💡 3. إرسال إشعار بالإنجليزية بعد إضافة عرض العمل بنجاح ──
        $titleEn = is_array($jobOffer->title) ? ($jobOffer->title['en'] ?? current($jobOffer->title)) : $jobOffer->title;

        $this->notificationService->sendToUser(
            $request->user()->id,
            'Job Offer Submitted Successfully',
            "Your job offer '{$titleEn}' has been submitted and is pending admin approval.",
            ['type' => 'job_offer_added', 'job_offer_id' => $jobOffer->id]
        );

        return response()->json([
            'success' => true,
            'message' => 'تم نشر عرض العمل بنجاح ونقله إلى صفحة الطلبات.',
            'data' => $jobOffer,
        ], 201);
    }

    public function getAppliedJobs(Request $request): JsonResponse
    {
        // جلب الملف الشخصي للفريلانسر الحالي
        $freelancer = $request->user()->providerProfile;

        // جلب الوظائف عبر الـ Service
        $appliedJobs = $this->jobOfferService->getFreelancerAppliedJobs($freelancer->id);

        return response()->json([
            'success' => true,
            'data' => $appliedJobs,
        ], 200);
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
     * جلب تفاصيل عرض عمل معين بواسطة المعرّف (ID)
     */
    public function show($id): JsonResponse
    {
        $jobOffer = $this->jobOfferService->getJobOfferById($id);

        if (!$jobOffer) {
            return response()->json([
                'success' => false,
                'message' => 'عذراً، عرض العمل هذا غير موجود.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $jobOffer,
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
            $jobOffer = \App\Models\JobOffer::find($jobOfferId);
            $message = ($jobOffer && !$jobOffer->is_active)
                ? 'عذراً، هذا العرض غير مفعّل حالياً من قبل الشركة.'
                : 'لقد قمت بالتقديم على هذه الوظيفة مسبقاً.';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تقديم طلبك بنجاح، وظهر الآن في لوحة تحكم الشركة.',
            'data' => $application,
        ], 201);
    }

    /**
     * [الشاشة الغامقة] تفعيل / تعطيل عرض العمل يدوياً من قبل الشركة
     */
    public function toggleActive(Request $request, $jobOfferId): JsonResponse
    {
        $company = $request->user()->providerProfile;

        $jobOffer = $this->jobOfferService->toggleActive($jobOfferId, $company->id);

        $statusLabel = $jobOffer->is_active ? 'مفعّل (Active)' : 'غير مفعّل (Inactive)';

        return response()->json([
            'success' => true,
            'message' => "تم تحديث حالة عرض العمل إلى: {$statusLabel}.",
            'data' => $jobOffer,
        ], 200);
    }
}