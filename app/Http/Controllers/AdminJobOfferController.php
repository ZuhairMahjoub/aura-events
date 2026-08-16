<?php

namespace App\Http\Controllers;

use App\Services\JobOfferService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminJobOfferController extends Controller
{
    protected JobOfferService $jobOfferService;

    public function __construct(JobOfferService $jobOfferService)
    {
        $this->jobOfferService = $jobOfferService;
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

        return response()->json([
            'status'  => true,
            'message' => 'تم رفض عرض العمل، وتم تسجيل سبب الرفض.',
            'data'    => $jobOffer,
        ], 200);
    }
}