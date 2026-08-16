<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * moderation_status: يفصل بين موافقة الأدمن على نشر الوظيفة أصلاً،
     * وبين is_active (تفعيل/تعطيل يدوي من الشركة بعد الموافقة). القيمتان
     * مستقلتان تماماً عن بعض، بنفس فلسفة Provider::moderation_status.
     *
     * - pending  : بانتظار مراجعة الأدمن (الحالة الافتراضية عند الإنشاء)
     * - approved : وافق الأدمن، تظهر للفريلانسرز ويمكن التقديم عليها
     * - rejected : رفضها الأدمن، لا تظهر إطلاقاً ولا يمكن التقديم عليها
     */
    public function up(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->enum('moderation_status', ['pending', 'approved', 'rejected'])
                ->default('pending')
                ->after('is_active');

            $table->text('rejection_reason')->nullable()->after('moderation_status');
        });
    }

    public function down(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->dropColumn(['moderation_status', 'rejection_reason']);
        });
    }
};