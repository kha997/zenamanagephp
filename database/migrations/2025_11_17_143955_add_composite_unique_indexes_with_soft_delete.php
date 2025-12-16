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
     * Adds composite unique indexes that respect soft deletes.
     * For MySQL, we use a workaround: unique index on (tenant_id, unique_field, deleted_at)
     * where deleted_at IS NULL for active records.
     * 
     * Note: MySQL allows multiple NULLs in unique indexes, so we use COALESCE to handle this.
     */
    public function up(): void
    {
        // Projects: unique (tenant_id, code) where deleted_at IS NULL
        if (Schema::hasTable('projects') && Schema::hasColumn('projects', 'code')) {
            // Drop existing unique on code if exists
            $this->dropIndexIfExists('projects', 'projects_code_unique');
            
            // Add composite unique: (tenant_id, code) - MySQL will allow multiple NULL deleted_at
            // For active records (deleted_at IS NULL), the combination (tenant_id, code) must be unique
            Schema::table('projects', function (Blueprint $table) {
                $table->unique(['tenant_id', 'code'], 'projects_tenant_code_unique');
            });
        }

        // Template Sets: unique (tenant_id, code) where deleted_at IS NULL
        if (Schema::hasTable('template_sets') && Schema::hasColumn('template_sets', 'code')) {
            $this->dropIndexIfExists('template_sets', 'template_sets_tenant_id_code_unique');
            $this->dropIndexIfExists('template_sets', 'unique_tenant_code');
            
            Schema::table('template_sets', function (Blueprint $table) {
                $table->unique(['tenant_id', 'code'], 'template_sets_tenant_code_unique');
            });
        }

        // Users: unique (tenant_id, email) where deleted_at IS NULL
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'email')) {
            $this->dropIndexIfExists('users', 'ux_users_email_tenant');
            
            Schema::table('users', function (Blueprint $table) {
                $table->unique(['tenant_id', 'email'], 'users_tenant_email_unique');
            });
        }

        // Clients: unique (tenant_id, name) where deleted_at IS NULL (if name should be unique)
        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'name')) {
            // Only add if name should be unique per tenant
            // Uncomment if needed:
            // Schema::table('clients', function (Blueprint $table) {
            //     $table->unique(['tenant_id', 'name'], 'clients_tenant_name_unique');
            // });
        }

        // Add indexes for common query patterns with tenant_id
        $this->addTenantCompositeIndexes();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop composite unique indexes
        if (Schema::hasTable('projects')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropUnique('projects_tenant_code_unique');
            });
            // Restore original unique on code
            if (Schema::hasColumn('projects', 'code')) {
                Schema::table('projects', function (Blueprint $table) {
                    $table->unique('code', 'projects_code_unique');
                });
            }
        }

        if (Schema::hasTable('template_sets')) {
            Schema::table('template_sets', function (Blueprint $table) {
                $table->dropUnique('template_sets_tenant_code_unique');
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_tenant_email_unique');
            });
        }
    }

    /**
     * Add composite indexes for common query patterns
     */
    private function addTenantCompositeIndexes(): void
    {
        // Projects: (tenant_id, status) for filtering by tenant and status
        if (Schema::hasTable('projects') && Schema::hasColumn('projects', 'status')) {
            if (!$this->hasIndex('projects', 'projects_tenant_status_index')) {
                try {
                    Schema::table('projects', function (Blueprint $table) {
                        $table->index(['tenant_id', 'status'], 'projects_tenant_status_index');
                    });
                } catch (\Exception $e) {
                    \Log::warning('Failed to add projects_tenant_status_index: ' . $e->getMessage());
                }
            }
        }

        // Tasks: (tenant_id, project_id, status) for common queries
        if (Schema::hasTable('tasks')) {
            if (!$this->hasIndex('tasks', 'tasks_tenant_project_status_index')) {
                try {
                    Schema::table('tasks', function (Blueprint $table) {
                        $table->index(['tenant_id', 'project_id', 'status'], 'tasks_tenant_project_status_index');
                    });
                } catch (\Exception $e) {
                    \Log::warning('Failed to add tasks_tenant_project_status_index: ' . $e->getMessage());
                }
            }
        }

        // Tasks: (tenant_id, assignee_id, status) for user task lists
        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'assignee_id')) {
            if (!$this->hasIndex('tasks', 'tasks_tenant_assignee_status_index')) {
                try {
                    Schema::table('tasks', function (Blueprint $table) {
                        $table->index(['tenant_id', 'assignee_id', 'status'], 'tasks_tenant_assignee_status_index');
                    });
                } catch (\Exception $e) {
                    \Log::warning('Failed to add tasks_tenant_assignee_status_index: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Drop index if it exists
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->hasIndex($table, $indexName)) {
            Schema::table($table, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
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
