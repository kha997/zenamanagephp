<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only add columns to tables that actually need them
        $tablesToUpdate = [
            'documents' => ['created_by', 'updated_by'],
            'components' => ['updated_by'], // already has created_by
            'projects' => ['updated_by'], // already has created_by
            'rfis' => ['created_by', 'updated_by'],
            'ncrs' => ['created_by', 'updated_by'],
            'change_requests' => ['created_by', 'updated_by'],
            'qc_plans' => ['created_by', 'updated_by'],
            'qc_inspections' => ['created_by', 'updated_by'],
            'teams' => ['created_by', 'updated_by'],
            'notifications' => ['created_by', 'updated_by'],
            'project_milestones' => ['updated_by'], // already has created_by
            'project_activities' => ['created_by', 'updated_by'],
            'task_assignments' => ['updated_by'], // already has created_by
            'audit_logs' => ['created_by', 'updated_by'],
            'document_versions' => ['created_by', 'updated_by'],
            'dashboard_widgets' => ['created_by', 'updated_by'],
            'user_dashboards' => ['created_by', 'updated_by'],
            'search_histories' => ['created_by', 'updated_by'],
            'report_schedules' => ['created_by', 'updated_by'],
            'onboarding_steps' => ['created_by', 'updated_by'],
            'security_alerts' => ['created_by', 'updated_by'],
            'system_alerts' => ['created_by', 'updated_by'],
            'billing_invoices' => ['created_by', 'updated_by'],
            'billing_payments' => ['created_by', 'updated_by'],
            'tenant_subscriptions' => ['created_by', 'updated_by'],
            'user_preferences' => ['created_by', 'updated_by'],
            'invitations' => ['created_by', 'updated_by'],
            'email_tracking' => ['created_by', 'updated_by'],
            'login_attempts' => ['created_by', 'updated_by'],
            'backup_logs' => ['created_by', 'updated_by'],
            'query_logs' => ['created_by', 'updated_by'],
            'cache_entries' => ['created_by', 'updated_by'],
            'data_retention_policies' => ['created_by', 'updated_by'],
            'system_settings' => ['created_by', 'updated_by'],
            'billing_plans' => ['created_by', 'updated_by'],
            'security_rules' => ['created_by', 'updated_by'],
            'calendar_integrations' => ['created_by', 'updated_by'],
            'change_request_comments' => ['created_by', 'updated_by'],
            'change_request_approvals' => ['created_by', 'updated_by'],
            'project_team_members' => ['created_by', 'updated_by'],
            'project_teams' => ['created_by', 'updated_by'],
            'task_dependencies' => ['created_by', 'updated_by'],
            'task_watchers' => ['created_by', 'updated_by'],
            'team_members' => ['created_by', 'updated_by'],
            'organizations' => ['created_by', 'updated_by'],
            'jobs' => ['created_by', 'updated_by'],
            'user_sessions' => ['created_by', 'updated_by'],
            'user_roles' => ['created_by', 'updated_by'],
            'role_permissions' => ['created_by', 'updated_by'],
            'permissions' => ['created_by', 'updated_by'],
            'roles' => ['created_by', 'updated_by'],
            'clients' => ['created_by', 'updated_by'],
            'quotes' => ['created_by', 'updated_by']
        ];

        foreach ($tablesToUpdate as $tableName => $columns) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns) {
                    foreach ($columns as $column) {
                        // Add column if it doesn't exist
                        if (!Schema::hasColumn($tableName, $column)) {
                            $table->ulid($column)->nullable();
                        }
                    }
                });

                // Add indexes for performance (skip foreign keys for now)
                Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns) {
                    foreach ($columns as $column) {
                        $indexName = $tableName . '_' . $column . '_index';
                        if (!$this->indexExists($tableName, $indexName)) {
                            $table->index($column);
                        }
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tablesToUpdate = [
            'documents' => ['created_by', 'updated_by'],
            'components' => ['updated_by'],
            'projects' => ['updated_by'],
            'rfis' => ['created_by', 'updated_by'],
            'ncrs' => ['created_by', 'updated_by'],
            'change_requests' => ['created_by', 'updated_by'],
            'qc_plans' => ['created_by', 'updated_by'],
            'qc_inspections' => ['created_by', 'updated_by'],
            'teams' => ['created_by', 'updated_by'],
            'notifications' => ['created_by', 'updated_by'],
            'project_milestones' => ['updated_by'],
            'project_activities' => ['created_by', 'updated_by'],
            'task_assignments' => ['updated_by'],
            'audit_logs' => ['created_by', 'updated_by'],
            'document_versions' => ['created_by', 'updated_by'],
            'dashboard_widgets' => ['created_by', 'updated_by'],
            'user_dashboards' => ['created_by', 'updated_by'],
            'search_histories' => ['created_by', 'updated_by'],
            'report_schedules' => ['created_by', 'updated_by'],
            'onboarding_steps' => ['created_by', 'updated_by'],
            'security_alerts' => ['created_by', 'updated_by'],
            'system_alerts' => ['created_by', 'updated_by'],
            'billing_invoices' => ['created_by', 'updated_by'],
            'billing_payments' => ['created_by', 'updated_by'],
            'tenant_subscriptions' => ['created_by', 'updated_by'],
            'user_preferences' => ['created_by', 'updated_by'],
            'invitations' => ['created_by', 'updated_by'],
            'email_tracking' => ['created_by', 'updated_by'],
            'login_attempts' => ['created_by', 'updated_by'],
            'backup_logs' => ['created_by', 'updated_by'],
            'query_logs' => ['created_by', 'updated_by'],
            'cache_entries' => ['created_by', 'updated_by'],
            'data_retention_policies' => ['created_by', 'updated_by'],
            'system_settings' => ['created_by', 'updated_by'],
            'billing_plans' => ['created_by', 'updated_by'],
            'security_rules' => ['created_by', 'updated_by'],
            'calendar_integrations' => ['created_by', 'updated_by'],
            'change_request_comments' => ['created_by', 'updated_by'],
            'change_request_approvals' => ['created_by', 'updated_by'],
            'project_team_members' => ['created_by', 'updated_by'],
            'project_teams' => ['created_by', 'updated_by'],
            'task_dependencies' => ['created_by', 'updated_by'],
            'task_watchers' => ['created_by', 'updated_by'],
            'team_members' => ['created_by', 'updated_by'],
            'organizations' => ['created_by', 'updated_by'],
            'jobs' => ['created_by', 'updated_by'],
            'user_sessions' => ['created_by', 'updated_by'],
            'user_roles' => ['created_by', 'updated_by'],
            'role_permissions' => ['created_by', 'updated_by'],
            'permissions' => ['created_by', 'updated_by'],
            'roles' => ['created_by', 'updated_by'],
            'clients' => ['created_by', 'updated_by'],
            'quotes' => ['created_by', 'updated_by']
        ];

        foreach ($tablesToUpdate as $tableName => $columns) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns) {
                    foreach ($columns as $column) {
                        // Drop foreign keys first
                        $constraintName = $tableName . '_' . $column . '_foreign';
                        try {
                            $table->dropForeign([$constraintName]);
                        } catch (\Exception $e) {
                            // Foreign key might not exist
                        }
                        
                        // Drop indexes
                        $indexName = $tableName . '_' . $column . '_index';
                        try {
                            $table->dropIndex([$indexName]);
                        } catch (\Exception $e) {
                            // Index might not exist
                        }
                        
                        // Drop columns
                        if (Schema::hasColumn($tableName, $column)) {
                            $table->dropColumn($column);
                        }
                    }
                });
            }
        }
    }


    /**
     * Check if a foreign key constraint exists
     */
    private function foreignKeyExists(string $table, string $constraint): bool
    {
        try {
            $foreignKeys = \DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = ? 
                AND TABLE_NAME = ? 
                AND CONSTRAINT_NAME = ?
            ", [config('database.connections.mysql.database'), $table, $constraint]);

            return count($foreignKeys) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if an index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $indexes = \DB::select("
                SELECT INDEX_NAME 
                FROM information_schema.STATISTICS 
                WHERE TABLE_SCHEMA = ? 
                AND TABLE_NAME = ? 
                AND INDEX_NAME = ?
            ", [config('database.connections.mysql.database'), $table, $index]);

            return count($indexes) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
};