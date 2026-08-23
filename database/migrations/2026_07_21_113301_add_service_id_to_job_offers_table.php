<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->foreignUlid('service_id')
                ->nullable()
                ->after('company_id')
                ->constrained('services')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
        });
    }
};