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
        // Add performance indexes for common query patterns
        // Skip if indexes already exist to avoid duplicate key errors
        
        $this->addIndexIfNotExists('project_activities', ['action', 'created_at'], 'idx_project_activities_action_created');
        $this->addIndexIfNotExists('project_activities', ['entity_type', 'created_at'], 'idx_project_activities_entity_type_created');
        
        $this->addIndexIfNotExists('notifications', ['tenant_id', 'user_id', 'read_at'], 'idx_notifications_tenant_user_read');
        $this->addIndexIfNotExists('notifications', ['user_id', 'created_at'], 'idx_notifications_user_created');
        $this->addIndexIfNotExists('notifications', ['type', 'created_at'], 'idx_notifications_type_created');
        
        $this->addIndexIfNotExists('projects', ['tenant_id', 'budget_total'], 'idx_projects_tenant_budget');
        $this->addIndexIfNotExists('projects', ['status', 'created_at'], 'idx_projects_status_created');
        $this->addIndexIfNotExists('projects', ['created_by', 'created_at'], 'idx_projects_creator_created');
        
        $this->addIndexIfNotExists('tasks', ['assigned_to', 'status', 'updated_at'], 'idx_tasks_assignee_status_updated');
        $this->addIndexIfNotExists('tasks', ['project_id', 'status'], 'idx_tasks_project_status');
        $this->addIndexIfNotExists('tasks', ['status', 'updated_at'], 'idx_tasks_status_updated');
        $this->addIndexIfNotExists('tasks', ['priority', 'created_at'], 'idx_tasks_priority_created');
        
        $this->addIndexIfNotExists('users', ['tenant_id', 'is_active'], 'idx_users_tenant_active');
        $this->addIndexIfNotExists('users', ['role', 'is_active'], 'idx_users_role_active');
        $this->addIndexIfNotExists('users', ['last_login_at'], 'idx_users_last_login');
        
        $this->addIndexIfNotExists('documents', ['uploaded_by', 'created_at'], 'idx_documents_uploader_created');
        $this->addIndexIfNotExists('documents', ['project_id', 'created_at'], 'idx_documents_project_created');
        $this->addIndexIfNotExists('documents', ['category', 'created_at'], 'idx_documents_category_created');
        $this->addIndexIfNotExists('documents', ['status', 'created_at'], 'idx_documents_status_created');
        
        $this->addIndexIfNotExists('document_versions', ['document_id', 'version_number'], 'idx_document_versions_doc_version');
        $this->addIndexIfNotExists('document_versions', ['created_by', 'created_at'], 'idx_document_versions_creator_created');
    }
    
    /**
     * Add index if it doesn't already exist
     */
    private function addIndexIfNotExists(string $table, array $columns, string $indexName): void
    {
        try {
            Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
                $table->index($columns, $indexName);
            });
        } catch (\Exception $e) {
            // Index might already exist, continue
            if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                throw $e;
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
        // Drop performance indexes
        
        Schema::table('project_activities', function (Blueprint $table) {
            $table->dropIndex('idx_project_activities_action_created');
            $table->dropIndex('idx_project_activities_entity_type_created');
        });
        
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_tenant_user_read');
            $table->dropIndex('idx_notifications_user_created');
            $table->dropIndex('idx_notifications_type_created');
        });
        
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('idx_projects_tenant_budget');
            $table->dropIndex('idx_projects_status_created');
            $table->dropIndex('idx_projects_creator_created');
        });
        
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('idx_tasks_assignee_status_updated');
            $table->dropIndex('idx_tasks_project_status');
            $table->dropIndex('idx_tasks_status_updated');
            $table->dropIndex('idx_tasks_priority_created');
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_tenant_active');
            $table->dropIndex('idx_users_role_active');
            $table->dropIndex('idx_users_last_login');
        });
        
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('idx_documents_uploader_created');
            $table->dropIndex('idx_documents_project_created');
            $table->dropIndex('idx_documents_category_created');
            $table->dropIndex('idx_documents_status_created');
        });
        
        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropIndex('idx_document_versions_doc_version');
            $table->dropIndex('idx_document_versions_creator_created');
        });
    }
};
