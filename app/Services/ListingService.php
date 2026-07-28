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

    public function getAllListings(int $perPage = 15): LengthAwarePaginator
    {
        return Listing::with([
            'category:id,name_ar,name_en',
            'provider:id,user_id',
            'district:id,name_ar,name_en',
            'images:id,imageable_id,imageable_type,path',
            'variants' => fn ($q) => $q->select('id', 'listing_id', 'variant_name', 'price', 'currency', 'price_type', 'stock_quantity'),
            'variants.images:id,imageable_id,imageable_type,path',
            'variants.availabilities' => fn ($q) => $q
                ->select('id', 'listing_variant_id', 'available_date', 'is_blocked')
                ->where('available_date', '>=', now()->toDateString())
                ->orderBy('available_date')
                ->limit(7),
            'variants.availabilities.slots' => fn ($q) => $q
                ->select('id', 'listing_availability_id', 'slot_name', 'start_time', 'end_time', 'remaining_capacity'),
        ])
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