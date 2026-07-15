<?php

namespace App\Actions\Arrangement;

use App\Models\CompanyFreelancerContract;
use App\Models\FreelancerBlockedDate;
use App\Models\Provider;
use Exception;
use Illuminate\Validation\ValidationException;

class ValidatePackageFreelancersAction
{
    /**
     * @param  array  $arrangementDates  تواريخ التنسيق (availabilities) للفحص عليها تعارض روزنامة الفريلانسر
     *
     * @throws Exception (403)
     * @throws ValidationException (422)
     */
    public function execute(array $freelancers, string $companyId, array $arrangementDates = []): void
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

        // الخطوة 7: فحص تعارض التواريخ فوراً — قبل ما نكمل أي حفظ فعلي.
        if (! empty($arrangementDates)) {
            $this->assertNoDateConflicts($freelancerIds, $arrangementDates);
        }
    }

    /**
     * @throws ValidationException (422)
     */
    private function assertNoDateConflicts(array $freelancerIds, array $arrangementDates): void
    {
        foreach ($freelancerIds as $freelancerId) {
            $conflictDates = FreelancerBlockedDate::where('freelancer_id', $freelancerId)
                ->whereIn('blocked_date', $arrangementDates)
                ->pluck('blocked_date');

            if ($conflictDates->isNotEmpty()) {
                $formatted = $conflictDates->map(fn ($d) => $d->format('Y-m-d'))->implode(', ');

                throw ValidationException::withMessages([
                    'freelancers' => "الفريلانسر غير متاح بتاريخ: {$formatted}",
                ]);
            }
        }
    }
}