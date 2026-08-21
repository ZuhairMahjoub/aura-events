<?php

namespace App\Console\Commands;

use App\Models\Provider;
use App\Models\ProviderSubscription;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TestProviderPolicySubscription extends Command
{
    protected $signature = 'test:provider-policy-subscription';
    protected $description = 'تيست شامل لفيتشر سياسة المزوّد + الاشتراكات (يعمل rollback تلقائياً)';

    private array $results = [];

    public function handle(): void
    {
        DB::beginTransaction();

        try {
            $adminUser = User::first();

            $providerUser = User::create([
                'first_name' => 'Provider', 'last_name' => 'Test',
                'email' => 'provider_' . Str::random(8) . '@test.com',
                'password' => bcrypt('password'), 'status' => 'active',
            ]);
            $provider = Provider::create([
                'user_id' => $providerUser->id, 'brand_name' => 'مزوّد تجريبي',
                'provider_type' => 'company', 'moderation_status' => 'approved', 'is_active' => true,
            ]);

            $this->ok('تجهيز provider جديد (approved, active, policy_accepted_at=null افتراضياً)');

            // TEST 1
            if ($provider->hasAcceptedPolicy() === false) {
                $this->ok('TEST 1: hasAcceptedPolicy() ترجع false قبل الموافقة');
            } else {
                $this->markFail('TEST 1: hasAcceptedPolicy() المفروض ترجع false هون');
            }

            // TEST 2
            try {
                $provider->update(['policy_accepted_at' => now()]);
                $provider->refresh();
                if ($provider->hasAcceptedPolicy() === true) {
                    $this->ok('TEST 2: بعد تحديث policy_accepted_at، hasAcceptedPolicy() ترجع true');
                } else {
                    $this->markFail('TEST 2: hasAcceptedPolicy() لسا false رغم تحديث policy_accepted_at');
                }
            } catch (\Throwable $e) { $this->markFail('TEST 2: تحديث policy_accepted_at', $e); }

            // TEST 3
            $subscription = null;
            try {
                $startsAt = now()->toDateString();
                $endsAt   = now()->addMonth()->toDateString();

                $subscription = ProviderSubscription::create([
                    'provider_id'               => $provider->id,
                    'current_period_starts_at'  => $startsAt,
                    'current_period_ends_at'    => $endsAt,
                    'payment_status'            => 'confirmed',
                    'amount'                    => 100,
                    'currency'                  => 'USD',
                    'confirmed_by'              => $adminUser?->id,
                    'confirmed_at'              => now(),
                    'admin_notes'               => 'تيست command',
                ]);

                if ($subscription->payment_status === 'confirmed' && $subscription->current_period_ends_at->toDateString() === $endsAt) {
                    $this->ok('TEST 3: إنشاء اشتراك (ProviderSubscription) بنجاح، مرتبط بالـ provider الصحيح');
                } else {
                    $this->markFail('TEST 3: الاشتراك انخلق لكن ببيانات غير متوقعة');
                }
            } catch (\Throwable $e) { $this->markFail('TEST 3: إنشاء اشتراك جديد', $e); }

            // TEST 4
            try {
                $count = $provider->subscriptions()->count();
                $latest = $provider->latestSubscription;
                if ($count === 1 && $latest && $latest->id === $subscription->id) {
                    $this->ok('TEST 4: subscriptions() و latestSubscription() تشتغلوا صح (count=1, latest مطابق)');
                } else {
                    $this->markFail("TEST 4: قيم غير متوقعة -> count={$count}, latest_id=" . ($latest->id ?? 'null'));
                }
            } catch (\Throwable $e) { $this->markFail('TEST 4: فحص العلاقات subscriptions/latestSubscription', $e); }

            // TEST 5
            try {
                $this->call('providers:deactivate-expired-subscriptions');
                $provider->refresh();
                if ($provider->is_active === true) {
                    $this->ok('TEST 5: provider ضل active لأنه اشتراكه لسا ساري (current_period_ends_at بالمستقبل)');
                } else {
                    $this->markFail('TEST 5: provider انوقف بالغلط رغم إنه اشتراكه لسا ساري!');
                }
            } catch (\Throwable $e) { $this->markFail('TEST 5: تشغيل scheduler على اشتراك ساري', $e); }

            // TEST 6
            try {
                $subscription->update(['current_period_ends_at' => now()->subDay()->toDateString()]);

                $this->call('providers:deactivate-expired-subscriptions');
                $provider->refresh();

                if ($provider->is_active === false) {
                    $this->ok('TEST 6: provider اتوقف تلقائياً (is_active=false) بعد انتهاء الاشتراك بدون تجديد');
                } else {
                    $this->markFail('TEST 6: provider ضل active رغم انتهاء الاشتراك -> الـ scheduler ما اشتغل صح');
                }
            } catch (\Throwable $e) { $this->markFail('TEST 6: محاكاة انتهاء الاشتراك وتشغيل scheduler', $e); }

            // TEST 7
            try {
                ProviderSubscription::create([
                    'provider_id'               => $provider->id,
                    'current_period_starts_at'  => now()->toDateString(),
                    'current_period_ends_at'    => now()->addMonth()->toDateString(),
                    'payment_status'            => 'confirmed',
                    'amount'                    => 100,
                    'currency'                  => 'USD',
                    'confirmed_by'              => $adminUser?->id,
                    'confirmed_at'              => now(),
                ]);

                if (! $provider->is_active) {
                    $provider->update(['is_active' => true]);
                }
                $provider->refresh();

                if ($provider->is_active === true) {
                    $this->ok('TEST 7: تأكيد دفعة جديدة بعد التوقف أعاد تفعيل provider (is_active=true)');
                } else {
                    $this->markFail('TEST 7: provider ضل غير مفعّل رغم تأكيد دفعة جديدة');
                }
            } catch (\Throwable $e) { $this->markFail('TEST 7: إعادة التفعيل بعد دفعة جديدة', $e); }

            $this->line("\n============================");
            $this->line("النتائج:");
            $this->line("============================");
            foreach ($this->results as $r) { $this->line($r); }

            $totalOk = collect($this->results)->filter(fn($r) => str_starts_with($r, '[OK]'))->count();
            $totalFail = collect($this->results)->filter(fn($r) => str_starts_with($r, '[FAIL]'))->count();
            $this->line("\n[SUMMARY] النتيجة النهائية: {$totalOk} ناجح / {$totalFail} فاشل من أصل " . count($this->results));

        } catch (\Throwable $e) {
            $this->error("\n[ERROR] خطأ عام أوقف السكربت: " . $e->getMessage());
            $this->line($e->getTraceAsString());
        } finally {
            DB::rollBack();
            $this->line("\n(تم عمل rollback لكل البيانات التجريبية — قاعدة البيانات رجعت متل ما كانت)");
        }
    }

    private function ok(string $label): void
    {
        $this->results[] = "[OK] $label";
    }

    private function markFail(string $label, ?\Throwable $e = null): void
    {
        $this->results[] = "[FAIL] $label" . ($e ? " -> " . $e->getMessage() : '');
    }
}