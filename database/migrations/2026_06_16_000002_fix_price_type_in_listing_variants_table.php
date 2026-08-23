<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix: The original migration defined price_type as ENUM('fixed', 'hourly'),
 * but the Requests and business logic use 'per_hour' and 'per_day'.
 * MySQL ENUM alteration requires a full column re-definition.
 *
 * Strategy: change the column to VARCHAR(20) so it is forward-compatible
 * with any future price types without needing another migration.
 * Application-level validation (FormRequest) enforces the allowed values.
 *
 * Existing 'hourly' values are migrated to 'per_hour' during up().
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Migrate legacy 'hourly' rows before changing the column type
        DB::table('listing_variants')
            ->where('price_type', 'hourly')
            ->update(['price_type' => 'per_hour']);

        // Step 2: Change ENUM → VARCHAR so new values (per_day etc.) are accepted
        Schema::table('listing_variants', function (Blueprint $table) {
            $table->string('price_type', 20)->default('fixed')->change();
        });
    }

    public function down(): void
    {
        // Revert 'per_hour' back to 'hourly' before restoring the enum
        DB::table('listing_variants')
            ->where('price_type', 'per_hour')
            ->update(['price_type' => 'hourly']);

        // Rows with 'per_day' would violate the old enum — set them to 'fixed'
        DB::table('listing_variants')
            ->where('price_type', 'per_day')
            ->update(['price_type' => 'fixed']);

        Schema::table('listing_variants', function (Blueprint $table) {
            $table->enum('price_type', ['fixed', 'hourly'])->default('fixed')->change();
        });
    }
};
