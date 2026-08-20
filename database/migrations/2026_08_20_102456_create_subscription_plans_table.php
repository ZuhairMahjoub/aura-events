<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('provider_id')->constrained('providers')->cascadeOnDelete();

            // فترة الاشتراك الحالية — إذا current_period_ends_at < now() ولم يُجدَّد،
            // الـ scheduler اليومي بيوقف is_active تلقائياً (راجع DeactivateExpiredSubscriptions).
            $table->date('current_period_starts_at');
            $table->date('current_period_ends_at');

            $table->enum('payment_status', ['pending', 'confirmed', 'overdue'])
                ->default('pending');

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('SYP');

            // من أكّد الدفعة (أدمن) ومتى — عبر
            // POST /admin/providers/{id}/subscriptions/confirm-payment
            $table->foreignUlid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();

            $table->text('admin_notes')->nullable();

            $table->timestamps();

            $table->index(['provider_id', 'current_period_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_subscriptions');
    }
};