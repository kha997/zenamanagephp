<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add only missing indexes that don't exist yet
        $this->addIndexIfNotExists('users', 'email', 'users_email_index');
        $this->addIndexIfNotExists('users', ['status', 'tenant_id'], 'users_status_tenant_id_index');
        $this->addIndexIfNotExists('users', ['role', 'status'], 'users_role_status_index');
        
        $this->addIndexIfNotExists('projects', ['tenant_id', 'status'], 'projects_tenant_id_status_index');
        $this->addIndexIfNotExists('projects', ['client_id', 'status'], 'projects_client_id_status_index');
        $this->addIndexIfNotExists('projects', ['pm_id', 'status'], 'projects_pm_id_status_index');
        $this->addIndexIfNotExists('projects', ['start_date', 'end_date'], 'projects_date_range_index');
        $this->addIndexIfNotExists('projects', ['progress', 'status'], 'projects_progress_status_index');
        
        $this->addIndexIfNotExists('tasks', ['project_id', 'status', 'priority'], 'tasks_project_status_priority_index');
        $this->addIndexIfNotExists('tasks', ['assignee_id', 'status'], 'tasks_assignee_status_index');
        $this->addIndexIfNotExists('tasks', ['tenant_id', 'status'], 'tasks_tenant_status_index');
        $this->addIndexIfNotExists('tasks', ['start_date', 'end_date'], 'tasks_date_range_index');
        $this->addIndexIfNotExists('tasks', ['progress_percent', 'status'], 'tasks_progress_status_index');
        $this->addIndexIfNotExists('tasks', ['estimated_hours', 'actual_hours'], 'tasks_hours_index');
        
        $this->addIndexIfNotExists('documents', ['project_id', 'status'], 'documents_project_status_index');
        $this->addIndexIfNotExists('documents', ['uploaded_by', 'status'], 'documents_uploader_status_index');
        $this->addIndexIfNotExists('documents', ['file_type', 'status'], 'documents_type_status_index');
        $this->addIndexIfNotExists('documents', ['file_hash', 'status'], 'documents_hash_status_index');
        
        $this->addIndexIfNotExists('task_assignments', ['task_id', 'user_id'], 'task_assignments_task_user_index');
        $this->addIndexIfNotExists('task_assignments', ['user_id', 'status'], 'task_assignments_user_status_index');
        $this->addIndexIfNotExists('task_assignments', ['tenant_id', 'status'], 'task_assignments_tenant_status_index');
        
        $this->addIndexIfNotExists('change_requests', ['project_id', 'status'], 'change_requests_project_status_index');
        $this->addIndexIfNotExists('change_requests', ['requested_by', 'status'], 'change_requests_requester_status_index');
        $this->addIndexIfNotExists('change_requests', ['priority', 'status'], 'change_requests_priority_status_index');
        
        $this->addIndexIfNotExists('teams', ['tenant_id', 'is_active'], 'teams_tenant_active_index');
        $this->addIndexIfNotExists('teams', ['team_lead_id', 'is_active'], 'teams_lead_active_index');
        
        $this->addIndexIfNotExists('components', ['project_id', 'status'], 'components_project_status_index');
        $this->addIndexIfNotExists('components', ['type', 'status'], 'components_type_status_index');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop all indexes we created
        $this->dropIndexIfExists('users', 'users_email_index');
        $this->dropIndexIfExists('users', 'users_status_tenant_id_index');
        $this->dropIndexIfExists('users', 'users_role_status_index');
        
        $this->dropIndexIfExists('projects', 'projects_tenant_id_status_index');
        $this->dropIndexIfExists('projects', 'projects_client_id_status_index');
        $this->dropIndexIfExists('projects', 'projects_pm_id_status_index');
        $this->dropIndexIfExists('projects', 'projects_date_range_index');
        $this->dropIndexIfExists('projects', 'projects_progress_status_index');
        
        $this->dropIndexIfExists('tasks', 'tasks_project_status_priority_index');
        $this->dropIndexIfExists('tasks', 'tasks_assignee_status_index');
        $this->dropIndexIfExists('tasks', 'tasks_tenant_status_index');
        $this->dropIndexIfExists('tasks', 'tasks_date_range_index');
        $this->dropIndexIfExists('tasks', 'tasks_progress_status_index');
        $this->dropIndexIfExists('tasks', 'tasks_hours_index');
        
        $this->dropIndexIfExists('documents', 'documents_project_status_index');
        $this->dropIndexIfExists('documents', 'documents_uploader_status_index');
        $this->dropIndexIfExists('documents', 'documents_type_status_index');
        $this->dropIndexIfExists('documents', 'documents_hash_status_index');
        
        $this->dropIndexIfExists('task_assignments', 'task_assignments_task_user_index');
        $this->dropIndexIfExists('task_assignments', 'task_assignments_user_status_index');
        $this->dropIndexIfExists('task_assignments', 'task_assignments_tenant_status_index');
        
        $this->dropIndexIfExists('change_requests', 'change_requests_project_status_index');
        $this->dropIndexIfExists('change_requests', 'change_requests_requester_status_index');
        $this->dropIndexIfExists('change_requests', 'change_requests_priority_status_index');
        
        $this->dropIndexIfExists('teams', 'teams_tenant_active_index');
        $this->dropIndexIfExists('teams', 'teams_lead_active_index');
        
        $this->dropIndexIfExists('components', 'components_project_status_index');
        $this->dropIndexIfExists('components', 'components_type_status_index');
    }

    /**
     * Add index if it doesn't exist
     */
    private function addIndexIfNotExists(string $table, $columns, string $indexName): void
    {
        if (!$this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
                $table->index($columns, $indexName);
            });
        }
    }

    /**
     * Drop index if it exists
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        }
    }

    /**
     * Check if index exists on table
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $driver = DB::getDriverName();
            
            if ($driver === 'sqlite') {
                // SQLite syntax
                $indexes = DB::select("PRAGMA index_list({$table})");
                foreach ($indexes as $idx) {
                    if ($idx->name === $index) {
                        return true;
                    }
                }
            } else {
                // MySQL syntax
                $indexes = DB::select("SHOW INDEX FROM {$table}");
                foreach ($indexes as $idx) {
                    if ($idx->Key_name === $index) {
                        return true;
                    }
                }
            }
            return false;
        } catch (\Exception $e) {
            // If table doesn't exist or other error, assume index doesn't exist
            return false;
        }
    }
};