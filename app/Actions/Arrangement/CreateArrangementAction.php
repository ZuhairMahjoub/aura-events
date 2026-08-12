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
            $this->validateFreelancers->execute(
                $data['freelancers'],
                $providerId,
                $this->extractArrangementDates($data)
            );
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
               
                'stock_quantity'     => 1,
                'currency'           => $data['currency'],
            ]);

            if (! empty($data['items'])) {
                $this->syncItems->execute($variant->id, $data['items']);
            }
            if (! empty($data['freelancers'])) {
                $this->syncFreelancers->execute($variant->id, $data['freelancers']);
            }

            if (! empty($data['availabilities'])) {
                $this->syncAvailabilities->execute($variant, $data['availabilities'], $data['capacity'] ?? 1);
            } elseif (! empty($data['date_range'])) {
                $availabilities = $this->syncAvailabilities->buildAvailabilitiesFromRange($data['date_range']);
                $this->syncAvailabilities->execute($variant, $availabilities, $data['capacity'] ?? 1);
            }

            if (! empty($data['images'])) {
                $this->attachImages($data['images'], $listing);
            }

            return $listing->load([
                'variants.packageItems.includedVariant.listing.images',
                'variants.packageFreelancers.freelancer',
                'variants.availabilities.slots',
                'images',
                'category',
                'district',
            ]);
        });
    }

  
    private function extractArrangementDates(array $data): array
    {
        if (! empty($data['availabilities'])) {
            return collect($data['availabilities'])
                ->flatMap(function ($availability) {
                    $date = \Illuminate\Support\Carbon::parse($availability['available_date'])->format('Y-m-d');
                    $slots = $availability['slots'] ?? [];

                    if (empty($slots)) {
                        return [['date' => $date, 'start_time' => null, 'end_time' => null]];
                    }

                    return collect($slots)->map(fn ($slot) => [
                        'date' => $date,
                        'start_time' => $slot['start_time'] ?? null,
                        'end_time' => $slot['end_time'] ?? null,
                    ]);
                })
                ->unique(fn ($w) => "{$w['date']}|{$w['start_time']}|{$w['end_time']}")
                ->values()
                ->toArray();
        }

        if (! empty($data['date_range'])) {
            return collect($this->syncAvailabilities->buildAvailabilitiesFromRange($data['date_range']))
                ->flatMap(function ($availability) {
                    $date = $availability['available_date'];
                    $slots = $availability['slots'] ?? [];

                    if (empty($slots)) {
                        return [['date' => $date, 'start_time' => null, 'end_time' => null]];
                    }

                    return collect($slots)->map(fn ($slot) => [
                        'date' => $date,
                        'start_time' => $slot['start_time'] ?? null,
                        'end_time' => $slot['end_time'] ?? null,
                    ]);
                })
                ->unique(fn ($w) => "{$w['date']}|{$w['start_time']}|{$w['end_time']}")
                ->values()
                ->toArray();
        }

        return [];
    }

    private function attachImages(array $tempPaths, Listing $listing): void
    {
        foreach ($tempPaths as $path) {
            // هنا يتم التقاط حقل path القادم من الـ JSON الجديد
            $cleanPath = is_array($path) ? ($path['path'] ?? null) : $path;

            if (empty($cleanPath)) {
                continue;
            }

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