<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add missing created_at indexes for time-based queries
        try {
            Schema::table('projects', function (Blueprint $table) {
                $table->index(['created_at'], 'projects_created_at_index');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }
        
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['created_at'], 'users_created_at_index');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }
        
        // Add composite indexes for common N+1 patterns
        try {
            Schema::table('projects', function (Blueprint $table) {
                $table->index(['tenant_id', 'status'], 'projects_tenant_status_index');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }
        
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['tenant_id', 'status'], 'users_tenant_status_index');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }
        
        // Add indexes for document relationships
        try {
            Schema::table('document_versions', function (Blueprint $table) {
                $table->index(['document_id', 'created_at'], 'document_versions_document_created_index');
                $table->index(['created_by', 'created_at'], 'document_versions_created_by_created_index');
            });
        } catch (\Exception $e) {
            // Indexes might already exist
        }
        
        // Add indexes for task assignments
        try {
            Schema::table('task_assignments', function (Blueprint $table) {
                $table->index(['task_id', 'user_id'], 'task_assignments_task_user_index');
                $table->index(['user_id', 'created_at'], 'task_assignments_user_created_index');
            });
        } catch (\Exception $e) {
            // Indexes might already exist
        }
        
        // Add indexes for project team members
        try {
            Schema::table('project_team_members', function (Blueprint $table) {
                $table->index(['project_id', 'user_id'], 'project_team_members_project_user_index');
                $table->index(['user_id', 'created_at'], 'project_team_members_user_created_index');
            });
        } catch (\Exception $e) {
            // Indexes might already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndexIfExists('projects', 'projects_created_at_index');
        $this->dropIndexIfExists('projects', 'projects_tenant_status_index');
        $this->dropIndexIfExists('tasks', 'tasks_created_at_index');
        $this->dropIndexIfExists('tasks', 'tasks_project_status_index');
        $this->dropIndexIfExists('tasks', 'tasks_assignee_status_index');
        $this->dropIndexIfExists('users', 'users_created_at_index');
        $this->dropIndexIfExists('users', 'users_tenant_status_index');
        $this->dropIndexIfExists('document_versions', 'document_versions_document_created_index');
        $this->dropIndexIfExists('document_versions', 'document_versions_created_by_created_index');
        $this->dropIndexIfExists('task_assignments', 'task_assignments_task_user_index');
        $this->dropIndexIfExists('task_assignments', 'task_assignments_user_created_index');
        $this->dropIndexIfExists('project_team_members', 'project_team_members_project_user_index');
        $this->dropIndexIfExists('project_team_members', 'project_team_members_user_created_index');
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if (! $this->indexExists($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName) {
            $table->dropIndex($indexName);
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $databaseName = Schema::getConnection()->getDatabaseName();
            $result = DB::selectOne(
                'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
                [$databaseName, $tableName, $indexName]
            );

            return $result !== null;
        }

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$tableName}')");

            foreach ($indexes as $index) {
                if (isset($index->name) && $index->name === $indexName) {
                    return true;
                }
            }
        }

        return false;
    }
};
