<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Round 94: Project Health Hardening (schema + caching TTL + snapshot command)
 * 
 * Tightens the project_health_snapshots schema by making tenant_id and project_id NOT NULL.
 * This ensures data integrity and consistency with other tenant-scoped tables.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if table exists before modifying it
        if (!Schema::hasTable('project_health_snapshots')) {
            return; // Table doesn't exist yet, skip this migration
        }

        // First, clean up any invalid rows (tenant_id or project_id is NULL)
        // These are invalid for this domain and should be removed
        DB::table('project_health_snapshots')
            ->whereNull('tenant_id')
            ->orWhereNull('project_id')
            ->delete();

        // Now make the columns NOT NULL
        Schema::table('project_health_snapshots', function (Blueprint $table) {
            $table->string('tenant_id')->nullable(false)->change();
            $table->string('project_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to nullable (for rollback purposes)
        Schema::table('project_health_snapshots', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->change();
            $table->string('project_id')->nullable()->change();
        });
    }
};
