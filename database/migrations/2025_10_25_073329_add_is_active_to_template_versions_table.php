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
        Schema::table('template_versions', function (Blueprint $table) {
            // Add is_active column if it doesn't exist
            if (!Schema::hasColumn('template_versions', 'is_active')) {
                $table->boolean('is_active')->default(false)->after('created_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_versions', function (Blueprint $table) {
            if (Schema::hasColumn('template_versions', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};