<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'id' => 'role_super_admin',
                'name' => 'Super Admin',
                'slug' => 'super_admin',
                'description' => 'Full system access with all permissions',
                'permissions' => [
                    'projects.create', 'projects.read', 'projects.update', 'projects.delete',
                    'tasks.create', 'tasks.read', 'tasks.update', 'tasks.delete',
                    'documents.create', 'documents.read', 'documents.update', 'documents.delete',
                    'users.create', 'users.read', 'users.update', 'users.delete',
                    'teams.create', 'teams.read', 'teams.update', 'teams.delete',
                    'settings.read', 'settings.update',
                    'reports.read', 'reports.create',
                    'admin.access'
                ],
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => 'role_admin',
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Administrative access with most permissions',
                'permissions' => [
                    'projects.create', 'projects.read', 'projects.update',
                    'tasks.create', 'tasks.read', 'tasks.update', 'tasks.delete',
                    'documents.create', 'documents.read', 'documents.update', 'documents.delete',
                    'users.create', 'users.read', 'users.update',
                    'teams.create', 'teams.read', 'teams.update',
                    'settings.read', 'settings.update',
                    'reports.read', 'reports.create'
                ],
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => 'role_project_manager',
                'name' => 'Project Manager',
                'slug' => 'project_manager',
                'description' => 'Project management access with task and document permissions',
                'permissions' => [
                    'projects.read', 'projects.update',
                    'tasks.create', 'tasks.read', 'tasks.update', 'tasks.delete',
                    'documents.create', 'documents.read', 'documents.update', 'documents.delete',
                    'teams.read', 'teams.update',
                    'reports.read'
                ],
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => 'role_member',
                'name' => 'Member',
                'slug' => 'member',
                'description' => 'Basic member access with limited permissions',
                'permissions' => [
                    'projects.read',
                    'tasks.read', 'tasks.update',
                    'documents.read', 'documents.create',
                    'teams.read'
                ],
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => 'role_client',
                'name' => 'Client',
                'slug' => 'client',
                'description' => 'Client access with read-only permissions',
                'permissions' => [
                    'projects.read',
                    'documents.read'
                ],
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        // Insert roles
        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['id' => $role['id']],
                $role
            );
        }

        // Create permissions table if it doesn't exist
        if (!DB::getSchemaBuilder()->hasTable('permissions')) {
            DB::statement('
                CREATE TABLE permissions (
                    id VARCHAR(255) PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    slug VARCHAR(255) UNIQUE NOT NULL,
                    description TEXT,
                    module VARCHAR(100),
                    action VARCHAR(100),
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP
                )
            ');
        }

        // Define all permissions
        $permissions = [
            // Project permissions
            ['id' => 'perm_projects_create', 'name' => 'Create Projects', 'slug' => 'projects.create', 'module' => 'projects', 'action' => 'create'],
            ['id' => 'perm_projects_read', 'name' => 'Read Projects', 'slug' => 'projects.read', 'module' => 'projects', 'action' => 'read'],
            ['id' => 'perm_projects_update', 'name' => 'Update Projects', 'slug' => 'projects.update', 'module' => 'projects', 'action' => 'update'],
            ['id' => 'perm_projects_delete', 'name' => 'Delete Projects', 'slug' => 'projects.delete', 'module' => 'projects', 'action' => 'delete'],
            
            // Task permissions
            ['id' => 'perm_tasks_create', 'name' => 'Create Tasks', 'slug' => 'tasks.create', 'module' => 'tasks', 'action' => 'create'],
            ['id' => 'perm_tasks_read', 'name' => 'Read Tasks', 'slug' => 'tasks.read', 'module' => 'tasks', 'action' => 'read'],
            ['id' => 'perm_tasks_update', 'name' => 'Update Tasks', 'slug' => 'tasks.update', 'module' => 'tasks', 'action' => 'update'],
            ['id' => 'perm_tasks_delete', 'name' => 'Delete Tasks', 'slug' => 'tasks.delete', 'module' => 'tasks', 'action' => 'delete'],
            
            // Document permissions
            ['id' => 'perm_documents_create', 'name' => 'Create Documents', 'slug' => 'documents.create', 'module' => 'documents', 'action' => 'create'],
            ['id' => 'perm_documents_read', 'name' => 'Read Documents', 'slug' => 'documents.read', 'module' => 'documents', 'action' => 'read'],
            ['id' => 'perm_documents_update', 'name' => 'Update Documents', 'slug' => 'documents.update', 'module' => 'documents', 'action' => 'update'],
            ['id' => 'perm_documents_delete', 'name' => 'Delete Documents', 'slug' => 'documents.delete', 'module' => 'documents', 'action' => 'delete'],
            
            // User permissions
            ['id' => 'perm_users_create', 'name' => 'Create Users', 'slug' => 'users.create', 'module' => 'users', 'action' => 'create'],
            ['id' => 'perm_users_read', 'name' => 'Read Users', 'slug' => 'users.read', 'module' => 'users', 'action' => 'read'],
            ['id' => 'perm_users_update', 'name' => 'Update Users', 'slug' => 'users.update', 'module' => 'users', 'action' => 'update'],
            ['id' => 'perm_users_delete', 'name' => 'Delete Users', 'slug' => 'users.delete', 'module' => 'users', 'action' => 'delete'],
            
            // Team permissions
            ['id' => 'perm_teams_create', 'name' => 'Create Teams', 'slug' => 'teams.create', 'module' => 'teams', 'action' => 'create'],
            ['id' => 'perm_teams_read', 'name' => 'Read Teams', 'slug' => 'teams.read', 'module' => 'teams', 'action' => 'read'],
            ['id' => 'perm_teams_update', 'name' => 'Update Teams', 'slug' => 'teams.update', 'module' => 'teams', 'action' => 'update'],
            ['id' => 'perm_teams_delete', 'name' => 'Delete Teams', 'slug' => 'teams.delete', 'module' => 'teams', 'action' => 'delete'],
            
            // Settings permissions
            ['id' => 'perm_settings_read', 'name' => 'Read Settings', 'slug' => 'settings.read', 'module' => 'settings', 'action' => 'read'],
            ['id' => 'perm_settings_update', 'name' => 'Update Settings', 'slug' => 'settings.update', 'module' => 'settings', 'action' => 'update'],
            
            // Report permissions
            ['id' => 'perm_reports_read', 'name' => 'Read Reports', 'slug' => 'reports.read', 'module' => 'reports', 'action' => 'read'],
            ['id' => 'perm_reports_create', 'name' => 'Create Reports', 'slug' => 'reports.create', 'module' => 'reports', 'action' => 'create'],
            
            // Admin permissions
            ['id' => 'perm_admin_access', 'name' => 'Admin Access', 'slug' => 'admin.access', 'module' => 'admin', 'action' => 'access']
        ];

        // Insert permissions
        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['id' => $permission['id']],
                array_merge($permission, [
                    'description' => $permission['name'],
                    'created_at' => now(),
                    'updated_at' => now()
                ])
            );
        }

        // Create role_permissions table if it doesn't exist
        if (!DB::getSchemaBuilder()->hasTable('role_permissions')) {
            DB::statement('
                CREATE TABLE role_permissions (
                    id VARCHAR(255) PRIMARY KEY,
                    role_id VARCHAR(255) NOT NULL,
                    permission_id VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP,
                    UNIQUE KEY unique_role_permission (role_id, permission_id)
                )
            ');
        }

        // Assign permissions to roles
        foreach ($roles as $role) {
            foreach ($role['permissions'] as $permissionSlug) {
                $permission = collect($permissions)->firstWhere('slug', $permissionSlug);
                if ($permission) {
                    DB::table('role_permissions')->updateOrInsert(
                        [
                            'role_id' => $role['id'],
                            'permission_id' => $permission['id']
                        ],
                        [
                            'id' => 'rp_' . $role['id'] . '_' . $permission['id'],
                            'created_at' => now(),
                            'updated_at' => now()
                        ]
                    );
                }
            }
        }

        $this->command->info('Roles and permissions seeded successfully!');
    }
}