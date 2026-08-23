<?php

namespace App\Services;

use App\Actions\Arrangement\CreateArrangementAction;
use App\Actions\Arrangement\UpdateArrangementAction;
use App\Models\CompanyFreelancerContract;
use App\Models\Listing;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ArrangementService
{
    public function __construct(
        private readonly CreateArrangementAction $createAction,
        private readonly UpdateArrangementAction $updateAction,
    ) {}

    public function createArrangement(array $data, string $providerId): Listing
    {
        return $this->createAction->execute($data, $providerId);
    }

    public function updateArrangement(Listing $listing, array $data, string $providerId): Listing
    {
        return $this->updateAction->execute($listing, $data, $providerId);
    }

    public function getProviderProducts(string $providerId): array
    {
        return Listing::with([
            'images', 'variants.images', 'variants.availabilities.slots', 'category', 'district',
        ])
            ->where('provider_id', $providerId)
            ->where('listing_type', 'physical_product')
            ->whereIn('moderation_status', ['approved', 'draft', 'pending_approval', 'rejected', 'cancelled'])
            ->get()
            ->map(fn ($listing) => [
                'id' => $listing->id,
                'title' => $listing->title,
                'name' => $listing->title,
                'description' => $listing->description,
                'status' => $listing->moderation_status,
                'category' => $listing->category,
                'district' => $listing->district,
                'image' => $listing->images->first(),
                'cancel_before_acceptance' => (bool) $listing->cancel_before_acceptance,
                'cancel_after_acceptance'  => (bool) $listing->cancel_after_acceptance,
                'cancel_before_payment'    => (bool) $listing->cancel_before_payment,
                'variants' => $listing->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'name' => $v->variant_name,
                    'price' => (float) $v->price,
                    'currency' => $v->currency,
                    'price_type' => $v->price_type,
                    'stock' => $v->stock_quantity,
                    'attributes' => $v->dynamic_attributes,
                    'images' => $v->images,
                    'availabilities' => $v->availabilities,
                ])->toArray(),
            ])
            ->toArray();
    }

    public function getAvailableFreelancers(string $companyId): EloquentCollection
    {
        return Provider::where('provider_type', 'freelancer')
            ->where('is_active', true)
            ->whereHas('activeContracts', fn ($q) => $q->where('company_id', $companyId))
            ->select(['id', 'brand_name'])
            ->get();
    }
}