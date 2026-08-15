<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
{
    Schema::create('company_blocked_dates', function (Blueprint $table) {
        $table->ulid('id')->primary();
        $table->foreignUlid('company_id')->constrained('providers')->onDelete('cascade');
        $table->date('blocked_date');

        // 💡 أضفنا حقول الوقت والملاحظات هنا
        $table->time('start_time')->nullable();
        $table->time('end_time')->nullable();
        $table->string('note')->nullable();

        $table->enum('source', ['manual', 'booking'])->default('manual');
        $table->foreignUlid('booking_id')->nullable()->constrained('bookings')->onDelete('cascade');
        $table->timestamps();

        // ⚠️ ملاحظة مهمة: قمت بتعطيل هذا السطر (unique) 
        // لكي تتمكني من إغلاق أوقات مختلفة في نفس اليوم (مثلاً: من 1 لـ 3، ومن 5 لـ 8)
        // $table->unique(['company_id', 'blocked_date']); 
    });
}

    public function down(): void
    {
        Schema::dropIfExists('company_blocked_dates');
    }
};