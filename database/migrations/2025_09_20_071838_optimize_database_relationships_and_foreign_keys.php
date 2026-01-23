<?php declare(strict_types=1);

use App\Traits\SkipsSchemaIntrospection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    use SkipsSchemaIntrospection;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (self::shouldSkipSchemaIntrospection()) {
            return;
        }

        // Optimize foreign key constraints
        $this->optimizeForeignKeys();
        
        // Add missing foreign key constraints
        $this->addMissingForeignKeys();
        
        // Add relationship indexes
        $this->addRelationshipIndexes();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert foreign key optimizations if needed
        $this->revertForeignKeyOptimizations();
    }

    /**
     * Optimize existing foreign key constraints
     */
    private function optimizeForeignKeys(): void
    {
        // Optimize users table foreign keys
        Schema::table('users', function (Blueprint $table) {
            if (!$this->foreignKeyExists('users', 'users_tenant_id_foreign')) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
            }
            
            if (!$this->foreignKeyExists('users', 'users_organization_id_foreign')) {
                $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
            }
        });

        // Optimize projects table foreign keys
        Schema::table('projects', function (Blueprint $table) {
            if (!$this->foreignKeyExists('projects', 'projects_client_id_foreign')) {
                $table->foreign('client_id')->references('id')->on('users')->onDelete('set null');
            }
            
            if (!$this->foreignKeyExists('projects', 'projects_pm_id_foreign')) {
                $table->foreign('pm_id')->references('id')->on('users')->onDelete('set null');
            }
            
            if (!$this->foreignKeyExists('projects', 'projects_tenant_id_foreign')) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            }
        });

        // Optimize tasks table foreign keys
        Schema::table('tasks', function (Blueprint $table) {
            if (!$this->foreignKeyExists('tasks', 'tasks_project_id_foreign')) {
                $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            }
            
            if (!$this->foreignKeyExists('tasks', 'tasks_assignee_id_foreign')) {
                $table->foreign('assignee_id')->references('id')->on('users')->onDelete('set null');
            }
            
            if (!$this->foreignKeyExists('tasks', 'tasks_tenant_id_foreign')) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            }
            
            if (!$this->foreignKeyExists('tasks', 'tasks_component_id_foreign')) {
                $table->foreign('component_id')->references('id')->on('components')->onDelete('set null');
            }
        });
    }

    /**
     * Add missing foreign key constraints
     */
    private function addMissingForeignKeys(): void
    {
        // Add foreign keys for task_assignments table
        Schema::table('task_assignments', function (Blueprint $table) {
            if (!$this->foreignKeyExists('task_assignments', 'task_assignments_task_id_foreign')) {
                $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            }
            
            if (!$this->foreignKeyExists('task_assignments', 'task_assignments_user_id_foreign')) {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            }
            
            if (!$this->foreignKeyExists('task_assignments', 'task_assignments_tenant_id_foreign')) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            }
        });

        // Add foreign keys for change_requests table
        Schema::table('change_requests', function (Blueprint $table) {
            if (!$this->foreignKeyExists('change_requests', 'change_requests_project_id_foreign')) {
                $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            }
            
            if (!$this->foreignKeyExists('change_requests', 'change_requests_requested_by_foreign')) {
                $table->foreign('requested_by')->references('id')->on('users')->onDelete('cascade');
            }
            
            if (!$this->foreignKeyExists('change_requests', 'change_requests_tenant_id_foreign')) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            }
        });
    }

    /**
     * Add relationship indexes
     */
    private function addRelationshipIndexes(): void
    {
        // Add indexes for common relationship queries
        $this->addIndexIfNotExists('users', 'tenant_id', 'users_tenant_id_index');
        $this->addIndexIfNotExists('users', 'organization_id', 'users_organization_id_index');
        
        $this->addIndexIfNotExists('projects', 'client_id', 'projects_client_id_index');
        $this->addIndexIfNotExists('projects', 'pm_id', 'projects_pm_id_index');
        $this->addIndexIfNotExists('projects', 'tenant_id', 'projects_tenant_id_index');
        
        $this->addIndexIfNotExists('tasks', 'project_id', 'tasks_project_id_index');
        $this->addIndexIfNotExists('tasks', 'assignee_id', 'tasks_assignee_id_index');
        $this->addIndexIfNotExists('tasks', 'tenant_id', 'tasks_tenant_id_index');
        $this->addIndexIfNotExists('tasks', 'component_id', 'tasks_component_id_index');
        
        $this->addIndexIfNotExists('task_assignments', 'task_id', 'task_assignments_task_id_index');
        $this->addIndexIfNotExists('task_assignments', 'user_id', 'task_assignments_user_id_index');
        $this->addIndexIfNotExists('task_assignments', 'tenant_id', 'task_assignments_tenant_id_index');
        
        $this->addIndexIfNotExists('change_requests', 'project_id', 'change_requests_project_id_index');
        $this->addIndexIfNotExists('change_requests', 'requested_by', 'change_requests_requested_by_index');
        $this->addIndexIfNotExists('change_requests', 'tenant_id', 'change_requests_tenant_id_index');
    }

    /**
     * Revert foreign key optimizations
     */
    private function revertForeignKeyOptimizations(): void
    {
        // This method can be used to revert specific optimizations if needed
        // For now, we'll leave it empty as most optimizations are additive
    }

    /**
     * Check if foreign key exists
     */
    private function foreignKeyExists(string $table, string $constraint): bool
    {
        try {
            $result = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = ? 
                AND CONSTRAINT_NAME = ?
            ", [$table, $constraint]);
            
            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Add index if it doesn't exist
     */
    private function addIndexIfNotExists(string $table, string $column, string $indexName): void
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM {$table}");
            foreach ($indexes as $idx) {
                if ($idx->Key_name === $indexName) {
                    return; // Index already exists
                }
            }
            
            // Index doesn't exist, add it
            Schema::table($table, function (Blueprint $table) use ($column, $indexName) {
                $table->index($column, $indexName);
            });
            
        } catch (\Exception $e) {
            // Log error but continue
            \Log::warning("Failed to add index {$indexName} to table {$table}: " . $e->getMessage());
        }
    }
};
