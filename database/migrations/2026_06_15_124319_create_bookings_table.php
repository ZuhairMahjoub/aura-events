<?php
// database/migrations/2026_06_15_124319_create_bookings_table.php
//
// نسخة معدَّلة من الـ migration الأصلي، مع دمج إصلاحات الأخطاء (أ) و(ب)
// مباشرة في تعريف الجدول بدل migration منفصل لاحقاً — مناسبة فقط إذا
// كانت قاعدة بياناتك ما زالت في طور التطوير ويمكنك إعادة migrate:fresh.
// إن كان لديك بيانات حقيقية بالفعل، استخدم بدلاً من هذا الملف الـ
// migration المنفصل: 2026_07_01_000001_fix_bookings_table_columns.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {

            $table->ulid('id')->primary();

            // ── الأطراف ────────────────────────────────────────────────
            $table->foreignUlid('user_id')
                  ->constrained('users')
                  ->restrictOnDelete();

            $table->foreignUlid('provider_id')         // denormalized للاستعلامات السريعة
                  ->constrained('providers')
                  ->restrictOnDelete();

            // ── الهرمية ─────────────────────────────────────────────────
            $table->foreignUlid('listing_id')
                  ->constrained('listings')
                  ->restrictOnDelete();

            $table->foreignUlid('listing_variant_id')
                  ->constrained('listing_variants')
                  ->restrictOnDelete();

            // nullable: موجود فقط للحجوزات الزمنية (صالات، خدمات بـ slots)
            $table->foreignUlid('listing_slot_id')
                  ->nullable()
                  ->constrained('listing_slots')
                  ->nullOnDelete();

            // ── نوع الحجز (discriminator للـ Strategy) ─────────────────
            $table->enum('booking_type', [
                'physical_product',
                'hall',
                'service',
                'package',
                // أضف النوع الرابع هنا فقط، لا شيء آخر يتغير
            ]);

            // ── الحالة ──────────────────────────────────────────────────
            $table->enum('status', [
                'pending',      // بانتظار موافقة Provider
                'accepted',     // قبل Provider
                'rejected',     // رفض Provider
                'confirmed',    // دفع العميل (انظر BookingService::confirmPayment)
                'completed',    // انتهى الحدث
                'cancelled',    // إلغاء من أي طرف
            ])->default('pending')->index();

            $table->enum('payment_status', [
                'unpaid', 'paid', 'refunded'
            ])->default('unpaid');

            // ── بيانات الحجز الأساسية ────────────────────────────────────
            $table->unsignedSmallInteger('quantity')->default(1);  // للمنتجات المادية
            $table->decimal('total_price', 12, 2);
            $table->string('currency', 3)->default('SYP');

            // ── Snapshot الزمني (denormalized) ───────────────────────────
            $table->date('booked_date')->nullable()->index();
            $table->time('booked_start_time')->nullable();
            $table->time('booked_end_time')->nullable();

            // ── بيانات إضافية خاصة بالنوع ───────────────────────────────
            $table->json('metadata')->nullable();

            // ── الإلغاء والإكمال ──────────────────────────────────────────
            $table->text('customer_notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            // إصلاح (ب): أُضيفت 'organizer' و'admin' لتطابق فعلياً
            // BookingService::VALID_CANCELLERS المستخدمة في الكود
            // (سابقاً كانت ['user','provider','system'] فقط، وأي محاولة
            // إلغاء من العميل 'organizer' كانت تفشل بخطأ SQL).
            $table->enum('cancelled_by', ['organizer', 'provider', 'admin', 'system'])->nullable();

            // إصلاح (أ): هذا العمود كان مفقوداً بالكامل رغم أن
            // BookingService::complete() يكتب إليه — أُضيف هنا مباشرة
            // بدل الاعتماد على migration تصحيحي لاحق.
            $table->timestamp('completed_at')->nullable();

            // ── الدفع ───────────────────────────────────────────────────
            $table->string('payment_reference')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // ── Indexes للاستعلامات الشائعة ─────────────────────────────
            $table->index(['user_id', 'status']);
            $table->index(['provider_id', 'booked_date', 'status']);
            $table->index(['listing_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};