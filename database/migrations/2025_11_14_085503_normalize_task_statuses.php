<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Normalize existing task status data and add CHECK constraint
     */
    public function up(): void
    {
        // Step 1: Normalize existing status data
        // Map old status values to new standardized values
        $statusMappings = [
            'pending' => 'backlog',
            'completed' => 'done',
            'cancelled' => 'canceled',
            'on_hold' => 'blocked',
        ];

        foreach ($statusMappings as $oldStatus => $newStatus) {
            DB::table('tasks')
                ->where('status', $oldStatus)
                ->update(['status' => $newStatus]);
        }

        // Step 2: Add CHECK constraint for status values
        // Note: MySQL 8.0.16+ supports CHECK constraints
        // For older MySQL versions, this will be handled at application level
        if (DB::getDriverName() === 'mysql') {
            $mysqlVersion = DB::select('SELECT VERSION() as version')[0]->version;
            $versionParts = explode('.', $mysqlVersion);
            $majorVersion = (int) $versionParts[0];
            $minorVersion = (int) $versionParts[1];
            $patchVersion = (int) ($versionParts[2] ?? 0);

            // MySQL 8.0.16+ supports CHECK constraints
            if ($majorVersion > 8 || ($majorVersion === 8 && $minorVersion > 0) || 
                ($majorVersion === 8 && $minorVersion === 0 && $patchVersion >= 16)) {
                DB::statement("
                    ALTER TABLE tasks 
                    ADD CONSTRAINT tasks_status_check 
                    CHECK (status IN ('backlog', 'in_progress', 'blocked', 'done', 'canceled'))
                ");
            }
        } elseif (DB::getDriverName() === 'pgsql') {
            // PostgreSQL supports CHECK constraints
            DB::statement("
                ALTER TABLE tasks 
                ADD CONSTRAINT tasks_status_check 
                CHECK (status IN ('backlog', 'in_progress', 'blocked', 'done', 'canceled'))
            ");
        }
        // For SQLite and other databases, validation will be handled at application level
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop CHECK constraint if it exists
        if (DB::getDriverName() === 'mysql') {
            $mysqlVersion = DB::select('SELECT VERSION() as version')[0]->version;
            $versionParts = explode('.', $mysqlVersion);
            $majorVersion = (int) $versionParts[0];
            $minorVersion = (int) $versionParts[1];
            $patchVersion = (int) ($versionParts[2] ?? 0);

            if ($majorVersion > 8 || ($majorVersion === 8 && $minorVersion > 0) || 
                ($majorVersion === 8 && $minorVersion === 0 && $patchVersion >= 16)) {
                DB::statement("ALTER TABLE tasks DROP CHECK IF EXISTS tasks_status_check");
            }
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE tasks DROP CONSTRAINT IF EXISTS tasks_status_check");
        }

        // Note: We don't reverse the status normalization as it's a data migration
        // Reversing would require knowing the original status values which we don't track
    }
};
