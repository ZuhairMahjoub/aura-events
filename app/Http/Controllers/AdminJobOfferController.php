<?php

namespace App\Http\Controllers;

use App\Services\JobOfferService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\FirebaseNotificationService;

class AdminJobOfferController extends Controller
{
    protected JobOfferService $jobOfferService;
    protected FirebaseNotificationService $notificationService;

    public function __construct(
        JobOfferService $jobOfferService,
        FirebaseNotificationService $notificationService
    ) {
        $this->jobOfferService = $jobOfferService;
        $this->notificationService = $notificationService;
    }

    public function pendingList(): JsonResponse
    {
        $jobOffers = $this->jobOfferService->getPendingJobOffers();

        return response()->json([
            'status'  => true,
            'data'    => $jobOffers,
        ], 200);
    }

    public function approve($id): JsonResponse
    {
        $jobOffer = $this->jobOfferService->approveJobOffer($id);
        
        $jobOffer->load('provider');

        $titleEn = is_array($jobOffer->title) ? ($jobOffer->title['en'] ?? current($jobOffer->title)) : $jobOffer->title;
        $userId = $jobOffer->provider->user_id;

        $this->notificationService->sendToUser(
            $userId,
            'Job Offer Approved ✅',
            "Your job offer '{$titleEn}' has been approved and is now visible to freelancers.",
            ['type' => 'job_offer_approved', 'job_offer_id' => $jobOffer->id]
        );

        return response()->json([
            'status'  => true,
            'message' => 'تمت الموافقة على عرض العمل، وأصبح ظاهراً للفريلانسرز.',
            'data'    => $jobOffer,
        ], 200);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $jobOffer = $this->jobOfferService->rejectJobOffer($id, $request->rejection_reason);
        
        $jobOffer->load('provider');

        $titleEn = is_array($jobOffer->title) ? ($jobOffer->title['en'] ?? current($jobOffer->title)) : $jobOffer->title;
        $userId = $jobOffer->provider->user_id;

        $this->notificationService->sendToUser(
            $userId,
            'Job Offer Rejected ❌',
            "Unfortunately, your job offer '{$titleEn}' was rejected. Reason: {$request->rejection_reason}",
            ['type' => 'job_offer_rejected', 'job_offer_id' => $jobOffer->id]
        );

        return response()->json([
            'status'  => true,
            'message' => 'تم رفض عرض العمل، وتم تسجيل سبب الرفض.',
            'data'    => $jobOffer,
        ], 200);
    }
}