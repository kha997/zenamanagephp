<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * GAP-098 — creates the permission codes that /api/v1 routes require but no other
 * seeder creates, and grants defaults per role (Gate 2 Option A §2).
 *
 * Role names are matched by every equivalent spelling, case-insensitive, as in
 * TreasuryRolePermissionSeeder. Grants are additive (syncWithoutDetaching):
 * re-running never removes a permission an administrator granted by hand.
 */
class V1PermissionGapSeeder extends Seeder
{
    public const ALL_CODES = [
        'baseline.view',
        'baseline.create',
        'baseline.edit',
        'baseline.delete',
        'change_request.submit',
        'change_request.delete',
        'compensation.view',
        'compensation.edit',
        'compensation.apply',
        'compensation.preview',
        'compensation.stats',
        'compensation.sync',
        'role.view',
        'role.create',
        'role.edit',
        'role.delete',
        'role.assign',
        'permission.view',
        'permission.create',
        'permission.edit',
        'permission.delete',
        'permission.export',
        'permission.import',
        'user.view',
        'audit.view',
        'template.create',
        'template.edit',
    ];

    public const MANAGER_CODES = [
        'baseline.view',
        'baseline.create',
        'baseline.edit',
        'baseline.delete',
        'change_request.submit',
        'compensation.view',
        'compensation.preview',
        'compensation.stats',
        'template.create',
        'template.edit',
        'user.view',
    ];

    public const ACCOUNTANT_CODES = [
        'compensation.view',
        'compensation.stats',
    ];

    public const VIEWER_CODES = [
        'baseline.view',
        'compensation.view',
    ];

    public const ADMIN_ROLE_NAMES = ['System Admin', 'Admin', 'super_admin', 'system_admin'];

    public const MANAGER_ROLE_NAMES = ['PM', 'Project Manager', 'project_manager', 'SiteEngineer', 'site_engineer'];

    public const ACCOUNTANT_ROLE_NAMES = ['Finance', 'accountant'];

    public const VIEWER_ROLE_NAMES = ['Project Member', 'project_member', 'Designer', 'QC', 'quality_inspector', 'Procurement'];

    public function run(): void
    {
        foreach (self::ALL_CODES as $code) {
            [$module, $action] = explode('.', $code, 2);
            Permission::query()->firstOrCreate(
                ['code' => $code],
                ['name' => $code, 'module' => $module, 'action' => $action, 'description' => $code]
            );
        }

        $this->grant(self::ADMIN_ROLE_NAMES, self::ALL_CODES);
        $this->grant(self::MANAGER_ROLE_NAMES, self::MANAGER_CODES);
        $this->grant(self::ACCOUNTANT_ROLE_NAMES, self::ACCOUNTANT_CODES);
        $this->grant(self::VIEWER_ROLE_NAMES, self::VIEWER_CODES);
    }

    /**
     * @param list<string> $roleNames
     * @param list<string> $codes
     */
    private function grant(array $roleNames, array $codes): void
    {
        $permissionIds = Permission::query()->whereIn('code', $codes)->pluck('id')->all();

        $lowered = array_values(array_unique(array_map('strtolower', $roleNames)));
        $placeholders = implode(',', array_fill(0, count($lowered), '?'));

        $roles = Role::query()
            ->whereRaw("LOWER(name) IN ({$placeholders})", $lowered)
            ->get();

        foreach ($roles as $role) {
            /** @var Role $role */
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }
    }
}
