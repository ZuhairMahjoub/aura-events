<?php

namespace App\Console\Commands;

use App\Models\Provider;
use App\Models\ProviderSubscription;
use Illuminate\Console\Command;

class DeactivateExpiredProviderSubscriptions extends Command
{
    protected $signature = 'providers:deactivate-expired-subscriptions';

    protected $description = 'إيقاف تفعيل المزوّدين الذين انتهت مدة اشتراكهم دون تجديد (is_active = false تلقائياً).';

    public function handle(): void
    {
        // ملاحظة: whereHas() لا يعمل بشكل موثوق مع علاقات latestOfMany/ofMany
        // (محدودية موثّقة رسمياً بـ Laravel)، لذلك نحسب "آخر فترة اشتراك"
        // مباشرة عبر GROUP BY + MAX بدل الاعتماد على علاقة latestSubscription.
        $expiredProviderIds = ProviderSubscription::query()
            ->selectRaw('provider_id, MAX(current_period_ends_at) as latest_ends_at')
            ->groupBy('provider_id')
            ->havingRaw('MAX(current_period_ends_at) < ?', [now()->toDateString()])
            ->pluck('provider_id');

        $providers = Provider::query()
            ->where('is_active', true)
            ->whereIn('id', $expiredProviderIds)
            ->get();

        $count = 0;

        foreach ($providers as $provider) {
            $provider->update(['is_active' => false]);
            $count++;

            // هون مكان مناسب لإرسال إشعار/إيميل للمزوّد بانتهاء الاشتراك،
            // لو عندك نظام إشعارات جاهز بالمشروع (Notification::send...).
        }

        if ($count > 0) {
            $this->info("تم إيقاف تفعيل {$count} مزوّد بسبب انتهاء الاشتراك دون تجديد.");
        } else {
            $this->info('لا يوجد مزوّدون بحاجة لإيقاف تفعيل حالياً.');
        }
    }
}