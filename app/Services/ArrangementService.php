<?php
 
namespace App\Services;
 
use App\Models\CompanyFreelancerContract;
use App\Models\Listing;
use App\Models\ListingVariant;
use App\Models\PackageFreelancer;
use App\Models\PackageItem;
use App\Models\Provider;
use Exception;
use Google\Service\Storage;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage as FacadesStorage;

class ArrangementService
{
    public function __construct(protected MediaService $mediaService)
    {
    }
 
    // ────────────────────────────────────────────────────────────────────────────
    // Public API
    // ────────────────────────────────────────────────────────────────────────────
 
    /**
     * Create a new package listing with its items, freelancers, and images.
     *
     * @param  array   $data        Validated data from ArrangementStoreRequest
     * @param  string  $providerId  The authenticated company's provider ID
     *
     * @throws Exception  If any pre-validation check fails (code 403)
     */
    public function createArrangement(array $data, string $providerId): Listing
    {
        // ── Pre-validation (outside transaction for early fail) ──────────────
        if (! empty($data['items'])) {
            $this->validateItems($data['items'], $providerId);
        }
 
        if (! empty($data['freelancers'])) {
            $this->validateFreelancers($data['freelancers'], $providerId);
        }
 
        // ── Transactional creation ────────────────────────────────────────────
        return DB::transaction(function () use ($data, $providerId) {
 
            $listing = Listing::create([
                'provider_id'              => $providerId,
                'category_id'              => $data['category_id'],
                'district_id'              => $data['district_id'],
                'title'                    => $data['title'],
                'description'              => $data['description'],
                'listing_type'             => 'package',
                'secondary_contact_number' => $data['secondary_contact_number'] ?? null,
                'cancel_before_acceptance' => $data['cancel_before_acceptance'] ?? false,
                'cancel_after_acceptance'  => $data['cancel_after_acceptance']  ?? false,
                'cancel_before_payment'    => $data['cancel_before_payment']    ?? false,
                'moderation_status'        => 'pending_approval',
            ]);
 
            $variant = ListingVariant::create([
                'listing_id'         => $listing->id,
                'variant_name'       => $data['title'],   // mirrors listing title
                'price'              => $data['price'],
                'price_type'         => $data['price_type'],
                'dynamic_attributes' => isset($data['capacity'])
                    ? ['capacity' => $data['capacity']]
                    : null,
            ]);
 
            if (! empty($data['items'])) {
                $this->bulkInsertPackageItems($variant->id, $data['items']);
            }
 
            if (! empty($data['freelancers'])) {
                $this->bulkInsertPackageFreelancers($variant->id, $data['freelancers']);
            }
 
            if (! empty($data['images'])) {
                $this->attachImages($data['images'], $listing);
            }
 
            return $listing->load([
                'variants.packageItems.includedVariant.listing',
                'variants.packageFreelancers.freelancer',
                'images',
                'category',
                'district',
            ]);
        });
    }
 
    /**
     * Update an existing package listing.
     *
     * When `items` is present in $data, it fully replaces all existing package items.
     *
     * @param  Listing  $listing     The package listing to update
     * @param  array    $data        Validated data from ArrangementUpdateRequest
     * @param  string   $providerId  Must match listing->provider_id
     *
     * @throws Exception
     */
    public function updateArrangement(Listing $listing, array $data, string $providerId): Listing
    {
        if ($listing->provider_id !== $providerId) {
            throw new Exception('لا تملك صلاحية تعديل هذا الترتيب.', 403);
        }
 
        // Pre-validate new items before touching the DB
        if (isset($data['items'])) {
            $this->validateItems($data['items'], $providerId);
        }
 
        return DB::transaction(function () use ($listing, $data, $providerId) {
 
            // ── Update Listing core fields ────────────────────────────────────
            $listingFields = [
                'category_id', 'district_id', 'title', 'description',
                'secondary_contact_number', 'is_provider_location_based',
                'cancel_before_acceptance', 'cancel_after_acceptance', 'cancel_before_payment',
            ];
            $listingPayload = array_intersect_key($data, array_flip($listingFields));
            if ($listingPayload) {
                $listing->update($listingPayload);
            }
 
            // ── Update Variant (price / capacity) ─────────────────────────────
            $variant = $listing->variants()->firstOrFail();
 
            $variantPayload = array_intersect_key($data, array_flip(['price', 'price_type']));
            if (isset($data['capacity'])) {
                $variantPayload['dynamic_attributes'] = array_merge(
                    $variant->dynamic_attributes ?? [],
                    ['capacity' => $data['capacity']]
                );
            }
            if (isset($data['title'])) {
                $variantPayload['variant_name'] = $data['title'];
            }
            if ($variantPayload) {
                $variant->update($variantPayload);
            }
 
            // ── Sync package items (full replacement) ─────────────────────────
            if (isset($data['items'])) {
                // Hard-delete all existing items before re-inserting the new set.
                // Using the Model query directly is more reliable than the relation builder
                // when forceDelete() is involved (avoids version-specific quirks).
                PackageItem::where('package_variant_id', $variant->id)->forceDelete();
                $this->bulkInsertPackageItems($variant->id, $data['items']);
            }
 
            // ── Add new images ────────────────────────────────────────────────
            if (! empty($data['images'])) {
                $this->attachImages($data['images'], $listing);
            }
 
            // ── Delete requested images ───────────────────────────────────────
            if (! empty($data['images_to_delete'])) {
                foreach ($data['images_to_delete'] as $imageId) {
                    $image = $listing->images()->find($imageId);
                    if ($image) {
                         $this->mediaService->deleteImage($image);
                    }
                }
            }
 
            return $listing->load([
                'variants.packageItems.includedVariant.listing',
                'variants.packageFreelancers.freelancer',
                'images',
                'category',
                'district',
            ]);
        });
    }
 
