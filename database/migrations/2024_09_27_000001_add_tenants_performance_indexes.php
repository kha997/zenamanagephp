<?php

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
        // Use raw SQL to check and add indexes safely
        $this->addIndexIfNotExists('tenants', 'idx_tenants_status_active', 'status, is_active');
        $this->addIndexIfNotExists('tenants', 'idx_tenants_search', 'name, domain, status');
        $this->addIndexIfNotExists('tenants', 'idx_tenants_created_at', 'created_at');
        $this->addUniqueIfNotExists('tenants', 'idx_tenants_domain_unique', 'domain');
        
        $this->addIndexIfNotExists('users', 'idx_users_tenant_status', 'tenant_id, status');
        $this->addIndexIfNotExists('users', 'idx_users_email', 'email');
        
        $this->addIndexIfNotExists('projects', 'idx_projects_tenant_active', 'tenant_id, status');
        $this->addIndexIfNotExists('projects', 'idx_projects_tenant_created', 'tenant_id, created_at');
        
        $this->addIndexIfNotExists('tasks', 'idx_tasks_tenant_status', 'tenant_id, status');
        $this->addIndexIfNotExists('tasks', 'idx_tasks_tenant_project', 'tenant_id, project_id');
    }
    
    private function addIndexIfNotExists($table, $indexName, $columns)
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = '{$indexName}'");
        if (empty($indexes)) {
            DB::statement("ALTER TABLE {$table} ADD INDEX {$indexName} ({$columns})");
        }
    }
    
    private function addUniqueIfNotExists($table, $indexName, $columns)
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = '{$indexName}'");
        if (empty($indexes)) {
            DB::statement("ALTER TABLE {$table} ADD UNIQUE {$indexName} ({$columns})");
        }
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
