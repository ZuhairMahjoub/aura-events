<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('booking_id')->constrained('bookings')->cascadeOnDelete();
            
            // بما أننا سنرفع PDF، الـ transaction_id قد لا يكون متاحاً في البداية، فنجعله nullable
            $table->string('transaction_id')->nullable()->index(); 
            
            $table->string('provider')->default('shamcash'); 
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('SYP');
            
            // أضفنا هذا الحقل لتخزين مسار ملف الـ PDF المرفوع
            $table->string('proof_file_path')->nullable(); 
            
            // الحالة الافتراضية تكون pending حتى تتأكد أنت أو النظام من صحة الملف
$table->enum('status', ['pending', 'confirmed', 'failed', 'refunded'])->default('pending');            
            // حقل للملاحظات في حال أردت كتابة سبب رفض الدفعة أو تعليق عليها
            $table->text('admin_notes')->nullable(); 
            
            $table->timestamps();
        });
    }

   
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};