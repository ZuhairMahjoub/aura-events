<?php

namespace App\Services;

use App\Models\Favorite;
use App\Models\Listing;
use Illuminate\Pagination\LengthAwarePaginator;

class FavoriteService
{
    public function toggle(string $userId, string $listingId): array
    {
        Listing::where('moderation_status', 'approved')->findOrFail($listingId);

        $favorite = Favorite::where('user_id', $userId)
            ->where('listing_id', $listingId)
            ->first();

        if ($favorite) {
            $favorite->delete();
            return ['favorited' => false];
        }

        Favorite::create(['user_id' => $userId, 'listing_id' => $listingId]);
        return ['favorited' => true];
    }

    public function list(string $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Listing::query()
            ->whereHas('favoritedBy', fn ($q) => $q->where('users.id', $userId))
            
            ->orderByDesc(
                Favorite::select('created_at')
                    ->whereColumn('favorites.listing_id', 'listings.id')
                    ->where('favorites.user_id', $userId)
                    ->latest()
                    ->take(1)
            )
            
            // 3. Eager load relations
            ->with(['images', 'variants','avilableties','slots', 'category', 'district'])
            ->paginate($perPage);
    }
}