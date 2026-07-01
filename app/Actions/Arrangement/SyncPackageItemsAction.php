<?php

namespace App\Actions\Arrangement;

use App\Models\PackageItem;
use Illuminate\Support\Str;

class SyncPackageItemsAction
{
    /**
     * استبدال كامل (full replacement) لعناصر الباقة.
     */
    public function execute(string $variantId, array $items): void
    {
        PackageItem::where('package_variant_id', $variantId)->forceDelete();

        if (empty($items)) {
            return;
        }

        $rows = collect($items)->map(fn ($item) => [
            'id'                  => (string) Str::ulid(),
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
}