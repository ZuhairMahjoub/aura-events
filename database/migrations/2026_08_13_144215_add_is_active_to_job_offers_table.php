<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            // تحكم يدوي من الشركة: تفعيل / تعطيل عرض العمل
            // القبول أو الرفض على متقدمين لا يغيّر هذه القيمة إطلاقاً.
            $table->boolean('is_active')->default(true)->after('contact_info');
        });
    }

    public function down(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};