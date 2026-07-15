<?php

namespace App\Http\Controllers;

use App\DTOs\ReviewData;
use App\Http\Requests\ReviewStoreRequest;
use App\Models\Provider;
use App\Models\User;
use App\Services\ReviewService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviewService) {}

    public function reviewProvider(ReviewStoreRequest $request): JsonResponse
    {
        return $this->handle(fn () => $this->reviewService->reviewProvider(
            $request->user()->id,
            ReviewData::fromRequest($request->validated())
        ));
    }

    public function reviewOrganizer(ReviewStoreRequest $request): JsonResponse
    {
        $provider = $request->user()->providerProfile;

        if (! $provider) {
            return response()->json(['success' => false, 'message' => 'ملف مزود الخدمة غير موجود.'], 403);
        }

        return $this->handle(fn () => $this->reviewService->reviewOrganizer(
            $provider->id,
            ReviewData::fromRequest($request->validated())
        ));
    }

    public function providerReviews(string $providerId): JsonResponse
    {
        $reviews = $this->reviewService->getRevieweeReviews(Provider::class, $providerId);

        return response()->json(['success' => true, 'data' => $reviews]);
    }

    private function handle(\Closure $action): JsonResponse
    {
        try {
            $review = $action();

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال التقييم بنجاح.',
                'data'    => $review,
            ], 201);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 422);
        }
    }
}