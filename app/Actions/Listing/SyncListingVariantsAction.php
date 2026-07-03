<?php

namespace App\Actions\Listing;

use App\Models\Listing;
use App\Models\ListingVariant;
use App\Services\MediaService;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class SyncListingVariantsAction
{
    public function __construct(
        private SyncVariantAvailabilitiesAction $syncAvailabilitiesAction,
        private MediaService $mediaService
    ) {}

    public function execute(Listing $listing, array $variantsData): void
    {
        $sentVariantIds = collect($variantsData)->pluck('id')->filter()->values()->toArray();

        // حذف الـ Variants غير المرسلة
        $listing->variants()->whereNotIn('id', $sentVariantIds)->get()->each->forceDelete();

        foreach ($variantsData as $variantData) {
            $payload = $this->buildVariantPayload($variantData);

            if (!empty($variantData['id'])) {
                $variant = $listing->variants()->findOrFail($variantData['id']);
                $variant->update($payload);
            } else {
                $variant = $listing->variants()->create($payload);
            }

            // إدارة صور الـ Variant بنفس منطق الـ Listing:
            // - images غير موجودة → لا تمس الصور
            // - images: []         → احذف كل الصور
            // - images: [...]      → sync
            if (array_key_exists('images', $variantData)) {
                $images = $variantData['images'] ?? [];

                $keepIds = collect($images)
                    ->filter(fn($img) => is_array($img) && !empty($img['id']))
                    ->pluck('id')
                    ->toArray();

                $variant->images()->whereNotIn('id', $keepIds)->delete();

                foreach ($images as $img) {
                    if (is_array($img) && empty($img['id']) && !empty($img['path'])) {
                        $fullTempPath = Str::startsWith($img['path'], 'temp/')
                            ? $img['path']
                            : 'temp/' . $img['path'];

                        $this->mediaService->moveAndAttach(
                            $fullTempPath,
                            $variant,
                            "listings/{$listing->id}/variants",
                            "Variant Image - " . ($variantData['variant_name']['en'] ?? 'Default')
                        );
                    }
                }
            }

            // مزامنة التواريخ والمواعيد
            if (!empty($variantData['availabilities'])) {
                $this->syncAvailabilitiesAction->execute($variant, $variantData['availabilities']);
            } elseif (!empty($variantData['date_range'])) {
                $availabilities = $this->generateAvailabilitiesFromRange($variantData['date_range']);
                $this->syncAvailabilitiesAction->execute($variant, $availabilities);
            }
        }
    }

    private function buildVariantPayload(array $variantData): array
    {
        $payload = [];
        if (empty($variantData['id'])) {
            $payload['id'] = (string) Str::ulid();
        }

        $fields = ['variant_name', 'price', 'currency', 'price_type', 'stock_quantity', 'capacity'];
        foreach ($fields as $field) {
            if (array_key_exists($field, $variantData)) {
                $payload[$field] = $variantData[$field];
            }
        }

        if (array_key_exists('services', $variantData)) {
            $payload['dynamic_attributes'] = $this->mapServicesToColumns($variantData['services']);
        }

        return $payload;
    }

    private function mapServicesToColumns(array $services): ?array
    {
        if (empty($services)) return null;
        $mapped = [];
        foreach ($services as $service) {
            $key = $service['key'] ?? null;
            if ($key) {
                $mapped[$key] = [
                    'value' => $service['value'],
                    'label' => $service['label'] ?? null,
                ];
            }
        }
        return $mapped ?: $services;
    }

    private function generateAvailabilitiesFromRange(array $range): array
    {
        $startDate = Carbon::parse($range['start_date'])->startOfDay();
        $endDate   = Carbon::parse($range['end_date'])->startOfDay();
        $today     = Carbon::today();
        $slots     = $range['slots'] ?? [];

        if ($startDate->gt($endDate)) {
            return [];
        }

        $availabilities = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            if ($current->gte($today)) {
                $availabilities[] = [
                    'available_date' => $current->format('Y-m-d'),
                    'is_blocked'     => false,
                    'slots'          => $slots,
                ];
            }
            $current->addDay();
        }

        return $availabilities;
    }
}