    /**
     * Return the company's own physical-product variants for the item picker UI.
     */
    public function getProviderProducts(string $providerId): array
    {
        return ListingVariant::with('listing')
            ->whereHas('listing', fn ($q) => $q
                ->where('provider_id', $providerId)
                ->where('listing_type', 'physical_product')
                ->where('moderation_status', 'approved')
            )
            ->select(['id', 'listing_id', 'variant_name', 'price', 'currency', 'price_type', 'stock_quantity', 'dynamic_attributes'])
            ->get()
            ->map(fn ($v) => [
                'id'           => $v->id,
                'product_name' => $v->listing->title,
                'variant_name' => $v->variant_name,
                'price'        => (float) $v->price,
                'currency'     => $v->currency,
                'price_type'   => $v->price_type,
                'stock'        => $v->stock_quantity,
                'attributes'   => $v->dynamic_attributes,
            ])
            ->toArray();
    }
 
    /**
     * Return freelancers who have an active contract with the given company.
     */
    public function getAvailableFreelancers(string $companyId): EloquentCollection
    {
        return Provider::where('provider_type', 'freelancer')
            ->where('is_active', true)
            ->whereHas('activeContracts', fn ($q) => $q->where('company_id', $companyId))
            ->select(['id', 'brand_name'])
            ->get();
    }
 
    // ────────────────────────────────────────────────────────────────────────────
    // Pre-validation helpers
    // ────────────────────────────────────────────────────────────────────────────
 
    /**
     * Validate all items in the `items[]` array.
     *
     * Checks:
     * 1. Every variant_id exists (done by FormRequest; double-checked here).
     * 2. No variant's listing is of type 'package' (prevents circular packages).
     * 3. Every variant's listing is owned by the company (ownership guard).
     * 4. Every included listing is approved / not rejected.
     *
     * @throws Exception (403)
     */
    private function validateItems(array $items, string $providerId): void
    {
        $variantIds = collect($items)->pluck('variant_id')->unique()->values()->toArray();
 
        // Load all variants with their parent listings in one query
        $variants = ListingVariant::with('listing')
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');
 
        // All variant IDs must resolve (redundant with FormRequest but defensive)
        if ($variants->count() !== count($variantIds)) {
            throw new Exception('أحد عناصر الباقة المختارة غير موجود.', 403);
        }
 
        foreach ($variants as $variant) {
            $listing      = $variant->listing;
            $displayTitle = $this->extractTitle($listing); // ← safe string regardless of cast
 
            // ① No circular packages
            if ($listing->listing_type === 'package') {
                throw new Exception(
                    "لا يمكن إضافة باقة داخل باقة أخرى. العنصر \"{$displayTitle}\" هو باقة.",
                    403
                );
            }
 
            // ② Ownership: variant's listing must belong to the same company
            if ($listing->provider_id !== $providerId) {
                throw new Exception(
                    "العنصر \"{$displayTitle}\" لا ينتمي لشركتك. يمكنك فقط إضافة منتجاتك الخاصة.",
                    403
                );
            }
 
            // ③ Listing must be approved or awaiting approval
            if (! in_array($listing->moderation_status, ['approved', 'pending_approval'], true)) {
                throw new Exception(
                    "العنصر \"{$displayTitle}\" غير مفعّل حالياً (الحالة: {$listing->moderation_status}).",
                    403
                );
            }
        }
    }
 
