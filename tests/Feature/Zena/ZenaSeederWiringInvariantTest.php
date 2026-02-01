<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Models\Permission as AppPermission;
use App\Models\Role as AppRole;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ZenaAdminRolePermissionSeeder;
use Database\Seeders\ZenaPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * @group zena-invariants
 */
class ZenaSeederWiringInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_wires_canonical_zena_seeders_without_duplicates(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $canonicalCodes = $this->canonicalPermissionCodes();
        $expectedCount = count($canonicalCodes);

        $this->assertTrue(
            Schema::hasColumn('permissions', 'code'),
            'The permissions table must expose a code column for canonical ZENA permissions.'
        );

        $persistedPermissions = AppPermission::query()
            ->whereIn('code', $canonicalCodes)
            ->get();

        $this->assertSame(
            $expectedCount,
            $persistedPermissions->count(),
            'Seeding twice should not produce extra permission rows beyond the canonical set.'
        );

        $this->assertSame(
            $expectedCount,
            $persistedPermissions->pluck('code')->unique()->count(),
            'Canonical permission codes must remain de-duplicated after repeated seeding.'
        );

        if (Schema::hasColumn('permissions', 'name')) {
            foreach ($canonicalCodes as $code) {
                $permission = AppPermission::where('code', $code)->firstOrFail();
                $this->assertSame(
                    $code,
                    $permission->name,
                    "Permission {$code} must keep its name aligned with the code for RBAC lookups."
                );
            }
        }

        $roles = $this->resolveAdminRoles();
        $this->assertNotEmpty(
            $roles,
            'No System Admin-style role was seeded. Expect at least one of: ' . implode(', ', ZenaAdminRolePermissionSeeder::ADMIN_ROLE_NAMES)
        );

        $roleNames = $roles->pluck('name')->unique()->values()->all();

        $assignedCodes = $roles
            ->flatMap(fn (AppRole $role): Collection => $role->permissions->pluck('code'))
            ->unique()
            ->values()
            ->all();

        $missing = array_values(array_diff($canonicalCodes, $assignedCodes));

        $diagnostics = sprintf(
            'Canonical count: %d, assigned to %d role(s): %s',
            $expectedCount,
            count($assignedCodes),
            implode(', ', $roleNames)
        );

        $this->assertEmpty(
            $missing,
            'The System Admin role(s) must retain every canonical permission. Missing: ' . implode(', ', $missing) . '. ' . $diagnostics
        );
    }

    private function canonicalPermissionCodes(): array
    {
        return array_column(ZenaPermissionsSeeder::CANONICAL_PERMISSIONS, 'code');
    }

    private function resolveAdminRoles(): Collection
    {
        $lowered = array_map('strtolower', ZenaAdminRolePermissionSeeder::ADMIN_ROLE_NAMES);

        return AppRole::with('permissions')
            ->get()
            ->filter(fn (AppRole $role) => in_array(strtolower($role->name), $lowered, true));
    }
}
