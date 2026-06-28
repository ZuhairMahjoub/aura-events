<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['active', 'converted', 'abandoned'])->default('active');
            $table->string('single_active_lock')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignUlid('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignUlid('listing_variant_id')->constrained('listing_variants')->cascadeOnDelete();
            $table->foreignUlid('listing_slot_id')->nullable()->constrained('listing_slots')->nullOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('price_snapshot', 12, 2);
            $table->date('booked_date')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignUlid('converted_booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->timestamps();
            $table->index('cart_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
