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
     * Add composite index, change order to decimal, and add version column
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Add version column for optimistic locking
            if (!Schema::hasColumn('tasks', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('order');
            }
        });

        // Change order column from integer to decimal(18,6) for midpoint strategy
        // We need to do this with raw SQL as Laravel's change() method has limitations
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE tasks MODIFY COLUMN `order` DECIMAL(18,6) DEFAULT 1000000');
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tasks ALTER COLUMN "order" TYPE DECIMAL(18,6)');
            DB::statement('ALTER TABLE tasks ALTER COLUMN "order" SET DEFAULT 1000000');
        } else {
            // For SQLite, we need to recreate the column
            // This is more complex and may require table recreation
            // For now, we'll keep it as integer and handle in application
        }

        // Add composite index for efficient Kanban queries
        // Index on (project_id, status, order) for sorting tasks within status columns
        Schema::table('tasks', function (Blueprint $table) {
            // Check if index doesn't already exist (database-specific)
            $indexExists = false;
            $driver = DB::getDriverName();
            
            if ($driver === 'mysql') {
                $indexes = DB::select("SHOW INDEX FROM tasks WHERE Key_name = 'idx_tasks_project_status_order'");
                $indexExists = !empty($indexes);
            } elseif ($driver === 'pgsql') {
                $indexes = DB::select("SELECT 1 FROM pg_indexes WHERE tablename = 'tasks' AND indexname = 'idx_tasks_project_status_order'");
                $indexExists = !empty($indexes);
            } elseif ($driver === 'sqlite') {
                // SQLite: Check using PRAGMA
                $indexes = DB::select("PRAGMA index_list(tasks)");
                foreach ($indexes as $idx) {
                    if ($idx->name === 'idx_tasks_project_status_order') {
                        $indexExists = true;
                        break;
                    }
                }
            }
            
            if (!$indexExists) {
                $table->index(['project_id', 'status', 'order'], 'idx_tasks_project_status_order');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Drop composite index
            $table->dropIndex('idx_tasks_project_status_order');
            
            // Drop version column
            if (Schema::hasColumn('tasks', 'version')) {
                $table->dropColumn('version');
            }
        });

        // Revert order column back to integer
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE tasks MODIFY COLUMN `order` INTEGER DEFAULT 0');
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tasks ALTER COLUMN "order" TYPE INTEGER');
            DB::statement('ALTER TABLE tasks ALTER COLUMN "order" SET DEFAULT 0');
        }
    }
};
