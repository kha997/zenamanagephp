<?php declare(strict_types=1);

/**
 * zenamanage Migration Runner
 * 
 * Script này chạy tất cả các migration để tạo database schema
 */

require_once __DIR__ . '/bootstrap.php';

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

echo "🗄️  Running zenamanage Migrations...\n\n";

try {
    $schema = db()->getSchemaBuilder();
    
    // Run RBAC migrations
    echo "Creating RBAC tables...\n";
    runRBACMigrations($schema);
    echo "✅ RBAC tables created\n\n";
    
    // Insert default data
    echo "Inserting default data...\n";
    insertDefaultData();
    echo "✅ Default data inserted\n\n";
    
    echo "🎉 All migrations completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Run RBAC migrations
 * 
 * @param Builder $schema
 */
function runRBACMigrations(Builder $schema): void
{
    // Create roles table
    if (!$schema->hasTable('roles')) {
        $schema->create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('scope', ['system', 'custom', 'project']);
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->unique(['name', 'scope']);
        });
    }
    
    // Create permissions table
    if (!$schema->hasTable('permissions')) {
        $schema->create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('module');
            $table->string('action');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
    
    // Create role_permissions table
    if (!$schema->hasTable('role_permissions')) {
        $schema->create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->boolean('allow_override')->default(false);
            $table->timestamps();
            
            $table->unique(['role_id', 'permission_id']);
        });
    }
    
    // Create users table (simplified)
    if (!$schema->hasTable('users')) {
        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('tenant_id')->nullable();
            $table->timestamps();
        });
    }
    
    // Create user_roles_system table
    if (!$schema->hasTable('user_roles_system')) {
        $schema->create('user_roles_system', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['user_id', 'role_id']);
        });
    }
    
    // Create user_roles_custom table
    if (!$schema->hasTable('user_roles_custom')) {
        $schema->create('user_roles_custom', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->string('tenant_id');
            $table->timestamps();
            
            $table->unique(['user_id', 'role_id', 'tenant_id']);
        });
    }
    
    // Create user_roles_project table
    if (!$schema->hasTable('user_roles_project')) {
        $schema->create('user_roles_project', function (Blueprint $table) {
            $table->id();
            $table->string('project_id');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['project_id', 'user_id', 'role_id']);
        });
    }
}

/**
 * Insert default data
 */
function insertDefaultData(): void
{
    // Insert default roles
    $roles = [
        ['name' => 'system_admin', 'scope' => 'system', 'description' => 'System Administrator'],
        ['name' => 'tenant_admin', 'scope' => 'custom', 'description' => 'Tenant Administrator'],
        ['name' => 'project_manager', 'scope' => 'project', 'description' => 'Project Manager'],
        ['name' => 'team_lead', 'scope' => 'project', 'description' => 'Team Lead'],
        ['name' => 'member', 'scope' => 'project', 'description' => 'Project Member'],
        ['name' => 'viewer', 'scope' => 'project', 'description' => 'Read-only Viewer']
    ];
    
    foreach ($roles as $role) {
        db()->table('roles')->updateOrInsert(
            ['name' => $role['name'], 'scope' => $role['scope']],
            array_merge($role, ['created_at' => now(), 'updated_at' => now()])
        );
    }
    
    // Insert default permissions
    $permissions = [
        // User management
        ['code' => 'user.view', 'module' => 'user', 'action' => 'view'],
        ['code' => 'user.create', 'module' => 'user', 'action' => 'create'],
        ['code' => 'user.update', 'module' => 'user', 'action' => 'update'],
        ['code' => 'user.delete', 'module' => 'user', 'action' => 'delete'],
        
        // Project management
        ['code' => 'project.view', 'module' => 'project', 'action' => 'view'],
        ['code' => 'project.create', 'module' => 'project', 'action' => 'create'],
        ['code' => 'project.update', 'module' => 'project', 'action' => 'update'],
        ['code' => 'project.delete', 'module' => 'project', 'action' => 'delete'],
        
        // Task management
        ['code' => 'task.view', 'module' => 'task', 'action' => 'view'],
        ['code' => 'task.create', 'module' => 'task', 'action' => 'create'],
        ['code' => 'task.update', 'module' => 'task', 'action' => 'update'],
        ['code' => 'task.delete', 'module' => 'task', 'action' => 'delete'],
        
        // RBAC management
        ['code' => 'rbac.role.view', 'module' => 'rbac', 'action' => 'role_view'],
        ['code' => 'rbac.role.create', 'module' => 'rbac', 'action' => 'role_create'],
        ['code' => 'rbac.role.update', 'module' => 'rbac', 'action' => 'role_update'],
        ['code' => 'rbac.role.delete', 'module' => 'rbac', 'action' => 'role_delete'],
        ['code' => 'rbac.permission.view', 'module' => 'rbac', 'action' => 'permission_view'],
        ['code' => 'rbac.assignment.manage', 'module' => 'rbac', 'action' => 'assignment_manage']
    ];
    
    foreach ($permissions as $permission) {
        db()->table('permissions')->updateOrInsert(
            ['code' => $permission['code']],
            array_merge($permission, ['created_at' => now(), 'updated_at' => now()])
        );
    }
}