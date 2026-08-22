<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Models\Permission;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-044 Surface 2 regression: TenantUserFactoryTrait::ensurePermissionAttached()
 * must look up Permission by its canonical, actually-unique `code` column,
 * not `name` — reusing an existing seeded row even when that row's `name`
 * is NULL (the real shape RoleSeeder produces; see
 * docs/superpowers/specs/2026-08-22-gap-044-testcase-transaction-and-permission-lookup-design.md
 * §1 for the confirmed RoleSeeder -> PermissionSeeder -> name=NULL
 * provenance, matching pre-existing AUD-28). Runs on SQLite (default) —
 * genuinely exercises `permissions.code`'s real unique constraint, which
 * exists on every driver, so no MySQL is needed for this specific proof.
 */
class PermissionCanonicalIdentityRegressionTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    public function test_reuses_existing_permission_by_code_even_when_name_is_null(): void
    {
        // Replicate RoleSeeder's exact real-world output shape: a
        // permissions row identified by `code`, with `name` left NULL.
        $existing = Permission::create([
            'id' => (string) Str::ulid(),
            'code' => 'project.read',
            'name' => null,
            'module' => 'project',
            'action' => 'read',
            'description' => 'Read Project',
            'is_active' => true,
        ]);

        $this->assertNull($existing->fresh()->name, 'Test setup invariant broken: seeded permission must have a NULL name to replicate the real RoleSeeder shape.');

        $tenant = Tenant::factory()->create();

        $user = $this->createTenantUser(
            $tenant,
            ['name' => 'Regression User', 'email' => 'gap044-permission-identity@example.test'],
            ['project_manager'],
            ['project.read']
        );

        $this->assertSame(
            1,
            Permission::where('code', 'project.read')->count(),
            'A duplicate permission row was created for code=project.read — ensurePermissionAttached() is not reusing the existing row by canonical code identity.'
        );

        $attachedPermissionIds = $user->roles()
            ->first()
            ->permissions()
            ->pluck('permissions.id')
            ->all();

        $this->assertContains(
            $existing->id,
            $attachedPermissionIds,
            'The role was not attached to the pre-existing permission row (by its original id) — canonical code-based reuse did not occur.'
        );
    }
}
