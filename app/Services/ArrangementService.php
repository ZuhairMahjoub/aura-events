<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\ListingVariant;
use App\Models\PackageItem;
use App\Models\PackageFreelancer;
use App\Models\CompanyFreelancerContract;
use Illuminate\Support\Facades\DB;
use App\Models\Provider;
use Exception;

class ArrangementService
{
    protected MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    /**
     * Create a complete arrangement with products and freelancers
     */
    public function createArrangement(array $data, string $providerId)
    {
        if (!empty($data['products'])) {
            $productVariantIds = collect($data['products'])->pluck('variant_id')->toArray();

            $ownedVariantsCount = ListingVariant::whereIn('id', $productVariantIds)
                ->whereHas('listing', function ($query) use ($providerId) {
                    $query->where('provider_id', $providerId);
                })->count();

            if ($ownedVariantsCount !== count($productVariantIds)) {
                throw new Exception('عذراً، بعض المنتجات المختارة لا تنتمي لشركتك.', 403);
            }
        }

        if (!empty($data['freelancers'])) {
            $freelancerIds = collect($data['freelancers'])->pluck('freelancer_id')->toArray();

            $activeContractsCount = CompanyFreelancerContract::where('company_id', $providerId)
                ->whereIn('freelancer_id', $freelancerIds)
                ->where('status', 'active')
                ->count();

            if ($activeContractsCount !== count($freelancerIds)) {
                throw new Exception('عذراً، بعض المستقلين المختارين لا يملكون عقوداً سارية مع شركتك.', 403);
            }

            $this->validateFreelancerIds($freelancerIds);
        }

        return DB::transaction(function () use ($data, $providerId) {
            $listing = Listing::create([
                'provider_id' => $providerId,
                'category_id' => $data['category_id'],
                'district_id' => $data['district_id'],
                'title' => $data['title'],
                'description' => $data['description'],
                'listing_type' => 'package',
                'secondary_contact_number' => $data['secondary_contact_number'] ?? null,
                'cancel_before_acceptance' => $data['cancel_before_acceptance'] ?? false,
                'cancel_after_acceptance' => $data['cancel_after_acceptance'] ?? false,
                'cancel_before_payment' => $data['cancel_before_payment'] ?? false,
                'moderation_status' => 'pending_approval',
            ]);

            $variant = ListingVariant::create([
                'listing_id' => $listing->id,
                'variant_name' => $data['title'],
                'price' => $data['price'],
                'price_type' => $data['price_type'],
                'dynamic_attributes' => ['capacity' => $data['capacity']],
            ]);

            if (!empty($data['products'])) {
                $this->bulkInsertPackageItems($variant->id, $data['products']);
            }

            if (!empty($data['freelancers'])) {
                $this->bulkInsertPackageFreelancers($variant->id, $data['freelancers']);
            }

            // إضافة الصور إذا وجدت
            if (isset($data['images'])) {
                foreach ($data['images'] as $tempPath) {
                    $this->mediaService->moveAndAttach(
                        $tempPath,
                        $listing,
                        "arrangements/{$listing->id}/main"
                    );
                }
            }

            return $listing;
        });
    }

    /**
     * Validate that all freelancer IDs exist and are active
     */
    private function validateFreelancerIds(array $freelancerIds): void
    {
        $count = Provider::where('provider_type', 'freelancer')
            ->whereIn('id', $freelancerIds)
            ->where('is_active', true)
            ->count();

        if ($count !== count($freelancerIds)) {
            throw new Exception('بعض الفريلانسرز غير نشطين أو غير موجودين.', 403);
        }
    }

