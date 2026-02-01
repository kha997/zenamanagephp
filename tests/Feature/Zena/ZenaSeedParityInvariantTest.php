<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Models\Permission as AppPermission;
use App\Models\Role as AppRole;
use Database\Seeders\ZenaAdminRolePermissionSeeder;
use Database\Seeders\ZenaPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * @group zena-invariants
 */
class ZenaSeedParityInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_are_repeatable_and_admin_receives_every_permission(): void
    {
        $this->runPermissionSeedersTwice();
        $this->createAdminRoles();
        $this->runAdminSeederTwice();

        $canonicalCodes = $this->canonicalPermissionCodes();
        $expectedCount = count($canonicalCodes);

        $this->assertSame(
            $expectedCount,
            AppPermission::whereIn('code', $canonicalCodes)->distinct('code')->count('code'),
            'Canonical permission codes should exist without duplicates.'
        );

        $this->assertFalse(
            $this->hasDuplicate('code', $canonicalCodes),
            'No duplicate canonical codes should exist across the permission table.'
        );

        if (Schema::hasColumn('permissions', 'name')) {
            $this->assertSame(
                $expectedCount,
                AppPermission::whereIn('name', $canonicalCodes)->distinct('name')->count('name'),
                'Canonical permission names should remain one-to-one with canonical codes.'
            );

            $this->assertFalse(
                $this->hasDuplicate('name', $canonicalCodes),
                'No duplicate canonical names should exist across the permission table.'
            );

            foreach ($canonicalCodes as $code) {
                $permission = AppPermission::where('code', $code)->firstOrFail();
                $this->assertSame($code, $permission->name, "Permission {$code} must keep its name aligned with the code for RBAC lookups.");
            }
        }

        $adminRole = AppRole::whereRaw('LOWER(name) = ?', ['system admin'])->first();

        $this->assertNotNull($adminRole, 'Expected a System Admin role to exist after seeding admin roles.');
        $this->assertSame(
            $expectedCount,
            $adminRole->permissions()->count(),
            'The System Admin role must retain every canonical permission.'
        );
    }

    private function runPermissionSeedersTwice(): void
    {
        $seeder = new ZenaPermissionsSeeder();
        $seeder->run();
        $seeder->run();
    }

    private function createAdminRoles(): void
    {
        foreach (ZenaAdminRolePermissionSeeder::ADMIN_ROLE_NAMES as $name) {
            AppRole::create([
                'name' => $name,
                'scope' => AppRole::SCOPE_SYSTEM,
                'allow_override' => false,
                'description' => "Parity seed role {$name}",
                'is_active' => true,
            ]);
        }
    }

    private function runAdminSeederTwice(): void
    {
        $seeder = new ZenaAdminRolePermissionSeeder();
        $seeder->run();
        $seeder->run();
    }

    private function canonicalPermissionCodes(): array
    {
        return array_column(ZenaPermissionsSeeder::CANONICAL_PERMISSIONS, 'code');
    }

    private function hasDuplicate(string $column, array $codes): bool
    {
        if (!Schema::hasColumn('permissions', $column) || empty($codes)) {
            return false;
        }

        return DB::table('permissions')
            ->select($column)
            ->whereIn($column, $codes)
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }
}
