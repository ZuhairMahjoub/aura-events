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
        Schema::table('providers', function (Blueprint $table) {
            $table->enum('moderation_status', ['pending', 'approved', 'rejected'])
                  ->default('pending')
                  ->after('user_id');

            // سيتواجد الحقل بنهاية الجدول وهو سليم تماماً
            $table->text('rejection_reason')->nullable(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn(['moderation_status', 'rejection_reason']);
        });
    }
};