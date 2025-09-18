<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add deprecation notice to zena_tasks table
        if (Schema::hasTable('zena_tasks')) {
            Schema::table('zena_tasks', function (Blueprint $table) {
                $table->string('deprecated_notice')->nullable()->after('id');
            });
            
            // Add deprecation notice to all existing records
            DB::table('zena_tasks')->update([
                'deprecated_notice' => 'This table is deprecated. Use tasks table instead.'
            ]);
        }

        // Add deprecation notice to zena_task_assignments table
        if (Schema::hasTable('zena_task_assignments')) {
            Schema::table('zena_task_assignments', function (Blueprint $table) {
                $table->string('deprecated_notice')->nullable()->after('id');
            });
            
            // Add deprecation notice to all existing records
            DB::table('zena_task_assignments')->update([
                'deprecated_notice' => 'This table is deprecated. Use task_assignments table instead.'
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove deprecation notices
        if (Schema::hasTable('zena_tasks')) {
            Schema::table('zena_tasks', function (Blueprint $table) {
                $table->dropColumn('deprecated_notice');
            });
        }

        if (Schema::hasTable('zena_task_assignments')) {
            Schema::table('zena_task_assignments', function (Blueprint $table) {
                $table->dropColumn('deprecated_notice');
            });
        }
    }
};