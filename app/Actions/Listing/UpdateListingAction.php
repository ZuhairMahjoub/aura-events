<?php

namespace App\Actions\Listing;

use App\Models\Listing;
use Illuminate\Support\Facades\DB;
use App\Services\MediaService;
use Illuminate\Support\Str;

class UpdateListingAction
{
    public function __construct(
        private SyncListingVariantsAction $syncVariantsAction,
        private MediaService $mediaService
    ) {}

    public function execute(Listing $listing, array $data): Listing
    {
        return DB::transaction(function () use ($listing, $data) {
            // 1. تحديث البيانات الأساسية
            $listingPayload = collect($data)->only([
                'category_id', 'district_id', 'title', 'description', 'listing_type',
                'cancel_before_acceptance', 'cancel_after_acceptance', 'cancel_before_payment',
                'secondary_contact_number', 'is_provider_location_based', 'material_composition',
                'moderation_status'
            ])->toArray();

            if (!empty($listingPayload)) {
                $listing->update($listingPayload);
            }

            // 2. إدارة الصور بثلاث حالات:
            // - images غير موجودة في الـ request  → لا تمس الصور
            // - images: []                         → احذف كل الصور
            // - images: [{id:...}, {path:...}]     → sync (احتفظ بالقديمة وارفع الجديدة)
            if (array_key_exists('images', $data)) {
                $images = $data['images'] ?? [];

                // احتفظ بالصور اللي جاء لها ID
                $keepIds = collect($images)
                    ->filter(fn($img) => is_array($img) && !empty($img['id']))
                    ->pluck('id')
                    ->toArray();

                // احذف الصور اللي مش في القائمة (أو كلها لو images: [])
                $listing->images()->whereNotIn('id', $keepIds)->delete();

                // ارفع الصور الجديدة فقط (اللي ما عندها ID)
                foreach ($images as $img) {
                    if (is_array($img) && empty($img['id']) && !empty($img['path'])) {
                        $fullTempPath = Str::startsWith($img['path'], 'temp/')
                            ? $img['path']
                            : 'temp/' . $img['path'];

                        $this->mediaService->moveAndAttach(
                            $fullTempPath,
                            $listing,
                            "listings/{$listing->id}/main"
                        );
                    }
                }
            }

            // 3. مزامنة المتغيرات
            if (isset($data['variants'])) {
                $this->syncVariantsAction->execute($listing, $data['variants']);
            }

            return $listing->load([
                'variants.availabilities.slots',
                'images',
                'variants.images',
                'category',
                'district'
            ]);
        });
    }
}