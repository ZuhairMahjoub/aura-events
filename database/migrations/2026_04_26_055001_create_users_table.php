
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
    Schema::create('users', function (Blueprint $table) {
        $table->ulid('id')->primary();
        $table->string('first_name');
        $table->string('last_name');
        $table->string('email')->unique()->nullable();
        $table->string('phone')->unique()->nullable();
        $table->string('password')->nullable(); // nullable لدعم السوشيال ميديا

        // الحقول الجوهرية للـ Logic تبعنا (من الـ ERD)
        $table->enum('status', ['active', 'inactive', 'banned'])->default('active');
        $table->boolean('is_profile_completed')->default(false);

        // التحقق (Verification)
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamp('phone_verified_at')->nullable();

        // الإعدادات (Settings)
        $table->string('settings_language')->default('ar'); // غيرتها لـ ar كديفولت بناءً على مشروعك
        $table->string('settings_theme')->default('light');

        // السوشيال لوجن (Social Login)
        $table->string('provider')->nullable();
        $table->string('provider_id')->nullable();

        $table->rememberToken();
        $table->softDeletes();
        $table->timestamps();
    });

    // جداول التوكنات والسيشنز (خليها مثل ما هي)
    Schema::create('password_reset_tokens', function (Blueprint $table) {
        $table->string('email')->primary();
        $table->string('token');
        $table->timestamp('created_at')->nullable();
    });

    Schema::create('sessions', function (Blueprint $table) {
        $table->string('id')->primary(); // في العادة الـ ID تبع السيشن يكون string عادي بـ لارافل
        $table->foreignUlid('user_id')->nullable()->index();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->longText('payload');
        $table->integer('last_activity')->index();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};

