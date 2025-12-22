<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Support\MigrationDriver;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds composite indexes (tenant_id, created_at) and (tenant_id, id/ulid) 
     * for optimal cursor-based pagination and common query patterns.
     */
    public function up(): void
    {
        if (MigrationDriver::isSqlite()) {
            return;
        }
        // Projects: (tenant_id, created_at DESC) for cursor pagination
        if (Schema::hasTable('projects')) {
            if (!$this->hasIndex('projects', 'projects_tenant_created_at_index')) {
                Schema::table('projects', function (Blueprint $table) {
                    $table->index(['tenant_id', 'created_at'], 'projects_tenant_created_at_index');
                });
            }
            
            // (tenant_id, id) for ULID-based cursor pagination
            // Check for both possible index names (from different migrations)
            $indexName = 'projects_tenant_id_index';
            $legacyIndexName = 'idx_projects_tenant_id';
            if (!$this->hasIndex('projects', $indexName) && !$this->hasIndex('projects', $legacyIndexName)) {
                try {
                    Schema::table('projects', function (Blueprint $table) use ($indexName) {
                        $table->index(['tenant_id', 'id'], $indexName);
                    });
                } catch (\Exception $e) {
                    \Log::warning('Failed to add projects_tenant_id_index: ' . $e->getMessage());
                }
            }
        }

        // Tasks: (tenant_id, created_at DESC) for cursor pagination
        if (Schema::hasTable('tasks')) {
            if (!$this->hasIndex('tasks', 'tasks_tenant_created_at_index')) {
                Schema::table('tasks', function (Blueprint $table) {
                    $table->index(['tenant_id', 'created_at'], 'tasks_tenant_created_at_index');
                });
            }
            
            // (tenant_id, id) for ULID-based cursor pagination
            $indexName = 'tasks_tenant_id_index';
            if (!$this->hasIndex('tasks', $indexName)) {
                try {
                    Schema::table('tasks', function (Blueprint $table) use ($indexName) {
                        $table->index(['tenant_id', 'id'], $indexName);
                    });
                } catch (\Exception $e) {
                    \Log::warning('Failed to add tasks_tenant_id_index: ' . $e->getMessage());
                }
            }
        }

        // Documents: (tenant_id, created_at DESC) for cursor pagination
        if (Schema::hasTable('documents')) {
            if (!$this->hasIndex('documents', 'documents_tenant_created_at_index')) {
                Schema::table('documents', function (Blueprint $table) {
                    $table->index(['tenant_id', 'created_at'], 'documents_tenant_created_at_index');
                });
            }
            
            // (tenant_id, id) for ULID-based cursor pagination
            $indexName = 'documents_tenant_id_index';
            if (!$this->hasIndex('documents', $indexName)) {
                try {
                    Schema::table('documents', function (Blueprint $table) use ($indexName) {
                        $table->index(['tenant_id', 'id'], $indexName);
                    });
                } catch (\Exception $e) {
                    \Log::warning('Failed to add documents_tenant_id_index: ' . $e->getMessage());
                }
            }
        }

        // Templates: (tenant_id, created_at DESC) for cursor pagination
        if (Schema::hasTable('templates')) {
            if (!$this->hasIndex('templates', 'templates_tenant_created_at_index')) {
                Schema::table('templates', function (Blueprint $table) {
                    $table->index(['tenant_id', 'created_at'], 'templates_tenant_created_at_index');
                });
            }
            
            // (tenant_id, id) for ULID-based cursor pagination
            $indexName = 'templates_tenant_id_index';
            if (!$this->hasIndex('templates', $indexName)) {
                try {
                    Schema::table('templates', function (Blueprint $table) use ($indexName) {
                        $table->index(['tenant_id', 'id'], $indexName);
                    });
                } catch (\Exception $e) {
                    \Log::warning('Failed to add templates_tenant_id_index: ' . $e->getMessage());
                }
            }
        }

        // Teams: (tenant_id, created_at DESC) for cursor pagination
        if (Schema::hasTable('teams')) {
            if (!$this->hasIndex('teams', 'teams_tenant_created_at_index')) {
                Schema::table('teams', function (Blueprint $table) {
                    $table->index(['tenant_id', 'created_at'], 'teams_tenant_created_at_index');
                });
            }
            
            // (tenant_id, id) for ULID-based cursor pagination
            $indexName = 'teams_tenant_id_index';
            if (!$this->hasIndex('teams', $indexName)) {
                try {
                    Schema::table('teams', function (Blueprint $table) use ($indexName) {
                        $table->index(['tenant_id', 'id'], $indexName);
                    });
                } catch (\Exception $e) {
                    \Log::warning('Failed to add teams_tenant_id_index: ' . $e->getMessage());
                }
            }
        }

        // Change Requests: (tenant_id, created_at DESC) for cursor pagination
        if (Schema::hasTable('change_requests')) {
            if (!$this->hasIndex('change_requests', 'change_requests_tenant_created_at_index')) {
                Schema::table('change_requests', function (Blueprint $table) {
                    $table->index(['tenant_id', 'created_at'], 'change_requests_tenant_created_at_index');
                });
            }
        }

        // Invitations: (tenant_id, created_at DESC) for cursor pagination
        if (Schema::hasTable('invitations')) {
            if (!$this->hasIndex('invitations', 'invitations_tenant_created_at_index')) {
                Schema::table('invitations', function (Blueprint $table) {
                    $table->index(['tenant_id', 'created_at'], 'invitations_tenant_created_at_index');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (MigrationDriver::isSqlite()) {
            return;
        }
        $tables = [
            'projects' => ['projects_tenant_created_at_index', 'projects_tenant_id_index'],
            'tasks' => ['tasks_tenant_created_at_index', 'tasks_tenant_id_index'],
            'documents' => ['documents_tenant_created_at_index', 'documents_tenant_id_index'],
            'templates' => ['templates_tenant_created_at_index', 'templates_tenant_id_index'],
            'teams' => ['teams_tenant_created_at_index', 'teams_tenant_id_index'],
            'change_requests' => ['change_requests_tenant_created_at_index'],
            'invitations' => ['invitations_tenant_created_at_index'],
        ];

        foreach ($tables as $tableName => $indexes) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($indexes as $indexName) {
                if ($this->hasIndex($tableName, $indexName)) {
                    Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                        $table->dropIndex($indexName);
                    });
                }
            }
        }
    }

    /**
     * Check if table has index
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        if (MigrationDriver::isSqlite()) {
            return false;
        }

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
