<?php

declare(strict_types=1);

namespace Tests\Feature\Recovery;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use App\Support\PlatformPermissions;
use App\Support\Rbac\PlatformOperators;
use App\Support\Rbac\RbacProtection;
use App\Support\Rbac\RbacSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * UAT RECOVERY 07d — OWN-2026-024 (O-17h) H3 (Owner: "H2B_ACCEPTED / H3_LOCAL_IMPLEMENTATION_APPROVED", 2026-11-25).
 *
 * The `isSuperAdmin()` short-circuit in RoleBasedAccessControlMiddleware::checkAccess() is removed. These tests prove the Gate 3
 * acceptance items that the H1/H2a/H2b classes do not already cover, through the REAL middleware (no bypass left to lean on):
 * B exact grant scope, C revocation at the route after repeated seeding, D/E/F platform authority = explicit permission AND
 * active operator ledger, G no self-promotion, N fail-closed without the H2b schema, plus a static guard against the
 * short-circuit returning. DocumentPolicy / SidebarConfigPolicy shortcuts are untouched (H4).
 * Fixture: production:rbac on a fresh database; test-owned roles only.
 */
#[Group('uat-recovery-07-o17h')]
class UatRecovery07dO17hH3Test extends TestCase
{
    use RefreshDatabase;

    /** Legacy role-name arguments the middleware treats as roles, not permissions (O-8, out of scope). */
    private const ROLE_ARGS = ['project_manager', 'team_member', 'client', 'viewer'];

    private Tenant $a;

    private Tenant $b;

