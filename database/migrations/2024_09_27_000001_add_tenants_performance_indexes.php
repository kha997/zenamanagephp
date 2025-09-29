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
        Schema::table('tenants', function (Blueprint $table) {
            // Status and active index for filtering
            $table->index(['status', 'is_active'], 'idx_tenants_status_active');
            
            // Search index for name, domain, status
            $table->index(['name', 'domain', 'status'], 'idx_tenants_search');
            
            // Created at index for date range filtering
            $table->index('created_at', 'idx_tenants_created_at');
            
            // Domain uniqueness index
            $table->unique('domain', 'idx_tenants_domain_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            // Tenant and status index for user queries
            $table->index(['tenant_id', 'status'], 'idx_users_tenant_status');
            
            // Email index for user lookups
            $table->index('email', 'idx_users_email');
        });

        Schema::table('projects', function (Blueprint $table) {
            // Tenant and active index for project queries
            $table->index(['tenant_id', 'is_active'], 'idx_projects_tenant_active');
            
            // Tenant and created at for sorting
            $table->index(['tenant_id', 'created_at'], 'idx_projects_tenant_created');
        });

        Schema::table('tasks', function (Blueprint $table) {
            // Tenant and status index for task queries
            $table->index(['tenant_id', 'status'], 'idx_tasks_tenant_status');
            
            // Tenant and project for task filtering
            $table->index(['tenant_id', 'project_id'], 'idx_tasks_tenant_project');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex('idx_tenants_status_active');
            $table->dropIndex('idx_tenants_search');
            $table->dropIndex('idx_tenants_created_at');
            $table->dropUnique('idx_tenants_domain_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_tenant_status');
            $table->dropIndex('idx_users_email');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('idx_projects_tenant_active');
            $table->dropIndex('idx_projects_tenant_created');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('idx_tasks_tenant_status');
            $table->dropIndex('idx_tasks_tenant_project');
        });
    }
};
