<?php

namespace App\Actions\Arrangement;

use App\Models\CompanyFreelancerContract;
use App\Models\Provider;
use Exception;

class ValidatePackageFreelancersAction
{
    /**
     * @throws Exception (403)
     */
    public function execute(array $freelancers, string $companyId): void
    {
        $freelancerIds = collect($freelancers)->pluck('freelancer_id')->unique()->toArray();
        $contractIds   = collect($freelancers)->pluck('contract_id')->unique()->toArray();

        $activeCount = Provider::where('provider_type', 'freelancer')
            ->where('is_active', true)
            ->whereIn('id', $freelancerIds)
            ->count();

        if ($activeCount !== count($freelancerIds)) {
            throw new Exception('بعض الفريلانسرز المختارين غير نشطين أو غير موجودين.', 403);
        }

        $validContracts = CompanyFreelancerContract::where('company_id', $companyId)
            ->where('status', 'active')
            ->whereIn('id', $contractIds)
            ->whereIn('freelancer_id', $freelancerIds)
            ->get()
            ->keyBy('id');

        if ($validContracts->count() !== count($contractIds)) {
            throw new Exception('بعض الفريلانسرز لا يملكون عقوداً سارية مع شركتك أو العقود غير مطابقة.', 403);
        }

        foreach ($freelancers as $entry) {
            $contract = $validContracts->get($entry['contract_id']);
            if (! $contract || $contract->freelancer_id !== $entry['freelancer_id']) {
                throw new Exception('عقد الفريلانسر غير مطابق للفريلانسر المُحدَّد.', 403);
            }
        }
    }
}