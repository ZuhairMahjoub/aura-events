<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('booking_id')->constrained('bookings')->cascadeOnDelete();

            // المُقيِّم: إما User (organizer) أو Provider
            $table->ulidMorphs('reviewer');

            // المُقيَّم: إما User (organizer) أو Provider
            $table->ulidMorphs('reviewee');

            $table->unsignedTinyInteger('rating'); // 1 - 5
            $table->text('comment')->nullable();
            $table->timestamps();

            // اتجاه واحد فقط لكل حجز (organizer→provider أو provider→organizer)
            $table->unique(['booking_id', 'reviewer_type', 'reviewer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};