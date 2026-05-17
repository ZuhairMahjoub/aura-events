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
    Schema::create('freelancer_details', function (Blueprint $table) {
        $table->ulid('id')->primary();
        $table->foreignUlid('provider_id')->constrained()->onDelete('cascade');
        $table->string('national_id')->unique();
        $table->integer('experience_years')->default(0);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('freelancer_details');
    }
};
