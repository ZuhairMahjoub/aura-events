<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_freelancer_contracts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            // الشركة المستضيفة للوظيفة
            $table->foreignUlid('company_id')->constrained('providers')->onDelete('cascade');
            
            // الفريلانسر المتقدم للوظيفة
            $table->foreignUlid('freelancer_id')->constrained('providers')->onDelete('cascade');
            
            // ربط الطلب بالوظيفة التي تم التقديم عليها (من جدول job_offers أعلاه)
            $table->foreignUlid('job_offer_id')->constrained('job_offers')->onDelete('cascade');
            
            // حالة الطلب (قيد الانتظار، مقبول ونشط، مرفوض، منتهي)
            $table->enum('status', ['pending', 'active', 'rejected', 'expired'])->default('pending');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_freelancer_contracts');
    }
};