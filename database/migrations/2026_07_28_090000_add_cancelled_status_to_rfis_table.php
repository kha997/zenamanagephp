<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add 'cancelled' to the rfis.status enum so RfiLifecycleService::cancel()
     * can persist a terminal cancelled state. SQLite (used in tests) has no
     * enum constraint, so this only matters for MySQL/MariaDB.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE rfis MODIFY COLUMN status ENUM('open', 'pending', 'in_progress', 'answered', 'closed', 'escalated', 'cancelled') NOT NULL DEFAULT 'open'"
            );
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE rfis MODIFY COLUMN status ENUM('open', 'pending', 'in_progress', 'answered', 'closed', 'escalated') NOT NULL DEFAULT 'open'"
            );
        }
    }
};
