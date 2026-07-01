<?php

namespace App\Actions\Arrangement;

use App\Models\Listing;
use App\Services\MediaService;
use Exception;
use Illuminate\Support\Facades\DB;

class UpdateArrangementAction
{
    public function __construct(
        private ValidatePackageItemsAction $validateItems,
        private ValidatePackageFreelancersAction $validateFreelancers,
        private SyncPackageItemsAction $syncItems,
        private SyncPackageFreelancersAction $syncFreelancers,
        private SyncArrangementAvailabilitiesAction $syncAvailabilities,
        private MediaService $mediaService,
    ) {}

    public function execute(Listing $listing, array $data, string $providerId): Listing
    {
        if ($listing->provider_id !== $providerId) {
            throw new Exception('لا تملك صلاحية تعديل هذا الترتيب.', 403);
        }

        if (isset($data['items'])) {
            $this->validateItems->execute($data['items'], $providerId);
        }
        if (isset($data['freelancers'])) {
            $this->validateFreelancers->execute($data['freelancers'], $providerId);
        }

        return DB::transaction(function () use ($listing, $data, $providerId) {
            $listingFields = [
                'category_id', 'district_id', 'title', 'description',
                'secondary_contact_number', 'is_provider_location_based',
                'cancel_before_acceptance', 'cancel_after_acceptance', 'cancel_before_payment',
            ];
            $listingPayload = array_intersect_key($data, array_flip($listingFields));
            if ($listingPayload) {
                $listing->update($listingPayload);
            }

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

            if (isset($data['items'])) {
                $this->syncItems->execute($variant->id, $data['items']);
            }
            if (isset($data['freelancers'])) {
                $this->syncFreelancers->execute($variant->id, $data['freelancers']);
            }
            if (isset($data['availabilities'])) {
                $this->syncAvailabilities->execute($variant, $data['availabilities'], $data['capacity'] ?? 1);
            }
            if (! empty($data['images'])) {
                $this->attachImages($data['images'], $listing);
            }
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
                'variants.availabilities.slots',
                'images', 'category', 'district',
            ]);
        });
    }

    private function attachImages(array $tempPaths, Listing $listing): void
    {
        foreach ($tempPaths as $path) {
            // إصلاح 1: البيانات القادمة من الـ validation هي مصفوفة مفتاحها
            // نصي {id: ..., path: "temp/xxx.jpg"} — وليست مصفوفة مرقّمة، لذا
            // $path[0] كان دائماً غير موجود (undefined key) ويرجع null.
            $cleanPath = is_array($path) ? ($path['path'] ?? null) : $path;

            if (empty($cleanPath)) {
                continue;
            }

            // إصلاح 2: تطبيع المسار بإضافة 'temp/' إذا لم يكن موجوداً،
            // بنفس منطق CreateArrangementAction و Listing.
            $cleanPath = \Illuminate\Support\Str::startsWith($cleanPath, 'temp/')
                ? $cleanPath
                : 'temp/' . $cleanPath;

            // إصلاح 3: try/catch لمنع فشل ملف واحد من إيقاف كل عملية
            // التحديث (transaction كاملة كانت تفشل بخطأ 500 من قبل).
            try {
                $this->mediaService->moveAndAttach($cleanPath, $listing, "arrangements/{$listing->id}/main");
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("خطأ أثناء نقل صورة الترتيب {$cleanPath}: " . $e->getMessage());
            }
        }
    }
}