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
    /**
     * @param  array  $arrangementWindows  نوافذ زمنية للتنسيق، كل عنصر:
     *                                     ['date' => 'Y-m-d', 'start_time' => ?string, 'end_time' => ?string]
     *                                     start_time/end_time = null يعني "اليوم كامل" (لا يوجد slot محدد).
     *
     * @throws Exception (403)
     * @throws ValidationException (422)
     */
    public function execute(array $freelancers, string $companyId, array $arrangementWindows = []): void
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

        // الخطوة 7: فحص تعارض التواريخ/الأوقات فوراً — قبل ما نكمل أي حفظ فعلي.
        if (! empty($arrangementWindows)) {
            $this->assertNoDateConflicts($freelancerIds, $arrangementWindows);
        }
    }

    /**
     * ⚠️ تحديث: فحص تعارض حقيقي بمستوى الوقت (مش اليوم كامل بس)، بنفس
     * منطق FreelancerBlockedDate::hasConflict() المستخدم بجهة الحجوزات —
     * عشان ما يصير نفس نوع المشكلة يلي انصلحت هناك (رفض تنسيق الساعة 9
     * صباحاً بسبب حجز مباشر الساعة 2 ظهراً بنفس اليوم، رغم عدم أي تداخل فعلي).
     *
     * @throws ValidationException (422)
     */
    private function assertNoDateConflicts(array $freelancerIds, array $arrangementWindows): void
    {
        foreach ($freelancerIds as $freelancerId) {
            foreach ($arrangementWindows as $window) {
                $hasConflict = FreelancerBlockedDate::hasConflict(
                    $freelancerId,
                    $window['date'],
                    $window['start_time'] ?? null,
                    $window['end_time'] ?? null,
                );

                if ($hasConflict) {
                    $label = $window['start_time']
                        ? "{$window['date']} ({$window['start_time']}-{$window['end_time']})"
                        : $window['date'];

                    throw ValidationException::withMessages([
                        'freelancers' => "الفريلانسر غير متاح بتاريخ: {$label}",
                    ]);
                }
            }
        }
    }
}