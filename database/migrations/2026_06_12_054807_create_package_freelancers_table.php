<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_freelancers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            // ربط الفريلانسر بمتغير باقة معين (من جدول listing_variants الخاص بكِ)
            $table->foreignUlid('package_variant_id')->constrained('listing_variants')->onDelete('cascade');
            
            // معرف الفريلانسر المتعاون
            $table->foreignUlid('freelancer_id')->constrained('providers')->onDelete('cascade');
            
            // معرف العقد الأصلي المستند إليه هذا التعاون (nullable للاحتياط)
            $table->foreignUlid('contract_id')->nullable()->constrained('company_freelancer_contracts')->onDelete('set null');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_freelancers');
    }
};