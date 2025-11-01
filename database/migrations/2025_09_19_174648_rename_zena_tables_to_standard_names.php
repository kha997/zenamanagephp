<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Rename zena_ prefixed tables to standard Laravel naming
        $tableMappings = [
            'zena_users' => 'users',
            'zena_components' => 'components',
            'zena_task_assignments' => 'task_assignments',
            'zena_documents' => 'documents',
            'zena_notifications' => 'notifications',
            'zena_roles' => 'roles',
            'zena_permissions' => 'permissions',
            'zena_role_permissions' => 'role_permissions',
            'zena_user_roles' => 'user_roles',
            'zena_audit_logs' => 'audit_logs',
            'zena_email_tracking' => 'email_tracking',
            'zena_system_settings' => 'system_settings',
            'zena_work_templates' => 'work_templates',
            'zena_template_tasks' => 'template_tasks',
            'zena_design_construction' => 'design_construction',
            'zena_change_requests' => 'change_requests',
            'zena_change_request_comments' => 'change_request_comments',
            'zena_change_request_approvals' => 'change_request_approvals',
        ];

        foreach ($tableMappings as $oldTable => $newTable) {
            if (Schema::hasTable($oldTable) && !Schema::hasTable($newTable)) {
                Schema::rename($oldTable, $newTable);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Rollback: rename standard tables back to zena_ prefixed
        $tableMappings = [
            'users' => 'zena_users',
            'components' => 'zena_components',
            'task_assignments' => 'zena_task_assignments',
            'documents' => 'zena_documents',
            'notifications' => 'zena_notifications',
            'roles' => 'zena_roles',
            'permissions' => 'zena_permissions',
            'role_permissions' => 'zena_role_permissions',
            'user_roles' => 'zena_user_roles',
            'audit_logs' => 'zena_audit_logs',
            'email_tracking' => 'zena_email_tracking',
            'system_settings' => 'zena_system_settings',
            'work_templates' => 'zena_work_templates',
            'template_tasks' => 'zena_template_tasks',
            'design_construction' => 'zena_design_construction',
            'change_requests' => 'zena_change_requests',
            'change_request_comments' => 'zena_change_request_comments',
            'change_request_approvals' => 'zena_change_request_approvals',
        ];

        foreach ($tableMappings as $oldTable => $newTable) {
            if (Schema::hasTable($oldTable)) {
                Schema::rename($oldTable, $newTable);
            }
        }
    }
};
