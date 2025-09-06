<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration để thêm các indexes quan trọng cho performance optimization
 * Tập trung vào các truy vấn thường xuyên và joins phức tạp
 */
class AddPerformanceIndexes extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Projects table indexes
        Schema::table('projects', function (Blueprint $table) {
            $table->index(['tenant_id', 'status'], 'idx_projects_tenant_status');
            $table->index(['start_date', 'end_date'], 'idx_projects_date_range');
            $table->index('progress', 'idx_projects_progress');
        });

        // Tasks table indexes
        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['project_id', 'status'], 'idx_tasks_project_status');
            $table->index(['start_date', 'end_date'], 'idx_tasks_date_range');
            $table->index(['component_id', 'is_hidden'], 'idx_tasks_component_visibility');
            $table->index('conditional_tag', 'idx_tasks_conditional_tag');
        });

        // Components table indexes
        Schema::table('components', function (Blueprint $table) {
            $table->index(['project_id', 'parent_component_id'], 'idx_components_hierarchy');
            $table->index('progress_percent', 'idx_components_progress');
        });

        // Interaction logs table indexes
        Schema::table('interaction_logs', function (Blueprint $table) {
            $table->index(['project_id', 'type'], 'idx_interaction_logs_project_type');
            $table->index(['created_by', 'created_at'], 'idx_interaction_logs_creator_date');
            $table->index(['visibility', 'client_approved'], 'idx_interaction_logs_visibility');
            $table->index('tag_path', 'idx_interaction_logs_tag_path');
        });

        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            $table->index(['tenant_id', 'email'], 'idx_users_tenant_email');
        });

        // Notifications table indexes
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'read_at'], 'idx_notifications_user_read');
            $table->index(['priority', 'created_at'], 'idx_notifications_priority_date');
        });

        // Change requests table indexes
        Schema::table('change_requests', function (Blueprint $table) {
            $table->index(['project_id', 'status'], 'idx_change_requests_project_status');
            $table->index(['created_by', 'created_at'], 'idx_change_requests_creator_date');
        });

        // Document versions table indexes
        Schema::table('document_versions', function (Blueprint $table) {
            $table->index(['document_id', 'version_number'], 'idx_document_versions_doc_version');
            $table->index('created_by', 'idx_document_versions_creator');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('idx_projects_tenant_status');
            $table->dropIndex('idx_projects_date_range');
            $table->dropIndex('idx_projects_progress');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('idx_tasks_project_status');
            $table->dropIndex('idx_tasks_date_range');
            $table->dropIndex('idx_tasks_component_visibility');
            $table->dropIndex('idx_tasks_conditional_tag');
        });

        Schema::table('components', function (Blueprint $table) {
            $table->dropIndex('idx_components_hierarchy');
            $table->dropIndex('idx_components_progress');
        });

        Schema::table('interaction_logs', function (Blueprint $table) {
            $table->dropIndex('idx_interaction_logs_project_type');
            $table->dropIndex('idx_interaction_logs_creator_date');
            $table->dropIndex('idx_interaction_logs_visibility');
            $table->dropIndex('idx_interaction_logs_tag_path');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_tenant_email');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_user_read');
            $table->dropIndex('idx_notifications_priority_date');
        });

        Schema::table('change_requests', function (Blueprint $table) {
            $table->dropIndex('idx_change_requests_project_status');
            $table->dropIndex('idx_change_requests_creator_date');
        });

        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropIndex('idx_document_versions_doc_version');
            $table->dropIndex('idx_document_versions_creator');
        });
    }
}