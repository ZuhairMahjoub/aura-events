<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * نضيف category_id كبديل اختياري لـ service_id.
     * الفلسفة: service_id إجباري إذا كان عند الشركة خدمات معرّفة أصلاً؛
     * وإلا (شركة بدون أي Service بعد) تقدر تنشر الوظيفة مرتبطة بـ category
     * عامة بدل ذلك. service_id أصلاً nullable بقاعدة البيانات (منذ migration
     * add_service_id_to_job_offers_table)، فلا حاجة لتعديلها هنا — الإجبارية
     * الفعلية (واحد من الاثنين بالضبط) تُفرض على مستوى الـ validation
     * بالـ Controller فقط، لأن قاعدة البيانات وحدها لا تعبّر عن "إما/أو" بسهولة.
     */
    public function up(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('service_id')
                ->constrained('categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};