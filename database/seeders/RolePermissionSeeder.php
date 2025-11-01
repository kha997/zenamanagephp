<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions
        $permissions = [
            // Project permissions
            ['code' => 'project.read', 'module' => 'project', 'action' => 'read', 'description' => 'View Projects'],
            ['code' => 'project.create', 'module' => 'project', 'action' => 'create', 'description' => 'Create Projects'],
            ['code' => 'project.update', 'module' => 'project', 'action' => 'update', 'description' => 'Update Projects'],
            ['code' => 'project.delete', 'module' => 'project', 'action' => 'delete', 'description' => 'Delete Projects'],
            ['code' => 'project.archive', 'module' => 'project', 'action' => 'archive', 'description' => 'Archive Projects'],
            ['code' => 'project.restore', 'module' => 'project', 'action' => 'restore', 'description' => 'Restore Projects'],
            ['code' => 'project.duplicate', 'module' => 'project', 'action' => 'duplicate', 'description' => 'Duplicate Projects'],
            ['code' => 'project.manage_team', 'module' => 'project', 'action' => 'manage_team', 'description' => 'Manage Project Team'],
            ['code' => 'project.view_budget', 'module' => 'project', 'action' => 'view_budget', 'description' => 'View Project Budget'],
            ['code' => 'project.edit_budget', 'module' => 'project', 'action' => 'edit_budget', 'description' => 'Edit Project Budget'],
            ['code' => 'project.view_files', 'module' => 'project', 'action' => 'view_files', 'description' => 'View Project Files'],
            ['code' => 'project.upload_files', 'module' => 'project', 'action' => 'upload_files', 'description' => 'Upload Project Files'],

            // Task permissions
            ['code' => 'task.read', 'module' => 'task', 'action' => 'read', 'description' => 'View Tasks'],
            ['code' => 'task.create', 'module' => 'task', 'action' => 'create', 'description' => 'Create Tasks'],
            ['code' => 'task.update', 'module' => 'task', 'action' => 'update', 'description' => 'Update Tasks'],
            ['code' => 'task.delete', 'module' => 'task', 'action' => 'delete', 'description' => 'Delete Tasks'],
            ['code' => 'task.assign', 'module' => 'task', 'action' => 'assign', 'description' => 'Assign Tasks'],
            ['code' => 'task.comment', 'module' => 'task', 'action' => 'comment', 'description' => 'Comment on Tasks'],
            ['code' => 'task.attach_files', 'module' => 'task', 'action' => 'attach_files', 'description' => 'Attach Files to Tasks'],
            ['code' => 'task.change_status', 'module' => 'task', 'action' => 'change_status', 'description' => 'Change Task Status'],
            ['code' => 'task.view_time_tracking', 'module' => 'task', 'action' => 'view_time_tracking', 'description' => 'View Task Time Tracking'],
            ['code' => 'task.edit_time_tracking', 'module' => 'task', 'action' => 'edit_time_tracking', 'description' => 'Edit Task Time Tracking'],

            // User permissions
            ['code' => 'user.read', 'module' => 'user', 'action' => 'read', 'description' => 'View Users'],
            ['code' => 'user.create', 'module' => 'user', 'action' => 'create', 'description' => 'Create Users'],
            ['code' => 'user.update', 'module' => 'user', 'action' => 'update', 'description' => 'Update Users'],
            ['code' => 'user.delete', 'module' => 'user', 'action' => 'delete', 'description' => 'Delete Users'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['code' => $permission['code']],
                $permission
            );
        }

        // Create roles
        $roles = [
            [
                'name' => 'super_admin',
                'scope' => 'system',
                'description' => 'Full system access',
                'permissions' => Permission::all()->pluck('code')->toArray()
            ],
            [
                'name' => 'admin',
                'scope' => 'system',
                'description' => 'Administrative access',
                'permissions' => Permission::all()->pluck('code')->toArray()
            ],
            [
                'name' => 'project_manager',
                'scope' => 'system',
                'description' => 'Manage projects and tasks',
                'permissions' => [
                    'project.read', 'project.create', 'project.update', 'project.archive', 'project.duplicate',
                    'project.manage_team', 'project.view_budget', 'project.view_files', 'project.upload_files',
                    'task.read', 'task.create', 'task.update', 'task.delete', 'task.assign', 'task.comment',
                    'task.attach_files', 'task.change_status', 'task.view_time_tracking', 'task.edit_time_tracking',
                    'user.read'
                ]
            ],
            [
                'name' => 'designer',
                'scope' => 'system',
                'description' => 'Design and technical work',
                'permissions' => [
                    'project.read', 'project.view_files', 'project.upload_files',
                    'task.read', 'task.create', 'task.update', 'task.comment', 'task.attach_files',
                    'task.change_status', 'task.view_time_tracking', 'task.edit_time_tracking'
                ]
            ],
            [
                'name' => 'site_engineer',
                'scope' => 'system',
                'description' => 'Site construction management',
                'permissions' => [
                    'project.read', 'project.view_files', 'project.upload_files',
                    'task.read', 'task.create', 'task.update', 'task.comment', 'task.attach_files',
                    'task.change_status', 'task.view_time_tracking', 'task.edit_time_tracking'
                ]
            ],
            [
                'name' => 'qc',
                'scope' => 'system',
                'description' => 'Quality control and inspection',
                'permissions' => [
                    'project.read', 'project.view_files', 'project.upload_files',
                    'task.read', 'task.create', 'task.update', 'task.comment', 'task.attach_files',
                    'task.change_status', 'task.view_time_tracking', 'task.edit_time_tracking'
                ]
            ],
            [
                'name' => 'procurement',
                'scope' => 'system',
                'description' => 'Procurement and purchasing',
                'permissions' => [
                    'project.read', 'project.view_budget', 'project.view_files', 'project.upload_files',
                    'task.read', 'task.create', 'task.update', 'task.comment', 'task.attach_files',
                    'task.change_status', 'task.view_time_tracking', 'task.edit_time_tracking'
                ]
            ],
            [
                'name' => 'finance',
                'scope' => 'system',
                'description' => 'Financial management',
                'permissions' => [
                    'project.read', 'project.view_budget', 'project.edit_budget', 'project.view_files',
                    'task.read', 'task.view_time_tracking', 'task.edit_time_tracking'
                ]
            ],
            [
                'name' => 'client',
                'scope' => 'system',
                'description' => 'Client access',
                'permissions' => [
                    'project.read', 'project.view_budget', 'project.view_files',
                    'task.read', 'task.comment'
                ]
            ]
        ];

        foreach ($roles as $roleData) {
            $permissions = $roleData['permissions'];
            unset($roleData['permissions']);

            $role = Role::firstOrCreate(
                ['name' => $roleData['name']],
                $roleData
            );

            // Assign permissions to role
            $permissionIds = Permission::whereIn('name', $permissions)->pluck('id');
            $role->permissions()->sync($permissionIds);
        }
    }
}