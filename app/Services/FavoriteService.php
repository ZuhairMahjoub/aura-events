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
        // 1. نبدأ جلب الصالات من خلال علاقة المفضلة الخاصة بالمستخدم حصراً
        return Listing::query()
            ->whereHas('favoritedBy', function ($query) use ($userId) {
                $query->where('users.id', $userId);
            })
            
            // 2. ترتيب الصالات المفضلة حسب الأحدث (حسب وقت إضافتها للمفضلة)
            ->orderByDesc(
                Favorite::select('created_at')
                    ->whereColumn('favorites.listing_id', 'listings.id')
                    ->where('favorites.user_id', $userId)
                    ->latest()
                    ->take(1)
            )
            
            // 3. جلب العلاقات المرتبطة بالصالات
            ->with([
                'images', 
                'variants.images', 
                'variants.availabilities.slots', 
                'category:id,name', 
                'district:id,name'
            ])
            ->paginate($perPage);
    }
}
