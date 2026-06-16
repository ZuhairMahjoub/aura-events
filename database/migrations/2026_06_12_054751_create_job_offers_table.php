<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_offers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            // ربط الوظيفة بالشركة التي طرحتها (من جدول providers)
            $table->foreignUlid('company_id')->constrained('providers')->onDelete('cascade');
            
            // تفاصيل الوظيفة الأساسية (Essential Details)
            $table->string('job_title'); // المسمى الوظيفي
            $table->enum('time_condition', ['Permanent', 'Temporary', 'Contract']); // طبيعة العمل
            $table->string('event_type'); // نوع الفعالية (مثال: زفاف، مؤتمر)
            $table->date('job_start_date'); // تاريخ بدء العمل
            $table->date('application_deadline'); // آخر موعد للتقديم
            
            // التفاصيل المالية والمواصفات (Financials & Specifics)
            $table->decimal('salary', 10, 2); // الراتب أو الأجر
            $table->enum('payment_system', ['Per Event', 'Monthly', 'Hourly']); // نظام الدفع
            $table->string('specific_event_association')->nullable(); // ربطها بحدث معين (اختياري)
            $table->enum('experience_level', ['Junior', 'Mid', 'Senior']); // مستوى الخبرة المطلوبة
            $table->boolean('company_equipment_provided')->default(false); // هل الشركة توفر المعدات؟
            
            // الشروط والتواصل (Requirements & Outreach)
            $table->text('job_requirements_and_scope'); // المتطلبات والمسؤوليات
            $table->string('contact_info'); // بريد أو رابط التواصل المباشر مع الـ HR
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_offers');
    }
};