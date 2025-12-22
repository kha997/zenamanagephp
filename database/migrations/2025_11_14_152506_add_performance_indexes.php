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
     * Add performance indexes for frequently used query patterns.
     * All indexes follow multi-tenant pattern: (tenant_id, ...) for proper isolation.
     */
    public function up(): void
    {
        // Projects table indexes
        $this->addIndexIfNotExists('projects', 'idx_projects_tenant_priority', ['tenant_id', 'priority']);
        $this->addIndexIfNotExists('projects', 'idx_projects_tenant_client', ['tenant_id', 'client_id']);
        $this->addIndexIfNotExists('projects', 'idx_projects_tenant_owner', ['tenant_id', 'owner_id']);
        $this->addIndexIfNotExists('projects', 'idx_projects_tenant_dates', ['tenant_id', 'start_date', 'end_date']);
        $this->addIndexIfNotExists('projects', 'idx_projects_tenant_overdue', ['tenant_id', 'end_date', 'status']);
        $this->addIndexIfNotExists('projects', 'idx_projects_tenant_order', ['tenant_id', 'status', 'order']);

        // Tasks table indexes
        $this->addIndexIfNotExists('tasks', 'idx_tasks_tenant_assignee', ['tenant_id', 'assignee_id']);
        $this->addIndexIfNotExists('tasks', 'idx_tasks_tenant_priority', ['tenant_id', 'priority']);
        $this->addIndexIfNotExists('tasks', 'idx_tasks_tenant_overdue', ['tenant_id', 'end_date', 'status']);
        $this->addIndexIfNotExists('tasks', 'idx_tasks_project_status', ['project_id', 'status']);
        $this->addIndexIfNotExists('tasks', 'idx_tasks_tenant_created', ['tenant_id', 'created_at']);
        $this->addIndexIfNotExists('tasks', 'idx_tasks_tenant_dates', ['tenant_id', 'start_date', 'end_date']);

        // Users table indexes
        $this->addIndexIfNotExists('users', 'idx_users_tenant_active', ['tenant_id', 'is_active']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Projects table indexes
        $this->dropIndexIfExists('projects', 'idx_projects_tenant_priority');
        $this->dropIndexIfExists('projects', 'idx_projects_tenant_client');
        $this->dropIndexIfExists('projects', 'idx_projects_tenant_owner');
        $this->dropIndexIfExists('projects', 'idx_projects_tenant_dates');
        $this->dropIndexIfExists('projects', 'idx_projects_tenant_overdue');
        $this->dropIndexIfExists('projects', 'idx_projects_tenant_order');

        // Tasks table indexes
        $this->dropIndexIfExists('tasks', 'idx_tasks_tenant_assignee');
        $this->dropIndexIfExists('tasks', 'idx_tasks_tenant_priority');
        $this->dropIndexIfExists('tasks', 'idx_tasks_tenant_overdue');
        $this->dropIndexIfExists('tasks', 'idx_tasks_project_status');
        $this->dropIndexIfExists('tasks', 'idx_tasks_tenant_created');
        $this->dropIndexIfExists('tasks', 'idx_tasks_tenant_dates');

        // Users table indexes
        $this->dropIndexIfExists('users', 'idx_users_tenant_active');
    }

    /**
     * Add index if it doesn't exist (database-agnostic)
     */
    private function addIndexIfNotExists(string $table, string $indexName, array $columns): void
    {
        // Check if table exists first
        if (!Schema::hasTable($table)) {
            return;
        }

        // Check if index already exists
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
                $table->index($columns, $indexName);
            });
        } catch (\Exception $e) {
            // Ignore errors in test environment or if index already exists
            if (app()->environment() !== 'testing') {
                \Log::warning("Failed to add index {$indexName} on table {$table}: " . $e->getMessage());
            }
        }
    }

    /**
     * Drop index if it exists (database-agnostic)
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        if (!$this->indexExists($table, $indexName)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        } catch (\Exception $e) {
            // Ignore errors in test environment
            if (app()->environment() !== 'testing') {
                \Log::warning("Failed to drop index {$indexName} on table {$table}: " . $e->getMessage());
            }
        }
    }

    /**
     * Check if index exists (database-agnostic)
     */
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $driver = config('database.default');
            $connection = Schema::getConnection();

            if ($driver === 'sqlite') {
                // SQLite: Check sqlite_master
                $indexes = DB::select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name=? AND name=?", [$table, $indexName]);
                return !empty($indexes);
            } elseif ($driver === 'mysql') {
                // MySQL: Use SHOW INDEX
                $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
                return !empty($indexes);
            } elseif ($driver === 'pgsql') {
                // PostgreSQL: Query pg_indexes
                $indexes = DB::select("SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?", [$table, $indexName]);
                return !empty($indexes);
            } else {
                // Fallback: Try Doctrine Schema Manager
                try {
                    $indexes = $connection->getDoctrineSchemaManager()->listTableIndexes($table);
                    return array_key_exists($indexName, $indexes);
                } catch (\Exception $e) {
                    return false;
                }
            }
        } catch (\Exception $e) {
            // If we can't check, assume it doesn't exist (safer for migrations)
            return false;
        }
    }
};