    /**
     * Bulk insert package items with chunking for performance
     */
    private function bulkInsertPackageItems(string $variantId, array $products): void
    {
        $packageItems = collect($products)->map(function ($product) use ($variantId) {
            return [
                'id' => (string) str()->ulid(),
                'package_variant_id' => $variantId,
                'included_variant_id' => $product['variant_id'],
                'quantity' => $product['quantity'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        foreach (array_chunk($packageItems, 500) as $chunk) {
            PackageItem::insert($chunk);
        }
    }

    /**
     * Bulk insert package freelancers with chunking for performance
     */
    private function bulkInsertPackageFreelancers(string $variantId, array $freelancers): void
    {
        $packageFreelancers = collect($freelancers)->map(function ($freelancer) use ($variantId) {
            return [
                'id' => (string) str()->ulid(),
                'package_variant_id' => $variantId,
                'freelancer_id' => $freelancer['freelancer_id'],
                'contract_id' => $freelancer['contract_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        foreach (array_chunk($packageFreelancers, 500) as $chunk) {
            PackageFreelancer::insert($chunk);
        }
    }
        /**
     * Get provider's physical products with details for UI
     */
    public function getProviderProducts($providerId)
    {
        return ListingVariant::with('listing')
            ->whereHas('listing', function ($query) use ($providerId) {
                $query->where('provider_id', $providerId)
                      ->where('listing_type', 'physical_product');
            })
            ->select([
                'id',
                'listing_id',
                'variant_name',
                'price',
                'currency',
                'price_type',
                'stock_quantity',
                'dynamic_attributes'
            ])
            ->get()
            ->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'product_name' => $variant->listing->title,
                    'variant_name' => $variant->variant_name,
                    'price' => (float) $variant->price,
                    'currency' => $variant->currency,
                    'price_type' => $variant->price_type,
                    'stock' => $variant->stock_quantity,
                    'description' => $variant->listing->description,
                    'attributes' => $variant->dynamic_attributes,
                ];
            });
    }

    /**
     * Get all active freelancers available for collaboration
     */
    public function getAvailableFreelancers(string $companyId)
    {
        return Provider::where('provider_type', 'freelancer')
            ->where('is_active', true)
            ->whereHas('activeContracts', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->select(['id', 'brand_name'])
            ->get();
    }

    /**
     * Update an existing arrangement with new data
     */
    public function updateArrangement(Listing $listing, array $data, string $providerId): Listing
    {
        if ($listing->provider_id !== $providerId) {
            throw new Exception('لا يمكنك تعديل هذا الترتيب. لا تملك الصلاحيات.', 403);
        }

        return DB::transaction(function () use ($listing, $data, $providerId) {
            // ── تحديث بيانات الـ Listing الأساسية ──
            $listingPayload = [];
            $listingFields = [
                'category_id', 'district_id', 'title', 'description',
                'cancel_before_acceptance', 'cancel_after_acceptance', 'cancel_before_payment',
                'secondary_contact_number', 'is_provider_location_based'
            ];

            foreach ($listingFields as $field) {
                if (array_key_exists($field, $data)) {
                    $listingPayload[$field] = $data[$field];
                }
            }

            if (!empty($listingPayload)) {
                $listing->update($listingPayload);
            }

            // ── إضافة الصور الجديدة ──
            if (isset($data['images'])) {
                foreach ($data['images'] as $tempPath) {
                    $this->mediaService->moveAndAttach(
                        $tempPath,
                        $listing,
                        "arrangements/{$listing->id}/main"
                    );
                }
            }

            // ── حذف الصور المحددة ──
            if (isset($data['images_to_delete']) && is_array($data['images_to_delete'])) {
                foreach ($data['images_to_delete'] as $imageId) {
                    $image = $listing->images()->find($imageId);
                    if ($image) {
                        $this->mediaService->deleteImage($image);
                    }
                }
            }

            // ── تحديث بيانات الـ Variant ──
            $variant = $listing->variants()->first();
            if ($variant && isset($data['price']) || isset($data['price_type']) || isset($data['capacity'])) {
                $variantPayload = [];
                if (isset($data['price'])) $variantPayload['price'] = $data['price'];
                if (isset($data['price_type'])) $variantPayload['price_type'] = $data['price_type'];
                if (isset($data['capacity'])) {
                    $variantPayload['dynamic_attributes'] = array_merge(
                        $variant->dynamic_attributes ?? [],
                        ['capacity' => $data['capacity']]
                    );
                }
                if (!empty($variantPayload)) {
                    $variant->update($variantPayload);
                }
            }

            return $listing->load(['variants', 'images']);
        });
    }
}