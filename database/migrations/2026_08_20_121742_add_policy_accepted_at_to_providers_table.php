<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            // وقت موافقة المزوّد على سياسة الاستخدام — null يعني لم يوافق بعد.
            // نتحقق من هذا الحقل في EnsureUserIsApprovedProvider بعد فحص
            // moderation_status/is_active، وقبل السماح بالوصول للوحة التحكم.
            $table->timestamp('policy_accepted_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn('policy_accepted_at');
        });
    }
};