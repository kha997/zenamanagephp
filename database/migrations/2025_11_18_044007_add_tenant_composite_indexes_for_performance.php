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
     * Adds composite indexes for common query patterns with tenant_id.
     * These indexes optimize queries that filter by tenant_id + status + created_at,
     * which are common patterns in list endpoints.
     * 
     * Indexes added:
     * - (tenant_id, status, created_at) for filtering by tenant, status, and sorting by date
     * - (tenant_id, project_id, status) for tasks filtered by project and status
     * - (tenant_id, assignee_id, status) for user task lists
     * - (tenant_id, due_date) for tasks filtered by due date
     */
    public function up(): void
    {
        // Projects: (tenant_id, status, created_at) for list filtering
        if (Schema::hasTable('projects') && Schema::hasColumn('projects', 'status')) {
            $this->addIndexIfNotExists('projects', 'idx_projects_tenant_status_created', ['tenant_id', 'status', 'created_at']);
        }

        // Tasks: (tenant_id, status, created_at) for list filtering
        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'status')) {
            $this->addIndexIfNotExists('tasks', 'idx_tasks_tenant_status_created', ['tenant_id', 'status', 'created_at']);
        }

        // Tasks: (tenant_id, project_id, status) for project task lists
        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'project_id') && Schema::hasColumn('tasks', 'status')) {
            $this->addIndexIfNotExists('tasks', 'idx_tasks_tenant_project_status', ['tenant_id', 'project_id', 'status']);
        }

        // Tasks: (tenant_id, assignee_id, status) for user task lists
        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'assignee_id') && Schema::hasColumn('tasks', 'status')) {
            $this->addIndexIfNotExists('tasks', 'idx_tasks_tenant_assignee_status', ['tenant_id', 'assignee_id', 'status']);
        }

        // Tasks: (tenant_id, due_date) for filtering by due date
        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'due_date')) {
            $this->addIndexIfNotExists('tasks', 'idx_tasks_tenant_due_date', ['tenant_id', 'due_date']);
        }

        // Documents: (tenant_id, status, created_at) for list filtering
        if (Schema::hasTable('documents') && Schema::hasColumn('documents', 'status')) {
            $this->addIndexIfNotExists('documents', 'idx_documents_tenant_status_created', ['tenant_id', 'status', 'created_at']);
        }

        // Change Requests: (tenant_id, status, created_at) for list filtering
        if (Schema::hasTable('change_requests') && Schema::hasColumn('change_requests', 'status')) {
            $this->addIndexIfNotExists('change_requests', 'idx_change_requests_tenant_status_created', ['tenant_id', 'status', 'created_at']);
        }

        // Quotes: (tenant_id, status, created_at) for list filtering
        if (Schema::hasTable('quotes') && Schema::hasColumn('quotes', 'status')) {
            $this->addIndexIfNotExists('quotes', 'idx_quotes_tenant_status_created', ['tenant_id', 'status', 'created_at']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexes = [
            'projects' => ['idx_projects_tenant_status_created'],
            'tasks' => [
                'idx_tasks_tenant_status_created',
                'idx_tasks_tenant_project_status',
                'idx_tasks_tenant_assignee_status',
                'idx_tasks_tenant_due_date',
            ],
            'documents' => ['idx_documents_tenant_status_created'],
            'change_requests' => ['idx_change_requests_tenant_status_created'],
            'quotes' => ['idx_quotes_tenant_status_created'],
        ];

        foreach ($indexes as $table => $tableIndexes) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($tableIndexes as $indexName) {
                if ($this->hasIndex($table, $indexName)) {
                    Schema::table($table, function (Blueprint $table) use ($indexName) {
                        $table->dropIndex($indexName);
                    });
                }
            }
        }
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
