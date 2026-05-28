<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_variants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('listing_id')->constrained('listings')->onDelete('cascade');
            
            $table->json('variant_name'); 
            $table->decimal('price', 12, 2)->index(); 
            $table->string('currency', 3)->default('USD');
            $table->enum('price_type', ['fixed', 'hourly'])->default('fixed');
            $table->integer('stock_quantity')->nullable();
            
            $table->json('dynamic_attributes')->nullable(); 
            
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_variants');
    }
};