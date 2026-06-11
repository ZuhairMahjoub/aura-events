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
        Schema::create('listing_availabilities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('listing_variant_id')->constrained('listing_variants')->cascadeOnDelete();
            $table->date('available_date');
            $table->boolean('is_blocked')->default(false); // Manual vendor lock
            $table->timestamps();

            $table->unique(['listing_variant_id', 'available_date'], 'variant_date_unique');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_availabilities');
    }
};
