<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freelancer_blocked_dates', function (Blueprint $table) {
            // 1. Add new columns
            $table->time('start_time')->nullable()->after('blocked_date');
            $table->time('end_time')->nullable()->after('start_time');
            
            $table->text('note')->nullable()->after('end_time'); 

            // 2. Add the non-unique index FIRST so MySQL foreign key has a backing index
            $table->index(['freelancer_id', 'blocked_date']);

            // 3. NOW drop the unique index safely
            $table->dropUnique(['freelancer_id', 'blocked_date']);
        });
    }

    public function down(): void
    {
        Schema::table('freelancer_blocked_dates', function (Blueprint $table) {
            $table->unique(['freelancer_id', 'blocked_date']);
            $table->dropIndex(['freelancer_id', 'blocked_date']);
             $table->dropColumn(['start_time', 'end_time', 'note']);
        });
    }
};