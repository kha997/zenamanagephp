<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class ZenaProjectManagerRolePermissionSeeder extends Seeder
{
    public const PROJECT_MANAGER_PERMISSION_CODES = [
        'rfi.escalate',
        'rfi.cancel',
        'rfi.resolve-escalation',
        // GAP-015: work through template-based work instances on the project screens.
        'work.view',
        'work.update',
        'work.approve',
        'work.export',
    ];

    /**
     * GAP-030: any tenant user can be chosen as an escalation target, so project members may resolve; the controller
     * still limits resolving to the target, the project manager or an admin.
     */
    public const PROJECT_MEMBER_PERMISSION_CODES = [
        'rfi.resolve-escalation',
        'work.view',
        'work.update',
    ];

    public function run(): void
    {
        // GAP-030: RoleSeeder names the role "Project Manager"; older environments may use "project_manager".
        $this->grant(['project_manager', 'project manager'], self::PROJECT_MANAGER_PERMISSION_CODES);
        $this->grant(['project_member', 'project member'], self::PROJECT_MEMBER_PERMISSION_CODES);
    }

    /**
     * @param list<string> $lowercaseRoleNames
     * @param list<string> $permissionCodes
     */
    private function grant(array $lowercaseRoleNames, array $permissionCodes): void
    {
        $permissionIds = Permission::query()->whereIn('code', $permissionCodes)->pluck('id')->all();

        if (empty($permissionIds)) {
            return;
        }

        foreach ($lowercaseRoleNames as $lowercaseRoleName) {
            /** @var Role|null $role */
            $role = Role::query()->whereRaw('LOWER(name) = ?', [$lowercaseRoleName])->first();

            $role?->permissions()->syncWithoutDetaching($permissionIds);
        }
    }
}
