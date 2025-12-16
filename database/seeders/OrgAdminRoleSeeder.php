<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class OrgAdminRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create org_admin role
        $orgAdminRole = Role::firstOrCreate(
            ['name' => 'org_admin'],
            [
                'name' => 'org_admin',
                'scope' => 'system',
                'description' => 'Organization Admin - Tenant-scoped admin with governance permissions',
                'is_active' => true,
                'allow_override' => false,
            ]
        );

        // Get permissions for org_admin
        $permissionCodes = [
            'admin.access.tenant',
            'admin.members.manage',
            'admin.templates.manage',
            'admin.projects.read',
            'admin.projects.force_ops',
            'admin.settings.tenant',
            'admin.analytics.tenant',
            'admin.activities.tenant',
        ];

        // Get permission IDs
        $permissions = Permission::whereIn('code', $permissionCodes)->get();

        if ($permissions->isEmpty()) {
            $this->command->warn('Permissions not found. Please run the migration first: php artisan migrate');
            return;
        }

        // Sync permissions to role
        $orgAdminRole->permissions()->sync($permissions->pluck('id'));

        $this->command->info('Org Admin role created/updated with ' . $permissions->count() . ' permissions.');
    }
}
