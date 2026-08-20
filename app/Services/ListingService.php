<?php

namespace App\Services;

use App\Models\Listing;
use App\Actions\Listing\CreateListingAction;
use App\Actions\Listing\UpdateListingAction;
use Illuminate\Pagination\LengthAwarePaginator;

class ListingService
{
    public function __construct(
        private readonly CreateListingAction $createListingAction,
        private readonly UpdateListingAction $updateListingAction
    ) {}
public function getAllListings(
    ?string $type = null,
    ?int $capacityMin = null,
    ?int $capacityMax = null,
    ?float $priceMin = null,
    ?float $priceMax = null,
    ?string $title = null,
    int $perPage = 15,
): LengthAwarePaginator
{
    return Listing::with([
        'category:id,name',
        'provider:id,user_id',
        'district:id,name',
        'images:id,imageable_id,imageable_type,path',
        'variants' => fn ($q) => $q->select('id', 'listing_id', 'variant_name', 'price', 'currency', 'price_type', 'stock_quantity', 'capacity','dynamic_attributes'),
        'variants.images:id,imageable_id,imageable_type,path',
        'variants.availabilities' => fn ($q) => $q
            ->select('id', 'listing_variant_id', 'available_date', 'is_blocked')
            ->where('available_date', '>=', now()->toDateString())
            ->orderBy('available_date')
            ->limit(7),
        'variants.availabilities.slots' => fn ($q) => $q
            ->select('id', 'listing_availability_id', 'slot_name', 'start_time', 'end_time', 'remaining_capacity'),

        // ── مكوّنات وفريلانسرز الباقات (Package) ────────────────────────────
        // يُحمَّل دايماً بغض النظر عن قيمة $type، لأن الاستعلام واحد لكل
        // الأنواع؛ لو النوع مش package، هاي العلاقات ببساطة بترجع فاضية
        // بدون أي كلفة إضافية محسوسة (whereHas ما لزم هون لأنه eager load
        // عادي، مش فلترة).
        'variants.packageItems.includedVariant.listing',
        'variants.packageItems.includedVariant.images',
        'variants.packageFreelancers.freelancer',
    ])
    // Business rule: مفروضة دايماً، مش optional filter
    ->where('moderation_status', 'approved')

    ->when($type, fn ($q) => $q->where('listing_type', $type))

    ->when($title, function ($q) use ($title) {
        $normalizedTitle = $this->normalizeArabic($title);

        $q->where(function ($query) use ($title, $normalizedTitle) {
            $query->where('title->ar', 'like', "{$title}%")
                  ->orWhere('title->ar', 'like', "{$normalizedTitle}%")
                  ->orWhere('title->en', 'like', "{$title}%");
        });
    })

    ->when($capacityMin || $capacityMax, function ($q) use ($capacityMin, $capacityMax) {
        $q->whereHas('variants', function ($variantQuery) use ($capacityMin, $capacityMax) {
            $variantQuery
                ->when($capacityMin, fn ($vq) => $vq->where('capacity', '>=', $capacityMin))
                ->when($capacityMax, fn ($vq) => $vq->where('capacity', '<=', $capacityMax));
        });
    })

    ->when($priceMin || $priceMax, function ($q) use ($priceMin, $priceMax) {
        $q->whereHas('variants', function ($variantQuery) use ($priceMin, $priceMax) {
            $variantQuery
                ->when($priceMin, fn ($vq) => $vq->where('price', '>=', $priceMin))
                ->when($priceMax, fn ($vq) => $vq->where('price', '<=', $priceMax));
        });
    })
    ->latest()
    ->paginate($perPage);
}
/**
 * تطبيع الحروف العربية المتشابهة (همزات، تاء مربوطة، ياء)
 * عشان "احترافي" تلاقي "إحترافي" والعكس
 */
private function normalizeArabic(string $text): string
{
    return str_replace(
        ['أ', 'إ', 'آ', 'ة', 'ى', 'ئ'],
        ['ا', 'ا', 'ا', 'ه', 'ي', 'ي'],
        $text
    );
}
    public function createListingWithGraph(array $data): Listing
    {
        return $this->createListingAction->execute($data);
    }

    public function updateListingWithGraph(Listing $listing, array $data): Listing
    {
        return $this->updateListingAction->execute($listing, $data);
    }

    public function deleteListing(Listing $listing): bool
    {
    
    
       $listing->images()->get()->each->delete();

        return (bool) $listing->forceDelete();
    }
}