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
        Schema::create('listing_slots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('listing_availability_id')->constrained('listing_availabilities')->cascadeOnDelete();
            
            $table->json('slot_name')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('remaining_capacity')->default(1);
            $table->timestamps();

            $table->unique(['listing_availability_id', 'start_time', 'end_time'], 'slot_time_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_slots');
    }
};