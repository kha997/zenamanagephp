<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
                'scope' => 'system',
                'description' => 'Full system access with all permissions',
                'permissions' => json_encode([
                    'projects.create', 'projects.read', 'projects.update', 'projects.delete',
                    'tasks.create', 'tasks.read', 'tasks.update', 'tasks.delete',
                    'documents.create', 'documents.read', 'documents.update', 'documents.delete',
                    'users.create', 'users.read', 'users.update', 'users.delete',
                    'teams.create', 'teams.read', 'teams.update', 'teams.delete',
                    'settings.read', 'settings.update',
                    'reports.read', 'reports.create',
                    'admin.access'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => 'role_admin',
                'name' => 'Admin',
                'scope' => 'tenant',
                'description' => 'Administrative access with most permissions',
                'permissions' => json_encode([
                    'projects.create', 'projects.read', 'projects.update',
                    'tasks.create', 'tasks.read', 'tasks.update', 'tasks.delete',
                    'documents.create', 'documents.read', 'documents.update', 'documents.delete',
                    'users.create', 'users.read', 'users.update',
                    'teams.create', 'teams.read', 'teams.update',
                    'settings.read', 'settings.update',
                    'reports.read', 'reports.create'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => 'role_project_manager',
                'name' => 'Project Manager',
                'scope' => 'tenant',
                'description' => 'Project management permissions',
                'permissions' => json_encode([
                    'projects.read', 'projects.update',
                    'tasks.create', 'tasks.read', 'tasks.update', 'tasks.delete',
                    'documents.create', 'documents.read', 'documents.update', 'documents.delete',
                    'teams.read', 'teams.update',
                    'reports.read'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => 'role_member',
                'name' => 'Member',
                'scope' => 'tenant',
                'description' => 'Basic member permissions',
                'permissions' => json_encode([
                    'projects.read',
                    'tasks.read', 'tasks.update',
                    'documents.read', 'documents.create',
                    'teams.read'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => 'role_client',
                'name' => 'Client',
                'scope' => 'tenant',
                'description' => 'Client view-only permissions',
                'permissions' => json_encode([
                    'projects.read',
                    'documents.read'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        // Insert roles
        foreach ($roles as $role) {
            $createdAt = $role['created_at'] ?? now();
            $updatedAt = $role['updated_at'] ?? now();

            DB::table('zena_roles')->updateOrInsert(
                ['id' => $role['id']],
                array_merge($role, [
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ])
            );

            if (Schema::hasTable('roles')) {
                DB::table('roles')->updateOrInsert(
                    ['id' => $role['id']],
                    [
                        'name' => $role['name'],
                        'scope' => $role['scope'],
                        'allow_override' => $role['allow_override'] ?? false,
                        'description' => $role['description'],
                        'is_active' => $role['is_active'] ?? true,
                        'tenant_id' => $role['tenant_id'] ?? null,
                        'created_by' => $role['created_by'] ?? null,
                        'updated_by' => $role['updated_by'] ?? null,
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                    ]
                );
            }
        }

        $this->command->info('Roles seeded successfully!');
    }
}
