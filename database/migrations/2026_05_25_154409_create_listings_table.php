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
        Schema::create('listings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();
            
            $table->json('title');
            $table->json('description');
            
            $table->enum('listing_type', ['physical_product', 'service', 'package', 'hall']);
            $table->string('material_composition')->nullable(); // Used if physical product
            $table->string('secondary_contact_number')->nullable();
            
            $table->boolean('cancel_before_acceptance')->default(false);
            $table->boolean('cancel_after_acceptance')->default(false);
            $table->boolean('cancel_before_payment')->default(false);
            $table->boolean('is_provider_location_based')->default(true);
            
            $table->enum('moderation_status', ['draft', 'pending_approval', 'approved', 'rejected'])->default('draft');
            $table->text('rejection_reason')->nullable();
            
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};