<?php

namespace App\Console\Commands;

use App\Models\Provider;
use App\Models\ProviderSubscription;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DiagnoseSubscriptionCommand extends Command
{
    protected $signature = 'diag:subscription';
    protected $description = 'تشخيص مؤقت لمشكلة الاشتراكات';

    public function handle(): void
    {
        DB::beginTransaction();

        try {
            $providerUser = User::create([
                'first_name' => 'DiagProvider', 'last_name' => 'Test',
                'email' => 'diag_' . Str::random(8) . '@test.com',
                'password' => bcrypt('password'), 'status' => 'active',
            ]);

            $provider = Provider::create([
                'user_id' => $providerUser->id, 'brand_name' => 'تشخيص',
                'provider_type' => 'company', 'moderation_status' => 'approved', 'is_active' => true,
            ]);

            $this->info("provider->id = {$provider->id}");
            $this->info("provider->is_active قبل أي شي = " . var_export($provider->is_active, true));

            $sub = ProviderSubscription::create([
                'provider_id'               => $provider->id,
                'current_period_starts_at'  => now()->toDateString(),
                'current_period_ends_at'    => now()->addMonth()->toDateString(),
                'payment_status'            => 'confirmed',
                'amount'                    => 100,
                'currency'                  => 'USD',
                'confirmed_at'              => now(),
            ]);

            $this->info("subscription->provider_id = {$sub->provider_id}");
            $this->info("subscription->current_period_ends_at = {$sub->current_period_ends_at}");

            $this->info("إجمالي عدد الصفوف بجدول provider_subscriptions حالياً (جوا نفس الـ transaction) = "
                . DB::table('provider_subscriptions')->count());

            $expiredProviderIds = ProviderSubscription::query()
                ->selectRaw('provider_id, MAX(current_period_ends_at) as latest_ends_at')
                ->groupBy('provider_id')
                ->havingRaw('MAX(current_period_ends_at) < ?', [now()->toDateString()])
                ->pluck('provider_id');

            $this->info("expiredProviderIds (لازم يكون فاضي) = " . $expiredProviderIds->toJson());
            $this->info("هل provider->id موجود جوا expiredProviderIds؟ = "
                . var_export($expiredProviderIds->contains($provider->id), true));

            // الآن نستدعي نفس الأمر الحقيقي مباشرة (مش عبر Artisan::call، لتفادي أي احتمال
            // فرق بالسلوك)، ونشوف شو صار فعلياً بـ is_active بعده.
            $this->call('providers:deactivate-expired-subscriptions');

            $provider->refresh();
            $this->info("provider->is_active بعد تشغيل الأمر (المفروض يضل true لأنه الاشتراك لسا ساري) = "
                . var_export($provider->is_active, true));

        } finally {
            DB::rollBack();
            $this->info('تم عمل rollback لكل البيانات التجريبية.');
        }
    }
}