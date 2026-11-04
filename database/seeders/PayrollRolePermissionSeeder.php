<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * GAP-109 Q-1 — payroll.manage (staff register, draft payroll, mark paid) for the owner / director and the accountant;
 * payroll.close (close / reopen a month) for the owner / director only. Additive like TreasuryRolePermissionSeeder;
 * every other role gets nothing, so salaries stay hidden from project staff.
 */
class PayrollRolePermissionSeeder extends Seeder
{
    public const ALL_CODES = ['payroll.manage', 'payroll.close'];

    public const ACCOUNTANT_CODES = ['payroll.manage'];

    public function run(): void
    {
        $this->grant(TreasuryRolePermissionSeeder::OWNER_ROLE_NAMES, self::ALL_CODES);
        $this->grant(TreasuryRolePermissionSeeder::ACCOUNTANT_ROLE_NAMES, self::ACCOUNTANT_CODES);
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

        foreach (Role::query()->whereRaw("LOWER(name) IN ({$placeholders})", $lowered)->get() as $role) {
            /** @var Role $role */
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }
    }
}
