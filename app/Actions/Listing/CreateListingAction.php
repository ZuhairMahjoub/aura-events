<?php

namespace App\Actions\Listing;

use App\Models\Listing;
use Illuminate\Support\Facades\DB;
use App\Services\MediaService;
use Illuminate\Support\Str;

class CreateListingAction
{
    public function __construct(
        private SyncListingVariantsAction $syncVariantsAction,
        private MediaService $mediaService
    ) {}

    public function execute(array $data): Listing
    {
        return DB::transaction(function () use ($data) {
            // 1. إنشاء الإعلان الأساسي
            $listing = Listing::create([
                'provider_id'                => $data['provider_id'],
                'category_id'                => $data['category_id'],
                'district_id'                => $data['district_id'],
                'title'                      => $data['title'],
                'description'                => $data['description'],
                'listing_type'               => $data['listing_type'],
                'material_composition'       => $data['material_composition'] ?? null,
                'secondary_contact_number'   => $data['secondary_contact_number'] ?? null,
                'cancel_before_acceptance'   => $data['cancel_before_acceptance'] ?? false,
                'cancel_after_acceptance'    => $data['cancel_after_acceptance'] ?? false,
                'cancel_before_payment'      => $data['cancel_before_payment'] ?? false,
                'is_provider_location_based' => $data['is_provider_location_based'] ?? true,
                'moderation_status'          => $data['moderation_status'] ?? 'draft',
                'rejection_reason'           => $data['rejection_reason'] ?? null,
            ]);

            // 2. معالجة صور الـ Listing الأساسية
            if (!empty($data['images']) && is_array($data['images'])) {
                foreach ($data['images'] as $img) {
                    // استخراج المسار سواء كان مرسلاً ككائن (Object) أو كنص مباشر (String)
                    $path = is_array($img) ? ($img['path'] ?? $img['temp_path'] ?? null) : (is_string($img) ? $img : null);

                    if (!empty($path)) {
                        $fullTempPath = Str::startsWith($path, 'temp/')
                            ? $path
                            : 'temp/' . $path;

                        $this->mediaService->moveAndAttach(
                            $fullTempPath,
                            $listing,
                            "listings/{$listing->id}/main"
                        );
                    }
                }
            }

            // 3. إنشاء المتغيرات (Variants) وتوابعها
            $this->syncVariantsAction->execute($listing, $data['variants']);

            return $listing->load(['variants.availabilities.slots', 'images', 'variants.images', 'category', 'district']);
        });
    }
}