    private Role $ownerRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        Artisan::call('production:rbac');
        $this->a = Tenant::factory()->create();
        $this->b = Tenant::factory()->create();
        $this->ownerRole = Role::query()->where('name', 'super_admin')->firstOrFail();
    }

    protected function tearDown(): void
    {
        RbacSchema::reset();
        parent::tearDown();
    }

    // ===================================================================== Gate 1: the short-circuit is gone and stays gone

    public function test_middleware_has_no_role_name_short_circuit(): void
    {
        $method = new \ReflectionMethod(RoleBasedAccessControlMiddleware::class, 'checkAccess');
        $lines = array_slice(file((string) $method->getFileName()) ?: [], $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1);
        $code = implode('', array_filter($lines, static fn (string $l): bool => !str_starts_with(ltrim($l), '//')));

        $this->assertStringNotContainsString('isSuperAdmin', $code, 'H3: checkAccess() must not admit a role by name');
        $this->assertStringNotContainsString('super_admin', $code);
        $this->assertStringNotContainsString('hasRole(', $code, 'permission arguments are decided by canonical grants only');
    }

    // ===================================================================== B: explicit grants authorize only their operations

    public function test_explicit_grants_open_exactly_the_gates_they_name(): void
    {
        $codes = $this->gateCodes();
        $this->assertGreaterThan(100, count($codes), 'route-table enumeration sanity');

        // A narrow test role: exactly two ordinary codes.
        $narrow = $this->userWith($this->a, [$this->roleWith('o17h_d_narrow', ['role.view', 'task.view'], (string) $this->a->id)]);
        $this->assertSame(['role.view', 'task.view'], $this->passingCodes($narrow, $codes));

        // The fully provisioned Owner passes exactly the codes its role is granted — never a platform code, never anything else.
        $owner = $this->userWith($this->a, [$this->ownerRole]);
        $granted = $this->ownerRole->permissions()->pluck('name')->all();
        $expected = array_values(array_filter($codes, static fn (string $c): bool => in_array($c, $granted, true)));
        $this->assertSame($expected, $this->passingCodes($owner, $codes));
        $this->assertSame([], array_values(array_filter($this->passingCodes($owner, $codes), [PlatformPermissions::class, 'isPlatformCode'])));

        // A user with no role at all passes nothing (no default, no role name).
        $this->assertSame([], $this->passingCodes($this->userWith($this->a, []), $codes));
    }

    // ===================================================================== C: revocation holds at the route after repeated seeding

    public function test_revoked_owner_grant_is_denied_at_the_route_after_two_reseeds(): void
    {
        $approver = $this->userWith($this->a, [$this->ownerRole]);
        $owner = $this->userWith($this->a, [$this->ownerRole]);
        // Middleware-level "before" check (an HTTP request here would bind a tenant context in-process and make the reseed below
        // trip the known S-2 WorkTemplateBaselineSeeder collision, which is unrelated to RBAC).
        $this->assertTrue($this->gatePasses($owner, 'role.view'));

        $this->artisan('rbac:permission', ['action' => 'revoke', '--role' => 'super_admin', '--code' => 'role.view', '--reason' => 'o17h h3 test', '--approval' => 'OWN-2026-024', '--approved-by' => $approver->email, '--yes' => true])->assertExitCode(0);
        Artisan::call('production:rbac');
        Artisan::call('production:rbac');

        Sanctum::actingAs($owner->fresh(), ['*']);
        $this->getJson('/api/v1/rbac/roles')->assertForbidden();
        $this->assertFalse($this->gatePasses($owner->fresh(), 'role.view'));
        $this->assertTrue($this->gatePasses($owner->fresh(), 'role.create'), 'only the revoked pair is affected');

        // A pivot row re-added outside the governed path still does not authorize while the revocation is active.
        DB::table('role_permissions')->insertOrIgnore(['role_id' => (string) $this->ownerRole->id, 'permission_id' => (string) Permission::query()->where('code', 'role.view')->value('id'), 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($owner->fresh(), ['*']);
        $this->getJson('/api/v1/rbac/roles')->assertForbidden();
    }

    // ===================================================================== D / E / F / K: platform authority

    public function test_platform_gates_need_explicit_permission_and_an_active_operator_record(): void
    {
        $platformRoutes = $this->platformGatedRoutes();
        $this->assertCount(62, $platformRoutes, 'the 62 re-gated host routes (102 §3)');

        // D: fully provisioned tenant Owner, no operator record -> every platform gate denied.
        $owner = $this->userWith($this->a, [$this->ownerRole]);
        $this->assertSame([], $this->passingPlatformRoutes($owner, $platformRoutes));

        // E: the Owner role carrying platform permissions (a leaked pivot row, bypassing every guard) but no operator record.
        $platformIds = Permission::query()->where('code', 'like', PlatformPermissions::PREFIX . '%')->pluck('id')->all();
        $this->assertCount(5, $platformIds);
        foreach ($platformIds as $id) {
            DB::table('role_permissions')->insert(['role_id' => (string) $this->ownerRole->id, 'permission_id' => (string) $id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->assertSame([], $this->passingPlatformRoutes($owner->fresh(), $platformRoutes), 'permission without operator status is denied');
        DB::table('role_permissions')->where('role_id', (string) $this->ownerRole->id)->whereIn('permission_id', $platformIds)->delete();

        // F (half 1): an operator ledger row WITHOUT the explicit permission confers nothing.
        $ledgerOnly = $this->userWith($this->a, []);
        DB::table('platform_operators')->insert(['id' => (string) Str::ulid(), 'user_id' => (string) $ledgerOnly->id, 'granted_by_actor' => 'test', 'reason' => 'test', 'approval_reference' => 'test', 'granted_at' => now(), 'active_key' => (string) $ledgerOnly->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertTrue(PlatformOperators::isActive((string) $ledgerOnly->id));
        $this->assertSame([], $this->passingPlatformRoutes($ledgerOnly->fresh(), $platformRoutes), 'operator status without permission is denied');

        // F (half 2): a server-provisioned operator (permission + ledger) passes every platform gate; revocation closes them again.
        $operator = $this->userWith($this->b, []);
        $this->artisan('platform:operator', ['action' => 'grant', '--user' => $operator->email, '--reason' => 'h3 test', '--approval' => 'OWN-2026-024', '--yes' => true])->assertExitCode(0);
        $this->assertCount(62, $this->passingPlatformRoutes($operator->fresh(), $platformRoutes));
        $this->assertFalse($this->gatePasses($operator->fresh(), 'role.view'), 'platform authority is not tenant authority');
        $this->artisan('platform:operator', ['action' => 'revoke', '--user' => $operator->email, '--reason' => 'h3 test', '--yes' => true])->assertExitCode(0);
        $this->assertSame([], $this->passingPlatformRoutes($operator->fresh(), $platformRoutes));

        // HTTP spot check through the full stack.
        Sanctum::actingAs($owner->fresh(), ['*']);
        $this->getJson('/api/cache/stats')->assertForbidden();
        $this->postJson('/api/cache/clear')->assertForbidden();
    }

    // ===================================================================== G: no self-promotion through the real middleware

    public function test_role_administrator_cannot_self_promote_to_owner_or_platform_operator(): void
    {
        $this->artisan('platform:operator', ['action' => 'grant', '--user' => $this->userWith($this->b, [])->email, '--reason' => 'creates the role', '--approval' => 'test', '--yes' => true])->assertExitCode(0);
        $platformRole = Role::query()->where('name', RbacProtection::PLATFORM_OPERATOR_ROLE)->firstOrFail();
        $roleAdmin = $this->userWith($this->a, [$this->roleWith('o17h_d_role_admin', ['role.view', 'role.assign', 'role.edit', 'role.create', 'user.manage_roles'], (string) $this->a->id)]);
        $before = $this->snapshot();

        Sanctum::actingAs($roleAdmin, ['*']);
        foreach ([$this->ownerRole, $platformRole] as $role) {
            $this->assertSame(403, $this->postJson("/api/v1/rbac/assignments/users/{$roleAdmin->id}/roles", ['role_id' => (string) $role->id])->status(), "self-assign {$role->name}");
            $this->postJson('/api/v1/rbac/bulk-assign-roles', ['user_ids' => [(string) $roleAdmin->id], 'role_ids' => [(string) $role->id], 'scope' => 'system']);
        }
        $this->assertSame($before, $this->snapshot(), 'nothing may persist');
        $this->assertFalse(RbacProtection::hasOwnerAuthority($roleAdmin->fresh()));
        $this->assertFalse($roleAdmin->fresh()->hasPermission(PlatformPermissions::OPS_VIEW));
    }

    // ===================================================================== Owner lock-out protection (Gate 7 review finding)

    public function test_owner_authority_cannot_be_removed_from_the_owner_role_over_http(): void
    {
        $owner = $this->userWith($this->a, [$this->ownerRole]);
        $codes = $this->ownerRole->permissions()->pluck('code')->all();
        $this->assertContains(RbacProtection::OWNER_AUTHORITY_CODE, $codes);
        $before = $this->snapshot();

        Sanctum::actingAs($owner, ['*']);
        $without = array_values(array_diff($codes, [RbacProtection::OWNER_AUTHORITY_CODE]));
        // The global Owner role is read-only over HTTP (GAP-042: 404); the explicit H3 guard in RoleController::syncPermissions
        // (403) is defense in depth should that ever change. Either way nothing may change.
        $this->assertContains($this->postJson("/api/v1/rbac/roles/{$this->ownerRole->id}/permissions", ['permission_codes' => $without])->status(), [403, 404]);

        $this->assertSame($before, $this->snapshot(), 'nothing persisted');
        $this->assertTrue(RbacProtection::hasOwnerAuthority($owner->fresh()));
    }

    // ===================================================================== N: missing H2b schema fails closed

    public function test_missing_h2b_schema_fails_closed_for_sensitive_operations(): void
    {
        $owner = $this->userWith($this->a, [$this->ownerRole]);
        $user = $this->userWith($this->a, []);
        $before = $this->snapshot();

        $this->simulatePreMigrationSchema();

        // Ordinary canonical grants keep working (no revocation can exist before the migration) ...
        $this->assertTrue($owner->fresh()->hasPermission('role.view'));
        $this->assertTrue($this->gatePasses($owner->fresh(), 'role.view'));
        // ... platform authority is shut ...
        $this->assertFalse($this->gatePasses($owner->fresh(), PlatformPermissions::OPS_VIEW));
        // ... and the governed writes refuse instead of half-applying.
        $this->artisan('rbac:permission', ['action' => 'revoke', '--role' => 'super_admin', '--code' => 'role.view', '--reason' => 'x', '--approval' => 'x', '--approved-by' => $owner->email, '--yes' => true])->assertExitCode(1);
        $this->artisan('platform:operator', ['action' => 'grant', '--user' => $user->email, '--reason' => 'x', '--approval' => 'x', '--yes' => true])->assertExitCode(1);
        $this->assertSame($before, $this->snapshot(), 'nothing persisted');
    }

    // ===================================================================== Gate 6: rollback cannot silently restore revoked access

    public function test_migration_rollback_is_refused_while_revocations_or_operators_are_active(): void
    {
        $migration = require base_path('database/migrations/2026_11_24_200000_own_2026_024_h2b_rbac_revocations_and_platform_operators.php');
        $approver = $this->userWith($this->a, [$this->ownerRole]);
        $this->artisan('rbac:permission', ['action' => 'revoke', '--role' => 'super_admin', '--code' => 'role.view', '--reason' => 'h3 rollback test', '--approval' => 'OWN-2026-024', '--approved-by' => $approver->email, '--yes' => true])->assertExitCode(0);

        try {
            $migration->down();
            $this->fail('rollback must be refused while an active revocation exists');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Refusing to roll back', $e->getMessage());
        }
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('role_permission_revocations'));
        $this->assertSame(1, DB::table('role_permission_revocations')->whereNull('superseded_at')->count());
        Artisan::call('production:rbac');
        $this->assertFalse($this->ownerRole->permissions()->where('code', 'role.view')->exists(), 'revoked access still not restored');
    }

    // ===================================================================== helpers

    /** RbacSchema memoizes table presence per process; force the "H2b tables absent" answer without DDL (DB-agnostic). */
    private function simulatePreMigrationSchema(): void
    {
        RbacSchema::reset();
        foreach (['revocations', 'operators', 'grantSource'] as $property) {
            $p = new \ReflectionProperty(RbacSchema::class, $property);
            $p->setValue(null, false);
        }
    }

    /** @return list<string> distinct rbac:<permission> arguments in the route table, sorted */
    private function gateCodes(): array
    {
        $codes = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($this->rbacArgs($route) as $arg) {
                $codes[$arg] = true;
            }
        }
        $codes = array_keys($codes);
        sort($codes);

        return $codes;
    }

    /** @return list<array{0: string, 1: list<string>}> [label, args] for routes gated on a platform.* code */
    private function platformGatedRoutes(): array
    {
        $out = [];
        foreach (Route::getRoutes() as $route) {
            $args = $this->rbacArgs($route);
            if (PlatformPermissions::containsPlatformCode($args)) {
                $out[] = [implode('|', $route->methods()) . ' ' . $route->uri(), $args];
            }
        }

        return $out;
    }

    /**
     * @param list<array{0: string, 1: list<string>}> $routes
     * @return list<string>
     */
    private function passingPlatformRoutes(User $user, array $routes): array
    {
        $passed = [];
        foreach ($routes as [$label, $args]) {
            $all = true;
            foreach ($args as $arg) {
                $all = $all && $this->gatePasses($user, $arg);
            }
            if ($all) {
                $passed[] = $label;
            }
        }

        return $passed;
    }

    /** @return list<string> */
    private function rbacArgs(\Illuminate\Routing\Route $route): array
    {
        $args = [];
        foreach ($route->gatherMiddleware() as $m) {
            if (is_string($m) && (str_starts_with($m, 'rbac:') || str_starts_with($m, RoleBasedAccessControlMiddleware::class . ':'))) {
                $arg = explode(',', explode(':', $m, 2)[1])[0];
                if ($arg !== '' && !in_array($arg, self::ROLE_ARGS, true)) {
                    $args[] = $arg;
                }
            }
        }

        return array_values(array_unique($args));
    }

    /**
     * @param list<string> $codes
     * @return list<string>
     */
    private function passingCodes(User $user, array $codes): array
    {
        return array_values(array_filter($codes, fn (string $c): bool => $this->gatePasses($user, $c)));
    }

    private function gatePasses(User $user, string $code): bool
    {
        $this->actingAs($user);
        $request = Request::create('/x', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $request->attributes->set('tenant_id', (string) $user->tenant_id);

        return (new RoleBasedAccessControlMiddleware())->handle($request, static fn (): Response => new Response('ok', 299), $code)->getStatusCode() === 299;
    }

    /** @param list<Role> $roles */
    private function userWith(Tenant $tenant, array $roles): User
    {
        $user = User::factory()->create(['tenant_id' => (string) $tenant->id, 'is_active' => true]);
        foreach ($roles as $role) {
            UserRole::query()->create(['user_id' => (string) $user->id, 'role_id' => (string) $role->id]);
        }

        return $user->fresh();
    }

    /** @param list<string> $codes */
    private function roleWith(string $name, array $codes, ?string $tenantId = null): Role
    {
        $role = Role::query()->create(['name' => $name, 'scope' => 'custom', 'is_active' => true, 'tenant_id' => $tenantId]);
        $role->permissions()->sync(Permission::query()->whereIn('code', $codes)->pluck('id')->all());

        return $role;
    }

    /** @return array<string, int> */
    private function snapshot(): array
    {
        return [
            'roles' => DB::table('roles')->count(),
            'permissions' => DB::table('permissions')->count(),
            'role_permissions' => DB::table('role_permissions')->count(),
            'user_roles' => DB::table('user_roles')->count(),
            'system_user_roles' => DB::table('system_user_roles')->count(),
            'revocations' => DB::table('role_permission_revocations')->count(),
            'audit_logs' => DB::table('audit_logs')->count(),
            'platform_operators' => DB::table('platform_operators')->count(),
        ];
    }
}
