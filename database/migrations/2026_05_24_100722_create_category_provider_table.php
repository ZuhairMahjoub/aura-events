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
    Schema::create('category_provider', function (Blueprint $table) {
        $table->id();
        
        // يربط مع جدول مقدمي الخدمات (providers)
        $table->foreignUlid('provider_id')->constrained()->onDelete('cascade');
        
        // يربط مع جدول الأقسام (categories)
        $table->foreignId('category_id')->constrained()->onDelete('cascade');
        
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_provider');
    }
};
