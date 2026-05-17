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
    Schema::create('providers', function (Blueprint $table) {
        $table->ulid('id')->primary();
        // الربط مع اليوزر (One-to-One)
$table->foreignUlid('user_id')->unique()->constrained('users')->onDelete('cascade');
// 🌟 كلمة unique() هنا هي القفل الحديدي في الداتابيز        // الربط مع الحي
        $table->foreignId('district_id')->constrained('districts')->onDelete('cascade');
    
    // الحقل النصي الحر للتفاصيل (الشارع، البناية، الطابق)
        $table->string('address_details')->nullable();        
        $table->string('brand_name');
        $table->enum('provider_type', ['company', 'freelancer']);
        $table->decimal('rating', 3, 2)->default(0.00);
        $table->boolean('is_verified')->default(false);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
