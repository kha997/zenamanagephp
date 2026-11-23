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

    /**
     * O-18 (Owner decision 2026-11-24, Option D + "include Project Manager"): day-to-day operational read codes for
     * project staff — my-work / today / schedule / own timesheet (task.view) and site / design / material records.
     * Granted to BOTH Project Member and Project Manager, additively, on every seed run. This seeder runs after
     * ZenaPermissionsSeeder (which defines the codes), so the first seed already yields the decided state.
     * Commercial codes (crm.view, contract.view, boq.view, vendor.view) and event-record.view are NOT in this list.
     */
    public const O18_OPERATIONAL_PERMISSION_CODES = [
        'task.view',
        'site_diary.view',
        'design-item.view',
        'material.read',
        'material-receipt.view',
        'material.view',
    ];

    public function run(): void
    {
        $this->grantStrict(['project_member', 'project member', 'project_manager', 'project manager'], self::O18_OPERATIONAL_PERMISSION_CODES);
        // GAP-030: RoleSeeder names the role "Project Manager"; older environments may use "project_manager".
        $this->grant(['project_manager', 'project manager'], self::PROJECT_MANAGER_PERMISSION_CODES);
        $this->grant(['project_member', 'project member'], self::PROJECT_MEMBER_PERMISSION_CODES);
    }

    /**
     * O-18: like grant(), but fails loudly when a listed code does not exist yet — silently skipping a missing code
     * is exactly what made the Member baseline depend on how many times the seeder had run.
     *
     * @param list<string> $lowercaseRoleNames
     * @param list<string> $permissionCodes
     */
    private function grantStrict(array $lowercaseRoleNames, array $permissionCodes): void
    {
        $existing = Permission::query()->whereIn('code', $permissionCodes)->pluck('code')->all();
        $missing = array_values(array_diff($permissionCodes, $existing));
        if ($missing !== []) {
            throw new \RuntimeException('O-18: permission codes must be seeded before they are granted; missing: ' . implode(', ', $missing));
        }

        $this->grant($lowercaseRoleNames, $permissionCodes);
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
