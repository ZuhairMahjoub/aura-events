<?php

namespace App\Http\Controllers;

use App\DTOs\ReviewData;
use App\Http\Requests\ReviewStoreRequest;
use App\Models\Listing;
use App\Models\Provider;
use App\Models\Review;
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
       public function index(Request $request, Listing $listing): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 10), 1), 50);

        [$revieweeType, $revieweeId] = $this->revieweeForListing($listing);

        $reviews = Review::query()
            ->where('reviewee_type', $revieweeType)
            ->where('reviewee_id', $revieweeId)
            ->with('reviewer')
            ->latest()
            ->paginate($perPage)
            ->through(fn (Review $review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'created_at' => $review->created_at?->toISOString(),
                'reviewer' => [
                    'id' => $review->reviewer_id,
                    'name' => $this->reviewerName($review->reviewer),
                ],
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'listing_id' => $listing->id,
                'rating_summary' => $this->ratingSummary(
                    $this->aggregateForReviewee($revieweeType, $revieweeId)
                ),
                'reviews' => $reviews->items(),
            ],
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    // GET /api/listing-ratings?listing_ids[]=ID1&listing_ids[]=ID2
    public function summaries(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'listing_ids' => ['required', 'array', 'min:1', 'max:100'],
            'listing_ids.*' => ['required', 'string'],
        ]);

        $listingIds = array_values(array_unique($validated['listing_ids']));

        $listings = Listing::query()
            ->whereIn('id', $listingIds)
            ->get(['id', 'provider_id'])
            ->keyBy('id');

        $directRatings = $this->aggregatesForReviewees(Listing::class, $listingIds);

        $providerIds = $listings->pluck('provider_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $providerRatings = $this->aggregatesForReviewees(Provider::class, $providerIds);

        return response()->json([
            'success' => true,
            'data' => collect($listingIds)->map(
                function (string $listingId) use ($listings, $directRatings, $providerRatings): array {
                    $listing = $listings->get($listingId);
                    $directAggregate = $directRatings->get($listingId);
                    $providerAggregate = $listing
                        ? $providerRatings->get($listing->provider_id)
                        : null;

                    return [
                        'listing_id' => $listingId,
                        'rating' => $this->ratingSummary(
                            $directAggregate ?? $providerAggregate
                        ),
                    ];
                }
            )->values(),
        ]);
    }

    private function revieweeForListing(Listing $listing): array
    {
        $hasDirectListingReview = Review::query()
            ->where('reviewee_type', Listing::class)
            ->where('reviewee_id', $listing->id)
            ->exists();

        return $hasDirectListingReview
            ? [Listing::class, $listing->id]
            : [Provider::class, $listing->provider_id];
    }

    private function aggregateForReviewee(string $revieweeType, ?string $revieweeId): ?object
    {
        if (! $revieweeId) {
            return null;
        }

        return Review::query()
            ->selectRaw('COUNT(*) as reviews_count')
            ->selectRaw('AVG(rating) as reviews_average')
            ->where('reviewee_type', $revieweeType)
            ->where('reviewee_id', $revieweeId)
            ->first();
    }

    private function aggregatesForReviewees(string $revieweeType, array $revieweeIds)
    {
        if ($revieweeIds === []) {
            return collect();
        }

        return Review::query()
            ->select('reviewee_id')
            ->selectRaw('COUNT(*) as reviews_count')
            ->selectRaw('AVG(rating) as reviews_average')
            ->where('reviewee_type', $revieweeType)
            ->whereIn('reviewee_id', $revieweeIds)
            ->groupBy('reviewee_id')
            ->get()
            ->keyBy('reviewee_id');
    }

    private function ratingSummary(?object $aggregate): array
    {
        $average = $aggregate ? round((float) $aggregate->reviews_average, 1) : 0.0;
        $count = $aggregate ? (int) $aggregate->reviews_count : 0;

        return [
            'average' => $average,
            'display' => number_format($average, 1, '.', ''),
            'count' => $count,
            'scale' => 5,
        ];
    }

    private function reviewerName(mixed $reviewer): string
    {
        if ($reviewer instanceof User) {
            $name = trim(implode(' ', array_filter([
                $reviewer->first_name,
                $reviewer->last_name,
            ])));

            return $name !== '' ? $name : 'مستخدم Aura Events';
        }

        if ($reviewer instanceof Provider) {
            return $reviewer->brand_name ?: 'مزود خدمة';
        }

        return 'مستخدم Aura Events';
    }
}