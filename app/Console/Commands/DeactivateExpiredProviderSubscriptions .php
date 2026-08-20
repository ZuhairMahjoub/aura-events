<?php

namespace App\Console\Commands;

use App\Models\Provider;
use Illuminate\Console\Command;

class DeactivateExpiredProviderSubscriptions extends Command
{
    protected $signature = 'providers:deactivate-expired-subscriptions';

    protected $description = 'إيقاف تفعيل المزوّدين الذين انتهت مدة اشتراكهم دون تجديد (is_active = false تلقائياً).';

    public function handle(): void
    {
        // مزوّد نشط حالياً، وآخر فترة اشتراك له انتهت (current_period_ends_at
        // < اليوم)، ولا يوجد فترة أحدث مفتوحة بعدها.
        $providers = Provider::query()
            ->where('is_active', true)
            ->whereHas('latestSubscription', function ($q) {
                $q->where('current_period_ends_at', '<', now()->toDateString());
            })
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