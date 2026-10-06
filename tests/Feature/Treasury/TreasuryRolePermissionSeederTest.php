<?php declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\TreasuryRolePermissionSeeder;
use Database\Seeders\ZenaPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * GAP-063 S1: treasury.* codes and alias-based role defaults (Gate 2 Option A §1–§2).
 */
class TreasuryRolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_sixteen_treasury_codes_are_canonical_permissions(): void
    {
        $codes = array_column(ZenaPermissionsSeeder::CANONICAL_PERMISSIONS, 'code');

        foreach (TreasuryRolePermissionSeeder::ALL_CODES as $code) {
            $this->assertContains($code, $codes);
        }
        $this->assertCount(16, TreasuryRolePermissionSeeder::ALL_CODES);
        $this->assertContains('treasury.all_projects', TreasuryRolePermissionSeeder::ALL_CODES);
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function roleProvider(): array
    {
        $accountant = ['treasury.view', 'treasury.all_projects', 'treasury.manage_parties', 'treasury.manage_wallets',
            'treasury.approve_expense', 'treasury.reconcile', 'treasury.reverse', 'treasury.adjust',
            'treasury.view_audit', 'treasury.export'];
        $engineer = ['treasury.view', 'treasury.declare_funding', 'treasury.create_transfer',
            'treasury.create_expense', 'treasury.submit_expense'];

        return [
            'System Admin' => ['System Admin', TreasuryRolePermissionSeeder::ALL_CODES],
            'Admin' => ['Admin', TreasuryRolePermissionSeeder::ALL_CODES],
            'super_admin' => ['super_admin', TreasuryRolePermissionSeeder::ALL_CODES],
            'Finance' => ['Finance', $accountant],
            'finance' => ['finance', $accountant],
            'PM' => ['PM', $engineer],
            'Project Manager' => ['Project Manager', $engineer],
            'project_manager' => ['project_manager', $engineer],
            'SiteEngineer' => ['SiteEngineer', $engineer],
            'site_engineer' => ['site_engineer', $engineer],
            'Project Member' => ['Project Member', ['treasury.view']],
            'Designer' => ['Designer', ['treasury.view']],
            'quality_inspector' => ['quality_inspector', ['treasury.view']],
            'Procurement' => ['Procurement', ['treasury.view']],
            'Client' => ['Client', []],
            'client' => ['client', []],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('roleProvider')]
    public function test_role_receives_exactly_its_treasury_codes(string $roleName, array $expected): void
    {
        $role = Role::factory()->create(['name' => $roleName]);

        $this->seedTreasury();

        $granted = $this->treasuryCodesOf($role);
        sort($expected);
        $this->assertSame($expected, $granted);
    }

    public function test_self_approval_is_held_by_owner_roles_only(): void
    {
        $this->assertSame(
            [],
            array_values(array_filter(
                array_merge(
                    TreasuryRolePermissionSeeder::ACCOUNTANT_CODES,
                    TreasuryRolePermissionSeeder::ENGINEER_CODES,
                    TreasuryRolePermissionSeeder::VIEWER_CODES
                ),
                static fn (string $code): bool => $code === 'treasury.self_approve_expense'
            ))
        );
    }

    public function test_rerun_is_idempotent_and_keeps_other_grants(): void
    {
        $role = Role::factory()->create(['name' => 'Finance']);
        $other = Permission::query()->firstOrCreate(
            ['code' => 'invoice.read'],
            ['name' => 'invoice.read', 'module' => 'invoice', 'action' => 'read']
        );
        $role->permissions()->attach($other->id);

        $this->seedTreasury();
        $this->seedTreasury();

        $this->assertCount(10, $this->treasuryCodesOf($role));
        $this->assertTrue($role->permissions()->where('code', 'invoice.read')->exists());
    }

    private function seedTreasury(): void
    {
        $this->seed(ZenaPermissionsSeeder::class);
        $this->seed(TreasuryRolePermissionSeeder::class);
    }

    /**
     * @return list<string>
     */
    private function treasuryCodesOf(Role $role): array
    {
        $codes = $role->permissions()
            ->where('code', 'like', 'treasury.%')
            ->pluck('code')
            ->map(static fn ($code): string => (string) $code)
            ->unique()
            ->values()
            ->all();
        sort($codes);

        return $codes;
    }
}
