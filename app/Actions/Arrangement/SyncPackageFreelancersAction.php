<?php

namespace App\Actions\Arrangement;

use App\Models\PackageFreelancer;
use Illuminate\Support\Str;

class SyncPackageFreelancersAction
{
   
    public function execute(string $variantId, array $freelancers): void
    {
        PackageFreelancer::where('package_variant_id', $variantId)->delete();

        if (empty($freelancers)) {
            return;
        }

        $rows = collect($freelancers)->map(fn ($f) => [
            'id'                 => (string) Str::ulid(),
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
}