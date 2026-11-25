<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Support\Rbac\RbacSeedGrants;
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
        // OWN-2026-024 H2b (D4): single seed grant path — additive, global roles only, never platform.*, never a revoked grant.
        RbacSeedGrants::grantCodesToGlobalRolesNamed($roleNames, $codes);
    }
}
