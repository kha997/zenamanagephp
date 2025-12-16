<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permissions = [
            [
                'code' => 'admin.access.tenant',
                'module' => 'admin',
                'action' => 'access.tenant',
                'description' => 'Access admin panel with tenant scope (Org Admin)'
            ],
            [
                'code' => 'admin.templates.manage',
                'module' => 'admin',
                'action' => 'templates.manage',
                'description' => 'Manage WBS templates for tenant'
            ],
            [
                'code' => 'admin.projects.read',
                'module' => 'admin',
                'action' => 'projects.read',
                'description' => 'Read-only access to projects portfolio in tenant'
            ],
            [
                'code' => 'admin.projects.force_ops',
                'module' => 'admin',
                'action' => 'projects.force_ops',
                'description' => 'Force operations on projects (freeze, archive, suspend)'
            ],
            [
                'code' => 'admin.settings.tenant',
                'module' => 'admin',
                'action' => 'settings.tenant',
                'description' => 'Manage tenant-level settings'
            ],
            [
                'code' => 'admin.analytics.tenant',
                'module' => 'admin',
                'action' => 'analytics.tenant',
                'description' => 'View tenant-scoped analytics'
            ],
            [
                'code' => 'admin.activities.tenant',
                'module' => 'admin',
                'action' => 'activities.tenant',
                'description' => 'View tenant-scoped audit log and activities'
            ],
        ];

        foreach ($permissions as $permData) {
            Permission::firstOrCreate(
                ['code' => $permData['code']],
                $permData
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissionCodes = [
            'admin.access.tenant',
            'admin.templates.manage',
            'admin.projects.read',
            'admin.projects.force_ops',
            'admin.settings.tenant',
            'admin.analytics.tenant',
            'admin.activities.tenant',
        ];

        Permission::whereIn('code', $permissionCodes)->delete();
    }
};
