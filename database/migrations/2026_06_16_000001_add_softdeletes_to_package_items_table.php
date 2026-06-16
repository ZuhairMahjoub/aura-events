<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix: PackageItem model uses the SoftDeletes trait but the table was created
 * without a deleted_at column, causing queries that filter on deleted_at
 * (e.g. ->forceDelete(), soft-delete scopes) to throw a column-not-found error.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_items', function (Blueprint $table) {
            $table->softDeletes()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('package_items', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
