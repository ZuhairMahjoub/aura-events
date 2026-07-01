<?php

namespace App\Actions\Arrangement;

use App\Models\Listing;
use App\Models\ListingVariant;
use App\Services\MediaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateArrangementAction
{
    public function __construct(
        private ValidatePackageItemsAction $validateItems,
        private ValidatePackageFreelancersAction $validateFreelancers,
        private SyncPackageItemsAction $syncItems,
        private SyncPackageFreelancersAction $syncFreelancers,
        private SyncArrangementAvailabilitiesAction $syncAvailabilities,
        private MediaService $mediaService,
    ) {}

    public function execute(array $data, string $providerId): Listing
    {
        if (! empty($data['items'])) {
            $this->validateItems->execute($data['items'], $providerId);
        }
        if (! empty($data['freelancers'])) {
            $this->validateFreelancers->execute($data['freelancers'], $providerId);
        }

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
                'cancel_after_acceptance'  => $data['cancel_after_acceptance'] ?? false,
                'cancel_before_payment'    => $data['cancel_before_payment'] ?? false,
                'moderation_status'        => 'pending_approval',
             
            ]);

            $variant = ListingVariant::create([
                'listing_id'         => $listing->id,
                'variant_name'       => $data['title'],
                'price'              => $data['price'],
                'price_type'         => $data['price_type'],
                'dynamic_attributes' => isset($data['capacity']) ? ['capacity' => $data['capacity']] : null,
                   'stock_quantity'           => $data['capacity'],
                'currency'                 => $data['currency'],
            ]);

            if (! empty($data['items'])) {
                $this->syncItems->execute($variant->id, $data['items']);
            }
            if (! empty($data['freelancers'])) {
                $this->syncFreelancers->execute($variant->id, $data['freelancers']);
            }
            
            // ── التعديل هنا: تمرير الـ date_range الجديد ──────────────────────
            if (! empty($data['date_range'])) {
                $this->syncAvailabilities->execute($variant, $data['date_range'], $data['capacity'] ?? 1);
            }
            
            if (! empty($data['images'])) {
                $this->attachImages($data['images'], $listing);
            }

            // ملاحظة: تبقى علاقة availabilities.slots كما هي لأننا في النهاية
            // نقوم بفرد النطاق الزمني وحفظه كأيام منفصلة داخل قاعدة البيانات.
            return $listing->load([
                'variants.packageItems.includedVariant.listing.images',
                'variants.packageFreelancers.freelancer',
                'variants.availabilities.slots',
                'images', 'category', 'district',
            ]);
        });
    }

   private function attachImages(array $tempPaths, Listing $listing): void
{
    foreach ($tempPaths as $path) {
        // هنا يتم التقاط حقل path القادم من الـ JSON الجديد
        $cleanPath = is_array($path) ? ($path['path'] ?? null) : $path;

        if (empty($cleanPath)) {
            continue;
        }

        // إصلاح: نفس منطق CreateListingAction — تطبيع المسار بإضافة
        // 'temp/' إذا لم يكن موجوداً. بدون هذا، أي مسار يصل بدون البريفكس
        // (فقط اسم الملف) يفشل بصمت في file_exists() ويتم تجاوز الصورة
        // دون أي خطأ ظاهر للمستخدم — وهو تحديداً ما كان يحدث هنا.
        $cleanPath = \Illuminate\Support\Str::startsWith($cleanPath, 'temp/')
            ? $cleanPath
            : 'temp/' . $cleanPath;

        $absTempPath = storage_path('app/public/' . str_replace('/', DIRECTORY_SEPARATOR, $cleanPath));

        if (! file_exists($absTempPath)) {
            \Illuminate\Support\Facades\Log::error("فشل العثور على الملف المؤقت: {$absTempPath}");
            continue;
        }

        try {
            $this->mediaService->moveAndAttach($cleanPath, $listing, "arrangements/{$listing->id}/main");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("خطأ أثناء نقل الصورة {$cleanPath}: " . $e->getMessage());
        }
    }
}
}