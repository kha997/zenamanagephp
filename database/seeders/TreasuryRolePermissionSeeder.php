<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * GAP-063 S1 — default treasury.* grants per role (Gate 2 Option A §2).
 *
 * The role catalogue is inconsistent across seeders ("System Admin" vs
 * "Admin" vs "super_admin", "Project Manager" vs "PM" vs "project_manager",
 * ...), so each holder is matched by every equivalent name, case-insensitive.
 * Grants are additive (syncWithoutDetaching): re-running never removes a
 * permission an administrator granted by hand. Client roles get nothing.
 */
class TreasuryRolePermissionSeeder extends Seeder
{
    public const ALL_CODES = [
        'treasury.view',
        'treasury.all_projects',
        'treasury.manage_parties',
        'treasury.manage_wallets',
        'treasury.declare_funding',
        'treasury.create_transfer',
        'treasury.create_expense',
        'treasury.submit_expense',
        'treasury.approve_expense',
        'treasury.self_approve_expense',
        'treasury.reconcile',
        'treasury.reverse',
        'treasury.adjust',
        'treasury.view_audit',
        'treasury.export',
        'treasury.manage_period_lock',
    ];

    public const ACCOUNTANT_CODES = [
        'treasury.view',
        'treasury.all_projects',
        'treasury.manage_parties',
        'treasury.manage_wallets',
        'treasury.approve_expense',
        'treasury.reconcile',
        'treasury.reverse',
        'treasury.adjust',
        'treasury.view_audit',
        'treasury.export',
    ];

    public const ENGINEER_CODES = [
        'treasury.view',
        'treasury.declare_funding',
        'treasury.create_transfer',
        'treasury.create_expense',
        'treasury.submit_expense',
    ];

    public const VIEWER_CODES = [
        'treasury.view',
    ];

    /** Owner/director "X" — may self-approve. */
    public const OWNER_ROLE_NAMES = ['System Admin', 'Admin', 'super_admin', 'system_admin'];

    public const ACCOUNTANT_ROLE_NAMES = ['Finance', 'accountant'];

    /** Engineer / site lead "Z". */
    public const ENGINEER_ROLE_NAMES = ['PM', 'Project Manager', 'project_manager', 'SiteEngineer', 'site_engineer'];

    public const VIEWER_ROLE_NAMES = ['Project Member', 'project_member', 'Designer', 'QC', 'quality_inspector', 'Procurement'];

    public function run(): void
    {
        $this->grant(self::OWNER_ROLE_NAMES, self::ALL_CODES);
        $this->grant(self::ACCOUNTANT_ROLE_NAMES, self::ACCOUNTANT_CODES);
        $this->grant(self::ENGINEER_ROLE_NAMES, self::ENGINEER_CODES);
        $this->grant(self::VIEWER_ROLE_NAMES, self::VIEWER_CODES);
    }

    /**
     * @param list<string> $roleNames
     * @param list<string> $codes
     */
    private function grant(array $roleNames, array $codes): void
    {
        $permissionIds = Permission::query()->whereIn('code', $codes)->pluck('id')->all();
        if ($permissionIds === []) {
            return;
        }

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
