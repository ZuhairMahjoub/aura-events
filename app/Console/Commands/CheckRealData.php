<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckRealData extends Command
{
    protected $signature = 'diag:check-real-data';
    protected $description = 'فحص البيانات الحقيقية الموجودة فعلياً بالجداول (بدون transaction)';

    public function handle(): void
    {
        $subCount = DB::table('provider_subscriptions')->count();
        $this->info("إجمالي provider_subscriptions الحقيقية = {$subCount}");

        if ($subCount > 0) {
            $rows = DB::table('provider_subscriptions')->get();
            foreach ($rows as $row) {
                $this->line("id={$row->id} | provider_id={$row->provider_id} | ends_at={$row->current_period_ends_at}");
            }
        }

        $activeProvidersCount = DB::table('providers')->where('is_active', true)->count();
        $this->info("إجمالي providers is_active=true = {$activeProvidersCount}");
    }
}