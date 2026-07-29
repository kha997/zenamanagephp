<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class ZenaProjectManagerRolePermissionSeeder extends Seeder
{
    public const PROJECT_MANAGER_PERMISSION_CODES = [
        'rfi.escalate',
        'rfi.cancel',
    ];

    public function run(): void
    {
        /** @var Role|null $role */
        $role = Role::query()->whereRaw('LOWER(name) = ?', ['project_manager'])->first();

        if (!$role) {
            return;
        }

        $permissionIds = Permission::query()->whereIn('code', self::PROJECT_MANAGER_PERMISSION_CODES)->pluck('id')->all();

        if (empty($permissionIds)) {
            return;
        }

        $role->permissions()->syncWithoutDetaching($permissionIds);
    }
}
