<?php

namespace App\Actions\Arrangement;

use App\Models\ListingVariant;
use Exception;

class ValidatePackageItemsAction
{
    /**
     * @throws Exception (403) لو في عنصر غير صالح لإضافته داخل الباقة
     */
    public function execute(array $items, string $providerId): void
    {
        $variantIds = collect($items)->pluck('variant_id')->unique()->values()->toArray();

        $variants = ListingVariant::with('listing')
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        if ($variants->count() !== count($variantIds)) {
            throw new Exception('أحد عناصر الباقة المختارة غير موجود.', 403);
        }

        foreach ($variants as $variant) {
            $listing = $variant->listing;
            $title   = $this->extractTitle($listing->title);

            if ($listing->listing_type === 'package') {
                throw new Exception("لا يمكن إضافة باقة داخل باقة أخرى. العنصر \"{$title}\" هو باقة.", 403);
            }

            if ($listing->provider_id !== $providerId) {
                throw new Exception("العنصر \"{$title}\" لا ينتمي لشركتك.", 403);
            }

            if (! in_array($listing->moderation_status, ['approved', 'pending_approval'], true)) {
                throw new Exception("العنصر \"{$title}\" غير مفعّل حالياً (الحالة: {$listing->moderation_status}).", 403);
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