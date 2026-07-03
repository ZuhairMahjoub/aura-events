<?php

namespace App\Services;

use App\DTOs\ReviewData;
use App\Models\Booking;
use App\Models\Provider;
use App\Models\Review;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    /**
     * المنظِّم بيقيّم مزود الخدمة بعد الحجز.
     */
    public function reviewProvider(string $organizerId, ReviewData $data): Review
    {
        $booking = $this->loadCompletedBooking($data->bookingId);

        if ($booking->user_id !== $organizerId) {
            throw new DomainException('لا تملك صلاحية تقييم هذا الحجز.', 403);
        }

        return $this->createReview(
            booking: $booking,
            reviewer: $booking->user,            // User
            reviewee: $booking->provider,        // Provider
            data: $data,
        );
    }

    /**
     * مزود الخدمة بيقيّم المنظِّم بعد الحجز.
     */
    public function reviewOrganizer(string $providerId, ReviewData $data): Review
    {
        $booking = $this->loadCompletedBooking($data->bookingId);

        if ($booking->provider_id !== $providerId) {
            throw new DomainException('لا تملك صلاحية تقييم هذا الحجز.', 403);
        }

        return $this->createReview(
            booking: $booking,
            reviewer: $booking->provider,        // Provider
            reviewee: $booking->user,            // User
            data: $data,
        );
    }

    private function loadCompletedBooking(string $bookingId): Booking
    {
        $booking = Booking::with(['user', 'provider'])->findOrFail($bookingId);

        if ($booking->status !== 'completed') {
            throw new DomainException('يمكن فقط تقييم الحجوزات المكتملة.', 422);
        }

        return $booking;
    }

    private function createReview(Booking $booking, $reviewer, $reviewee, ReviewData $data): Review
    {
        $exists = Review::where('booking_id', $booking->id)
            ->where('reviewer_type', $reviewer->getMorphClass())
            ->where('reviewer_id', $reviewer->id)
            ->exists();

        if ($exists) {
            throw new DomainException('لقد قمت بتقييم هذا الحجز مسبقاً.', 422);
        }

        return DB::transaction(function () use ($booking, $reviewer, $reviewee, $data) {
            $review = Review::create([
                'booking_id'    => $booking->id,
                'reviewer_type' => $reviewer->getMorphClass(),
                'reviewer_id'   => $reviewer->id,
                'reviewee_type' => $reviewee->getMorphClass(),
                'reviewee_id'   => $reviewee->id,
                'rating'        => $data->rating,
                'comment'       => $data->comment,
            ]);

            $this->refreshAggregateRating($reviewee);

            return $review;
        });
    }

    /**
     * إعادة احتساب متوسط التقييم للطرف المُقيَّم (Provider فقط بيملك عمود rating حالياً).
     */
    private function refreshAggregateRating($reviewee): void
    {
        if (! $reviewee instanceof Provider) {
            return; // أضف عمود rating لـ User لو بدك تجميع تقييم المنظمين أيضاً
        }

        $avg = Review::where('reviewee_type', $reviewee->getMorphClass())
            ->where('reviewee_id', $reviewee->id)
            ->avg('rating');

        $reviewee->update(['rating' => round($avg, 2)]);
    }

    public function getRevieweeReviews(string $revieweeType, string $revieweeId, int $perPage = 15)
    {
        return Review::where('reviewee_type', $revieweeType)
            ->where('reviewee_id', $revieweeId)
            ->with('reviewer')
            ->latest()
            ->paginate($perPage);
    }
}