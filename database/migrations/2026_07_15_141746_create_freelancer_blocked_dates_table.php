<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('freelancer_blocked_dates', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // كل تاريخ محجوز تابع لفريلانسر وحد، وبينحذف تلقائياً لو انحذف الفريلانسر
            $table->foreignUlid('freelancer_id')->constrained('providers')->onDelete('cascade');

            $table->date('blocked_date');

            // manual: أضافه الفريلانسر يدوياً / booking: انحجز تلقائياً بسبب حجز مقبول
            $table->enum('source', ['manual', 'booking'])->default('manual');

            // مرتبط بحجز إذا كان source = booking، nullable لو manual
            $table->foreignUlid('booking_id')->nullable()->constrained('bookings')->onDelete('cascade');

            $table->timestamps();

            // نفس الفريلانسر ما يقدر يكرر نفس التاريخ مرتين
            $table->unique(['freelancer_id', 'blocked_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('freelancer_blocked_dates');
    }
};