<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('pending_expires_at')->nullable()->after('booked_end_time')->index();
        });

        DB::statement("ALTER TABLE bookings MODIFY status ENUM(
        'pending', 'accepted', 'rejected', 'confirmed', 'completed', 'cancelled', 'expired'
    ) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::table('bookings')->where('status', 'expired')->update(['status' => 'cancelled']);

        DB::statement("ALTER TABLE bookings MODIFY status ENUM(
        'pending', 'accepted', 'rejected', 'confirmed', 'completed', 'cancelled'
    ) NOT NULL DEFAULT 'pending'");

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('pending_expires_at');
        });
    }
};
