<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;

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
        $this->ensureSingleColumnIndexExists('project_team_members', 'project_id', 'project_team_members_project_id_fk_idx');
        $this->ensureSingleColumnIndexExists('project_team_members', 'user_id', 'project_team_members_user_id_fk_idx');

        $indexesToDrop = [
            'projects' => [
                'projects_created_at_index',
                'projects_tenant_status_index',
            ],
            'tasks' => [
                'tasks_created_at_index',
                'tasks_project_status_index',
                'tasks_assignee_status_index',
            ],
            'users' => [
                'users_created_at_index',
                'users_tenant_status_index',
            ],
            'document_versions' => [
                'document_versions_document_created_index',
                'document_versions_created_by_created_index',
            ],
            'task_assignments' => [
                'task_assignments_task_user_index',
                'task_assignments_user_created_index',
            ],
        ];

        foreach ($indexesToDrop as $tableName => $indexNames) {
            foreach ($indexNames as $indexName) {
                $this->dropIndexIfExists($tableName, $indexName);
            }
        }
        
        $this->dropIndexIfExists('project_team_members', 'project_team_members_project_user_index');
        $this->dropIndexIfExists('project_team_members', 'project_team_members_user_created_index');
    }

    private function ensureSingleColumnIndexExists(string $tableName, string $columnName, string $indexName): void
    {
        if ($this->singleColumnIndexExists($tableName, $columnName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columnName, $indexName) {
            $table->index([$columnName], $indexName);
        });
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if (! $this->indexExists($tableName, $indexName)) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        } catch (QueryException $exception) {
            // Another migration/process might have dropped it after existence check.
            if (! $this->indexExists($tableName, $indexName) && $this->isMissingIndexError($exception)) {
                return;
            }

            throw $exception;
        }
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
            $quotedTableName = str_replace("'", "''", $tableName);
            $indexes = DB::select("PRAGMA index_list('{$quotedTableName}')");

            foreach ($indexes as $index) {
                if (isset($index->name) && $index->name === $indexName) {
                    return true;
                }
            }
        }

        return false;
    }

    private function singleColumnIndexExists(string $tableName, string $columnName, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $databaseName = Schema::getConnection()->getDatabaseName();
            $result = DB::selectOne(
                'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? AND seq_in_index = 1 AND column_name = ? LIMIT 1',
                [$databaseName, $tableName, $indexName, $columnName]
            );

            return $result !== null;
        }

        if ($driver === 'sqlite') {
            if (! $this->indexExists($tableName, $indexName)) {
                return false;
            }

            $quotedIndexName = str_replace("'", "''", $indexName);
            $indexColumns = DB::select("PRAGMA index_info('{$quotedIndexName}')");
            if (count($indexColumns) !== 1) {
                return false;
            }

            $firstColumn = $indexColumns[0]->name ?? null;

            return $firstColumn === $columnName;
        }

        return false;
    }

    private function isMissingIndexError(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = $exception->errorInfo[1] ?? null;

        return $sqlState === '42000' && (int) $driverCode === 1091;
    }
};
