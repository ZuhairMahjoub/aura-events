<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Collection;

class ProviderDashboardService
{
    
    public function summary(
        User $user,
        int $pendingListingsLimit = 5,
        int $topListingsLimit = 10
    ): array {
        $provider = $user->providerProfile;

        if (! $provider) {
            abort(422, 'لا يوجد ملف مزود خدمة مرتبط بهذا الحساب.');
        }

        $providerId = $provider->id;

        return [
            'counts' => [
                // عدد طلبات الحجوزات المعلقة للمزود
                'pending_booking_requests' => Booking::query()
                    ->where('provider_id', $providerId)
                    ->where('status', 'pending')
                    ->count(),

                // عدد العروض النشطة = approved
                'active_listings' => Listing::query()
                    ->where('provider_id', $providerId)
                    ->where('moderation_status', 'approved')
                    ->count(),
            ],

            // أحدث العروض المعلقة: الاسم، النوع، وتاريخ الإرسال
            'pending_listings' => $this->pendingListings(
                $providerId,
                $pendingListingsLimit
            ),

            // ترتيب العروض من الأكثر طلبات حجز إلى الأقل
            'listings_by_booking_requests' => $this->topListingsByBookingRequests(
                $providerId,
                $topListingsLimit
            ),
        ];
    }

    private function pendingListings(string $providerId, int $limit): Collection
    {
        return Listing::query()
            ->where('provider_id', $providerId)
            ->where('moderation_status', 'pending_approval')
            ->latest()
            ->limit($limit)
            ->get([
                'id',
                'title',
                'listing_type',
                'moderation_status',
                'created_at',
            ])
            ->map(fn (Listing $listing) => [
                'id' => $listing->id,
                'name' => $this->displayTitle($listing->title),
                'type' => $listing->listing_type,
                'submitted_at' => $listing->created_at?->toISOString(),
                'status' => $listing->moderation_status,
            ])
            ->values();
    }

    private function topListingsByBookingRequests(
        string $providerId,
        int $limit
    ): Collection {
        return Listing::query()
            ->where('provider_id', $providerId)
            ->withCount('bookings')
            ->orderByDesc('bookings_count')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get([
                'id',
                'title',
                'listing_type',
                'moderation_status',
                'created_at',
            ])
            ->map(fn (Listing $listing) => [
                'id' => $listing->id,
                'name' => $this->displayTitle($listing->title),
                'type' => $listing->listing_type,
                'status' => $listing->moderation_status,
                'booking_requests_count' => $listing->bookings_count,
            ])
            ->values();
    }

    
    private function displayTitle(mixed $title): ?string
    {
        if (is_string($title)) {
            return $title;
        }

        if (! is_array($title) || $title === []) {
            return null;
        }

        return $title['ar']
            ?? $title['en']
            ?? collect($title)->first();
    }
}