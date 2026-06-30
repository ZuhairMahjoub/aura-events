<?php
// database/migrations/2026_07_01_000002_create_booking_status_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_status_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('booking_id')
                  ->constrained('bookings')
                  ->restrictOnDelete();

            $table->string('from_status')->nullable();
            $table->string('to_status');

            $table->enum('actor_type', ['organizer', 'provider', 'admin', 'system'])->index();
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('reason')->nullable();

          
            $table->json('metadata')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // أكثر استعلام متوقع: "أرني كل تاريخ الحالة لحجز معيّن مرتباً زمنياً"
            $table->index(['booking_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_status_logs');
    }
};