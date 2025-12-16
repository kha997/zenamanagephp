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
     * Adds composite indexes for efficient list queries with tenant isolation.
     * These indexes optimize queries that filter by tenant_id and sort by created_at or other fields.
     * 
     * Indexes added:
     * - projects: (tenant_id, created_at), (tenant_id, status, created_at)
     * - tasks: (tenant_id, created_at), (tenant_id, status, created_at), (tenant_id, project_id, created_at)
     * - documents: (tenant_id, created_at), (tenant_id, project_id, created_at)
     * - For ULID tables: (tenant_id, id) as secondary index
     */
    public function up(): void
    {
        // Projects indexes
        if (Schema::hasTable('projects')) {
            $this->addIndexIfNotExists('projects', 'projects_tenant_created_idx', ['tenant_id', 'created_at']);
            $this->addIndexIfNotExists('projects', 'projects_tenant_status_created_idx', ['tenant_id', 'status', 'created_at']);
            // ULID secondary index for cursor pagination
            if (Schema::hasColumn('projects', 'id')) {
                $this->addIndexIfNotExists('projects', 'projects_tenant_id_idx', ['tenant_id', 'id']);
            }
        }

        // Tasks indexes
        if (Schema::hasTable('tasks')) {
            $this->addIndexIfNotExists('tasks', 'tasks_tenant_created_idx', ['tenant_id', 'created_at']);
            $this->addIndexIfNotExists('tasks', 'tasks_tenant_status_created_idx', ['tenant_id', 'status', 'created_at']);
            $this->addIndexIfNotExists('tasks', 'tasks_tenant_project_created_idx', ['tenant_id', 'project_id', 'created_at']);
            // ULID secondary index
            if (Schema::hasColumn('tasks', 'id')) {
                $this->addIndexIfNotExists('tasks', 'tasks_tenant_id_idx', ['tenant_id', 'id']);
            }
        }

        // Documents indexes
        if (Schema::hasTable('documents')) {
            $this->addIndexIfNotExists('documents', 'documents_tenant_created_idx', ['tenant_id', 'created_at']);
            $this->addIndexIfNotExists('documents', 'documents_tenant_project_created_idx', ['tenant_id', 'project_id', 'created_at']);
            // ULID secondary index
            if (Schema::hasColumn('documents', 'id')) {
                $this->addIndexIfNotExists('documents', 'documents_tenant_id_idx', ['tenant_id', 'id']);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop projects indexes
        $this->dropIndexIfExists('projects', 'projects_tenant_created_idx');
        $this->dropIndexIfExists('projects', 'projects_tenant_status_created_idx');
        $this->dropIndexIfExists('projects', 'projects_tenant_id_idx');

        // Drop tasks indexes
        $this->dropIndexIfExists('tasks', 'tasks_tenant_created_idx');
        $this->dropIndexIfExists('tasks', 'tasks_tenant_status_created_idx');
        $this->dropIndexIfExists('tasks', 'tasks_tenant_project_created_idx');
        $this->dropIndexIfExists('tasks', 'tasks_tenant_id_idx');

        // Drop documents indexes
        $this->dropIndexIfExists('documents', 'documents_tenant_created_idx');
        $this->dropIndexIfExists('documents', 'documents_tenant_project_created_idx');
        $this->dropIndexIfExists('documents', 'documents_tenant_id_idx');
    }

    /**
     * Add index if it doesn't exist
     */
    private function addIndexIfNotExists(string $table, string $indexName, array $columns): void
    {
        if (!$this->hasIndex($table, $indexName)) {
            Schema::table($table, function (Blueprint $table) use ($indexName, $columns) {
                $table->index($columns, $indexName);
            });
        }
    }

    /**
     * Drop index if it exists
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->hasIndex($table, $indexName)) {
            try {
                Schema::table($table, function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            } catch (\Exception $e) {
                try {
                    DB::statement("DROP INDEX `{$indexName}` ON `{$table}`");
                } catch (\Exception $e2) {
                    // Ignore if index doesn't exist
                }
            }
        }
    }

    /**
     * Check if table has index
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        try {
            $result = DB::select(
                "SELECT COUNT(*) as count 
                 FROM information_schema.statistics 
                 WHERE table_schema = ? 
                 AND table_name = ? 
                 AND index_name = ?",
                [$databaseName, $table, $indexName]
            );
            
            return $result[0]->count > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
};

