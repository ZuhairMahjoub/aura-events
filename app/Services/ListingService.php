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
       int $perPage = 15,
   ): LengthAwarePaginator
{
    return Listing::with([
        'category:id,name',
        'provider:id,user_id',
        'district:id,name',
        'images:id,imageable_id,imageable_type,path',
        // ⚠️ dynamic_attributes رجعت تختفي من هالـ select() بعد git pull
        // سابق — بدونها Eloquent أصلاً ما بيسحب capacity/color/material
        // من الـ DB، وكل قيمهم بترجع null بالـ API مهما كان صحيح بالجدول.
        'variants' => fn ($q) => $q->select('id', 'listing_id', 'variant_name', 'price', 'currency', 'price_type', 'stock_quantity', 'dynamic_attributes'),
        'variants.images:id,imageable_id,imageable_type,path',
        'variants.availabilities' => fn ($q) => $q
            ->select('id', 'listing_variant_id', 'available_date', 'is_blocked')
            ->where('available_date', '>=', now()->toDateString())
            ->orderBy('available_date')
            ->limit(7),
        'variants.availabilities.slots' => fn ($q) => $q
            ->select('id', 'listing_availability_id', 'slot_name', 'start_time', 'end_time', 'remaining_capacity'),
    ])
        ->when($type, fn ($q) => $q->where('listing_type', $type))
        // فلتر capacity مستقل تماماً عن فلتر type — كل مزيج ممكن (type بس،
        // capacity بس، الاثنين سوا، ولا واحد فيهم) بيشتغل بدون تعارض.
        ->when($capacityMin || $capacityMax, function ($q) use ($capacityMin, $capacityMax) {
            $q->whereHas('variants', function ($variantQuery) use ($capacityMin, $capacityMax) {
                $variantQuery
                    ->when($capacityMin, fn ($vq) => $vq->where('dynamic_attributes->capacity', '>=', $capacityMin))
                    ->when($capacityMax, fn ($vq) => $vq->where('dynamic_attributes->capacity', '<=', $capacityMax));
            });
        })
        ->latest()
        ->paginate($perPage);
}
    public function getListingById(string $id): Listing
    {
        return Listing::with([
            'category',
            'district',
            'images',
            'variants.images',
            'variants.availabilities.slots'
        ])->findOrFail($id);
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