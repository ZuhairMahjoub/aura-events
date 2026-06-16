<?php
// database/migrations/xxxx_create_bookings_table.php

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
                'confirmed',    // دفع العميل
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
            // يُحفظ وقت الحجز الفعلي بغض النظر عن تغييرات Provider لاحقاً
            $table->date('booked_date')->nullable()->index();
            $table->time('booked_start_time')->nullable();
            $table->time('booked_end_time')->nullable();

            // ── بيانات إضافية خاصة بالنوع ───────────────────────────────
            // physical_product: {is_rental: true, rental_days: 3, delivery_address: "..."}
            // hall:             {event_type: "wedding", guest_count: 150}
            // service:          {event_description: "..."}
            $table->json('metadata')->nullable();

            // ── الإلغاء ─────────────────────────────────────────────────
            $table->text('customer_notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->enum('cancelled_by', ['user', 'provider', 'system'])->nullable();

            // ── الدفع ───────────────────────────────────────────────────
            $table->string('payment_reference')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // ── Indexes للاستعلامات الشائعة ─────────────────────────────
            // "كل حجوزات المستخدم X"
            $table->index(['user_id', 'status']);
            // "كل حجوزات Provider Y في تاريخ محدد"
            $table->index(['provider_id', 'booked_date', 'status']);
            // "حجوزات listing معين"
            $table->index(['listing_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};