    /**
     * Validate all freelancers in the `freelancers[]` array.
     *
     * Checks:
     * 1. Each freelancer_id belongs to an active freelancer provider.
     * 2. Each contract_id is an active contract between this company and the freelancer.
     * 3. The contract_id actually belongs to the stated freelancer_id (no cross-linking).
     *
     * @throws Exception (403)
     */
    private function validateFreelancers(array $freelancers, string $companyId): void
    {
        $freelancerIds = collect($freelancers)->pluck('freelancer_id')->unique()->toArray();
        $contractIds   = collect($freelancers)->pluck('contract_id')->unique()->toArray();
 
        // ① All freelancer IDs must be active freelancer providers
        $activeFreelancerCount = Provider::where('provider_type', 'freelancer')
            ->where('is_active', true)
            ->whereIn('id', $freelancerIds)
            ->count();
 
        if ($activeFreelancerCount !== count($freelancerIds)) {
            throw new Exception('بعض الفريلانسرز المختارين غير نشطين أو غير موجودين.', 403);
        }
 
        // ② All contracts must be active and belong to this company
        $validContracts = CompanyFreelancerContract::where('company_id', $companyId)
            ->where('status', 'active')
            ->whereIn('id', $contractIds)
            ->whereIn('freelancer_id', $freelancerIds)
            ->get()
            ->keyBy('id');
 
        if ($validContracts->count() !== count($contractIds)) {
            throw new Exception(
                'بعض الفريلانسرز لا يملكون عقوداً سارية مع شركتك أو العقود غير مطابقة.',
                403
            );
        }
 
        // ③ Cross-reference: each contract must belong to its stated freelancer
        foreach ($freelancers as $entry) {
            $contract = $validContracts->get($entry['contract_id']);
            if (! $contract || $contract->freelancer_id !== $entry['freelancer_id']) {
                throw new Exception(
                    'عقد الفريلانسر غير مطابق. العقد المُرسَل لا ينتمي للفريلانسر المُحدَّد.',
                    403
                );
            }
        }
    }
 
    // ────────────────────────────────────────────────────────────────────────────
    // Bulk insert helpers (chunked for performance)
    // ────────────────────────────────────────────────────────────────────────────
 
    /**
     * Bulk-insert package items for a given package variant.
     */
    private function bulkInsertPackageItems(string $variantId, array $items): void
    {
        $rows = collect($items)->map(fn ($item) => [
            'id'                  => (string) str()->ulid(),
            'package_variant_id'  => $variantId,
            'included_variant_id' => $item['variant_id'],
            'quantity'            => $item['quantity'],
            'created_at'          => now(),
            'updated_at'          => now(),
        ])->toArray();
 
        foreach (array_chunk($rows, 500) as $chunk) {
            PackageItem::insert($chunk);
        }
    }
 
    /**
     * Bulk-insert package freelancers for a given package variant.
     */
    private function bulkInsertPackageFreelancers(string $variantId, array $freelancers): void
    {
        $rows = collect($freelancers)->map(fn ($f) => [
            'id'                 => (string) str()->ulid(),
            'package_variant_id' => $variantId,
            'freelancer_id'      => $f['freelancer_id'],
            'contract_id'        => $f['contract_id'],
            'created_at'         => now(),
            'updated_at'         => now(),
        ])->toArray();
 
        foreach (array_chunk($rows, 500) as $chunk) {
            PackageFreelancer::insert($chunk);
        }
    }
 
    /**
     * Move temp images to their final location and attach them to the listing.
     */
private function attachImages(array $tempPaths, Listing $listing): void
{
    foreach ($tempPaths as $path) {
        $cleanPath = is_array($path) ? $path[0] : $path;

        // التعديل هنا: الفحص باستخدام public_path المباشر المتوافق مع الـ MediaService الجديدة
        $absTempPath = public_path(str_replace('/', DIRECTORY_SEPARATOR, $cleanPath));

        if (! file_exists($absTempPath)) {
            Log::error("فشل العثور على الملف المؤقت في المجلد العام: {$absTempPath}");
            continue;
        }

        try {
            $this->mediaService->moveAndAttach(
                $cleanPath,
                $listing,
                "arrangements/{$listing->id}/main"
            );
        } catch (\Exception $e) {
            Log::error("خطأ أثناء نقل الصورة {$cleanPath}: " . $e->getMessage());
        }
    }
}
     private function extractTitle(mixed $title): string
    {
        if (is_array($title)) {
            return $title['ar'] ?? $title['en'] ?? 'عنوان غير متوفر';
        }
        return (string) $title;
    }
}
 



