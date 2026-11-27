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
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * UAT RECOVERY 07b — OWN-2026-024 (O-17h) H2a: the platform permission namespace (Owner Gate 2, 2026-11-24:
 * GATE2_CONDITIONAL_APPROVED / H2A_LOCAL_IMPLEMENTATION_AUTHORIZED; D1, D2, D3, D6).
 *
 * All tests here are GREEN guards. They do NOT prove the Owner is blocked: the legacy super_admin middleware short-circuit
 * stays until H3 and still admits that role on every platform route (see UatRecovery07O17hExplicitRbacTest RED group and
 * tests/Feature/Recovery/QUARANTINE-O17H.md). Fixture = production:rbac on a fresh database; test-owned roles only.
 */
#[Group('uat-recovery-07-o17h')]
class UatRecovery07bO17hH2aPlatformTest extends TestCase
{
    use RefreshDatabase;

    /** Exact host-route -> platform-code mapping approved for H2a (102 §3). Distinct code per operation category. */
    private const PLATFORM_ROUTES = [
        'POST admin/maintenance/backup-database' => 'platform.backup.execute',
        'POST api/cache/clear' => 'platform.cache.manage',
        'POST api/cache/invalidate/key' => 'platform.cache.manage',
        'POST api/cache/invalidate/pattern' => 'platform.cache.manage',
        'POST api/cache/invalidate/tags' => 'platform.cache.manage',
        'POST api/cache/warmup' => 'platform.cache.manage',
        'POST admin/maintenance/cleanup-logs' => 'platform.maintenance.execute',
        'POST admin/maintenance/clear-cache' => 'platform.maintenance.execute',
        'POST admin/maintenance/database' => 'platform.maintenance.execute',
        'POST api/legacy-routes/cleanup' => 'platform.maintenance.execute',
        'POST api/legacy-routes/record-usage' => 'platform.maintenance.execute',
        'POST api/v1/performance/implement-caching' => 'platform.maintenance.execute',
        'POST api/v1/performance/optimize-api' => 'platform.maintenance.execute',
        'POST api/v1/performance/optimize-assets' => 'platform.maintenance.execute',
        'POST api/v1/performance/optimize-database' => 'platform.maintenance.execute',
        'GET admin' => 'platform.ops.view',
        'GET admin/activities' => 'platform.ops.view',
        'GET admin/alerts' => 'platform.ops.view',
        'GET admin/analytics' => 'platform.ops.view',
        'GET admin/dashboard' => 'platform.ops.view',
        'GET admin/maintenance' => 'platform.ops.view',
        'GET admin/projects' => 'platform.ops.view',
        'GET admin/security' => 'platform.ops.view',
        'GET admin/settings' => 'platform.ops.view',
        'GET admin/sidebar-builder' => 'platform.ops.view',
        'GET api/admin/dashboard/metrics' => 'platform.ops.view',
        'GET api/admin/dashboard/stats' => 'platform.ops.view',
        'GET api/cache/config' => 'platform.ops.view',
        'GET api/cache/stats' => 'platform.ops.view',
        'GET api/legacy-routes/migration-phase' => 'platform.ops.view',
        'GET api/legacy-routes/report' => 'platform.ops.view',
        'GET api/legacy-routes/usage' => 'platform.ops.view',
        'POST api/v1/final-integration/complete-launch-task' => 'platform.ops.view',
        'POST api/v1/final-integration/execute-action' => 'platform.ops.view',
        'GET api/v1/final-integration/go-live-checklist' => 'platform.ops.view',
        'POST api/v1/final-integration/launch-actions' => 'platform.ops.view',
        'GET api/v1/final-integration/launch-metrics' => 'platform.ops.view',
        'POST api/v1/final-integration/launch-preparation-tasks' => 'platform.ops.view',
        'GET api/v1/final-integration/launch-report' => 'platform.ops.view',
        'GET api/v1/final-integration/launch-status' => 'platform.ops.view',
        'POST api/v1/final-integration/pre-launch-actions' => 'platform.ops.view',
        'POST api/v1/final-integration/production-readiness-checks' => 'platform.ops.view',
        'POST api/v1/final-integration/run-production-check' => 'platform.ops.view',
        'POST api/v1/final-integration/system-integration-checks' => 'platform.ops.view',
        'POST api/v1/final-integration/toggle-checklist-item' => 'platform.ops.view',
        'POST api/v1/final-integration/validate-integration' => 'platform.ops.view',
        'GET api/v1/performance/analysis' => 'platform.ops.view',
        'GET api/v1/performance/metrics' => 'platform.ops.view',
        'GET api/v1/performance/recommendations' => 'platform.ops.view',
        'POST api/v1/universal-frame/analysis' => 'platform.ops.view',
        'GET api/v1/universal-frame/analysis/{context}' => 'platform.ops.view',
        'GET api/v1/universal-frame/analysis/{context}/charts' => 'platform.ops.view',
        'GET api/v1/universal-frame/analysis/{context}/insights' => 'platform.ops.view',
        'GET api/v1/universal-frame/analysis/{context}/metrics' => 'platform.ops.view',
        'POST api/v1/universal-frame/filters/apply' => 'platform.ops.view',
        'GET api/websocket/channels' => 'platform.ops.view',
        'GET api/websocket/info' => 'platform.ops.view',
        'GET api/websocket/stats' => 'platform.ops.view',
        'POST api/v1/universal-frame/alerts/create' => 'platform.realtime.broadcast',
        'POST api/websocket/broadcast' => 'platform.realtime.broadcast',
        'POST api/websocket/notification' => 'platform.realtime.broadcast',
        'GET api/websocket/test' => 'platform.realtime.broadcast',
    ];

    /** The 16 mock / legacy routes retired under D2 (zero references in UI, code, routes, config, tests and fixtures). */
    private const RETIRED = [
        ['GET', 'admin/tasks', 'Closure'],
        ['GET', 'api/admin/dashboard/activities', 'App\Http\Controllers\Api\Admin\DashboardController@getActivities'], // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_LEGACY_SURFACE)
        ['GET', 'api/admin/dashboard/alerts', 'App\Http\Controllers\Api\Admin\DashboardController@getAlerts'], // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_LEGACY_SURFACE)
        ['GET', 'api/v1/universal-frame/activities', 'App\Http\Controllers\ActivityController@index'], // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_LEGACY_SURFACE)
        ['GET', 'api/v1/universal-frame/activities/by-type', 'App\Http\Controllers\ActivityController@byType'], // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_LEGACY_SURFACE)
        ['GET', 'api/v1/universal-frame/activities/stats', 'App\Http\Controllers\ActivityController@stats'], // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_LEGACY_SURFACE)
        ['GET', 'api/v1/universal-frame/alerts', 'App\Http\Controllers\AlertController@index'], // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_LEGACY_SURFACE)
        ['GET', 'api/v1/universal-frame/alerts/stats', 'App\Http\Controllers\AlertController@stats'], // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_LEGACY_SURFACE)
        ['POST', 'api/v1/universal-frame/alerts/acknowledge', 'App\Http\Controllers\AlertController@acknowledge'], // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_LEGACY_SURFACE)
        ['POST', 'api/v1/universal-frame/alerts/mute', 'App\Http\Controllers\AlertController@mute'], // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_LEGACY_SURFACE)
        ['POST', 'api/v1/universal-frame/alerts/dismiss-all', 'App\Http\Controllers\AlertController@dismissAll'], // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_LEGACY_SURFACE)
        ['GET', 'api/v1/universal-frame/filters/presets', 'App\Http\Controllers\FilterController@presets'], // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_LEGACY_SURFACE)
        ['GET', 'api/v1/universal-frame/filters/deep', 'App\Http\Controllers\FilterController@deepFilters'], // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_LEGACY_SURFACE)
        ['GET', 'api/v1/universal-frame/filters/saved-views', 'App\Http\Controllers\FilterController@savedViews'], // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_LEGACY_SURFACE)
        ['POST', 'api/v1/universal-frame/filters/saved-views', 'App\Http\Controllers\FilterController@saveView'], // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_LEGACY_SURFACE)
        ['DELETE', 'api/v1/universal-frame/filters/saved-views/1', 'App\Http\Controllers\FilterController@deleteView'], // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_LEGACY_SURFACE)
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    // ------------------------------------------------------------------ D1: catalogue + no implicit grants

    public function test_platform_catalogue_is_exactly_the_five_approved_codes_and_no_role_holds_them(): void
    {
        // Every seeding path a real environment runs: full DatabaseSeeder (dev/test incl. "System Admin" Permission::all()),
        // then production:rbac twice (all admin/owner alias seeders, baseline grants, re-run idempotency).
        $this->seed(DatabaseSeeder::class);
        Artisan::call('production:rbac');
        Artisan::call('production:rbac');

        $codes = Permission::query()->where('code', 'like', 'platform.%')->orderBy('code')->pluck('code')->all();
        $this->assertSame(['platform.backup.execute', 'platform.cache.manage', 'platform.maintenance.execute', 'platform.ops.view', 'platform.realtime.broadcast'], $codes);
        foreach ($codes as $code) {
            $this->assertSame($code, Permission::query()->where('code', $code)->value('name'), "{$code}: name must equal code (the rbac gate matches on name)");
        }

        $holders = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->where('permissions.code', 'like', 'platform.%')
            ->pluck('roles.name')->unique()->values()->all();
        $this->assertSame([], $holders, 'no role may receive a platform code implicitly (D1/D6)');
        $this->assertTrue(Role::query()->where('name', 'System Admin')->exists(), 'fixture must include the "System Admin" all-permissions role');
    }

    // ------------------------------------------------------------------ D1: distinct controls per operation category

    public function test_host_routes_map_to_distinct_platform_codes_by_operation(): void
    {
        Artisan::call('production:rbac');
        $actual = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($this->rbacArgs($route) as $arg) {
                if (PlatformPermissions::isPlatformCode($arg)) {
                    $actual[implode('|', array_values(array_diff($route->methods(), ['HEAD']))) . ' ' . $route->uri()] = $arg;
                }
            }
        }
        ksort($actual);
        $expected = self::PLATFORM_ROUTES;
        ksort($expected);
        $this->assertSame($expected, $actual, 'platform route mapping drifted from the approved H2a table (102 §3)');

        // Category rules: the backup export has its own code; destructive maintenance never shares a code with reads.
        $this->assertSame(['POST admin/maintenance/backup-database'], array_keys(array_filter($actual, fn ($c) => $c === PlatformPermissions::BACKUP_EXECUTE)));
        foreach (['POST admin/maintenance/database', 'POST admin/maintenance/cleanup-logs', 'POST api/legacy-routes/cleanup'] as $destructive) {
            $this->assertSame(PlatformPermissions::MAINTENANCE_EXECUTE, $actual[$destructive]);
        }
        foreach ($actual as $key => $code) {
            if (str_starts_with($key, 'GET ') && $key !== 'GET api/websocket/test') {
                $this->assertSame(PlatformPermissions::OPS_VIEW, $code, "{$key}: a read must need only platform.ops.view");
            }
        }
    }

    /** A holder of ONLY platform.ops.view reaches read routes and nothing that mutates, broadcasts, maintains or exports. */
    public function test_read_only_platform_grant_does_not_open_mutating_destructive_or_export_routes(): void
    {
        Artisan::call('production:rbac');
        $tenant = Tenant::factory()->create();
        $viewer = $this->userWith($tenant, [$this->roleWith('o17h_platform_viewer_fixture', [PlatformPermissions::OPS_VIEW])]);
        // H2b (D6): platform authority also needs an ACTIVE operator ledger row (test fixture standing in for server provisioning).
        DB::table('platform_operators')->insert(['id' => (string) \Illuminate\Support\Str::ulid(), 'user_id' => (string) $viewer->id, 'granted_by_actor' => 'test-fixture', 'reason' => 'o17h test', 'approval_reference' => 'test', 'granted_at' => now(), 'active_key' => (string) $viewer->id, 'created_at' => now(), 'updated_at' => now()]);

        $wrong = [];
        foreach ($this->platformRoutes() as [$route, $args]) {
            $expectedPass = $args === [PlatformPermissions::OPS_VIEW];
            if ($this->middlewarePasses($viewer, $route, $args) !== $expectedPass) {
                $wrong[] = $route->uri() . ' [' . implode(',', $args) . ']';
            }
        }
        $this->assertSame(0, count($wrong), 'read-only platform grant decided wrongly on: ' . implode('; ', $wrong));
    }

    /** Ordinary production roles and a tenant role administrator pass no platform gate. */
    public function test_ordinary_staff_and_tenant_role_admins_pass_no_platform_gate(): void
    {
        Artisan::call('production:rbac');
        $tenant = Tenant::factory()->create();
        $actors = [];
        foreach (['Project Manager', 'Project Member', 'Finance', 'site_engineer'] as $name) {
            $actors[$name] = $this->userWith($tenant, [Role::query()->where('name', $name)->firstOrFail()]);
        }
        $actors['tenant role admin'] = $this->userWith($tenant, [$this->roleWith('o17h_tenant_rbac_admin', $this->rbacAdminCodes(), (string) $tenant->id)]);

        $passed = [];
        foreach ($actors as $label => $user) {
            foreach ($this->platformRoutes() as [$route, $args]) {
                if ($this->middlewarePasses($user, $route, $args)) {
                    $passed[] = "{$label}: " . $route->uri();
                }
            }
        }
        $this->assertSame(0, count($passed), 'platform gate passed by: ' . implode('; ', array_slice($passed, 0, 5)));
    }

    // ------------------------------------------------------------------ D6: no HTTP create / attach / assign path

    /**
     * Neither the tenant Owner (which still passes every rbac gate through the legacy short-circuit) nor a tenant role
     * administrator can create, edit, delete, attach (sync or CSV import) or assign platform authority over HTTP.
     */
    public function test_owner_and_tenant_role_admin_cannot_create_attach_or_assign_platform_authority(): void
    {
        Artisan::call('production:rbac');
        $tenant = Tenant::factory()->create();
        $owner = $this->userWith($tenant, [Role::query()->where('name', 'super_admin')->firstOrFail()]);
        $roleAdmin = $this->userWith($tenant, [$this->roleWith('o17h_tenant_rbac_admin', $this->rbacAdminCodes(), (string) $tenant->id)]);
        // A server-side fixture standing in for an (H2b) Platform Operator role: global system role holding a platform code.
        $operatorRole = Role::query()->create(['name' => 'o17h_platform_operator_fixture', 'scope' => 'system', 'is_active' => true]);
        $operatorRole->permissions()->sync(Permission::query()->where('code', PlatformPermissions::OPS_VIEW)->pluck('id')->all());
        $platformPermission = Permission::query()->where('code', PlatformPermissions::MAINTENANCE_EXECUTE)->firstOrFail();

        foreach (['owner' => $owner, 'tenant role admin' => $roleAdmin] as $label => $actor) {
            $target = $this->roleWith('o17h_target_' . str_replace(' ', '_', $label), ['task.view'], (string) $tenant->id);
            $victim = $this->userWith($tenant, []);
            $before = $this->snapshot();
            Sanctum::actingAs($actor, ['*']);

            $sync = $this->postJson("/api/v1/rbac/roles/{$target->id}/permissions", ['permission_codes' => ['task.view', PlatformPermissions::OPS_VIEW]])->status();
            $create = $this->postJson('/api/v1/rbac/permissions', ['module' => 'platform', 'action' => 'escalate', 'description' => 'o17h'])->status();
            $edit = $this->putJson("/api/v1/rbac/permissions/{$platformPermission->id}", ['description' => 'tampered'])->status();
            $delete = $this->deleteJson("/api/v1/rbac/permissions/{$platformPermission->id}")->status();
            $csv = UploadedFile::fake()->createWithContent('matrix.csv', "role_name,module,action,permission_code,allow\n{$target->name},platform,ops.view,platform.ops.view,true\n");
            $this->post('/api/v1/rbac/permission-matrix/import', ['csv_file' => $csv], ['Accept' => 'application/json']);
            $assign = $this->postJson("/api/v1/rbac/assignments/users/{$victim->id}/roles", ['role_id' => (string) $operatorRole->id])->status();
            $selfAssign = $this->postJson("/api/v1/rbac/assignments/users/{$actor->id}/roles", ['role_id' => (string) $operatorRole->id])->status();
            $this->postJson('/api/v1/rbac/bulk-assign-roles', ['user_ids' => [(string) $victim->id, (string) $actor->id], 'role_ids' => [(string) $operatorRole->id], 'scope' => 'system']);

            $this->assertSame(422, $sync, "{$label}: attaching platform.* to a tenant role must be refused");
            $this->assertNotContains($create, [200, 201], "{$label}: creating a platform.* permission must be refused (HTTP {$create})");
            $this->assertSame(422, $edit, "{$label}: editing a platform permission must be refused");
            $this->assertSame(422, $delete, "{$label}: deleting a platform permission must be refused");
            $this->assertNotContains($assign, [200, 201], "{$label}: assigning a platform-holding role must be refused (HTTP {$assign})");
            $this->assertNotContains($selfAssign, [200, 201], "{$label}: self-assigning a platform-holding role must be refused (HTTP {$selfAssign})");
            $this->assertSame($before, $this->snapshot(), "{$label}: nothing may persist");
            $this->assertFalse($target->fresh()->permissions()->where('code', 'like', 'platform.%')->exists());
            $this->assertSame('Platform: run destructive host maintenance (database, logs)', Permission::query()->whereKey($platformPermission->id)->value('description'));
            $this->assertSame(0, UserRole::query()->where('role_id', $operatorRole->id)->count());
        }
    }

    // ------------------------------------------------------------------ D2: retired routes are gone, with no replacement

    public function test_retired_mock_routes_are_gone_and_not_reachable_elsewhere(): void
    {
        $routes = Route::getRoutes();
        $actions = [];
        foreach ($routes as $route) {
            $actions[] = $route->getActionName();
        }
        foreach (self::RETIRED as [$method, $uri, $action]) {
            try {
                $match = $routes->match(Request::create('/' . $uri, $method));
                $this->fail("{$method} {$uri} is still routed (to {$match->getActionName()})");
            } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException | \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
                // retired as intended
            }
            if ($action !== 'Closure') {
                $this->assertNotContains($action, $actions, "{$action} must not be reachable through another route");
            }
        }
    }

    // ------------------------------------------------------------------ D3: explicit, self-scoped notification grant

    public function test_owner_holds_explicit_self_scoped_notification_grant_and_nothing_else_new(): void
    {
        Artisan::call('production:rbac');
        $tenant = Tenant::factory()->create();
        $ownerRole = Role::query()->where('name', 'super_admin')->firstOrFail();
        $this->assertTrue($ownerRole->permissions()->where('code', 'notification.manage_rules')->exists());

        // Grant-based (not name-based): an Owner-equivalent role (identical grants, another name) passes the route's PERMISSION
        // gate; a role without the grant does not. (The same route also carries the parameterless rbac gate, which accepts only
        // legacy role names — finding O8-1, out of scope — so the HTTP call below uses the real Owner.)
        $clone = Role::query()->create(['name' => 'o17h_owner_equivalent_b', 'scope' => 'custom', 'is_active' => true, 'tenant_id' => (string) $tenant->id]);
        $clone->permissions()->sync($ownerRole->permissions()->pluck('permissions.id')->all());
        $route = Route::getRoutes()->match(Request::create('/api/v1/settings/notifications', 'PATCH'));
        $this->assertContains('notification.manage_rules', $this->rbacArgs($route));
        $this->assertTrue($this->middlewarePasses($this->userWith($tenant, [$clone]), $route, ['notification.manage_rules']));
        $this->assertFalse($this->middlewarePasses($this->userWith($tenant, [$this->roleWith('o17h_no_rules', ['task.view'], (string) $tenant->id)]), $route, ['notification.manage_rules']));

        // Self-scoped: the Owner changes only its OWN settings (the endpoint takes no target user).
        $me = $this->userWith($tenant, [$ownerRole]);
        $other = $this->userWith($tenant, [$ownerRole]);
        Sanctum::actingAs($me, ['*']);
        $this->patchJson('/api/v1/settings/notifications', ['email_notifications' => false])->assertOk();
        $this->assertFalse((bool) data_get($me->fresh()->preferences, 'notifications.email_notifications'));
        $this->assertNull(data_get($other->fresh()->preferences, 'notifications'), 'only the caller\'s own settings may change');
        // A smuggled target id is ignored (pre-existing: the endpoint's unexpected-key check is discarded by a re-run of
        // fails(); recorded in 102, not fixed here) — the other user's settings must stay untouched.
        $this->patchJson('/api/v1/settings/notifications', ['user_id' => (string) $other->id, 'push_notifications' => false]);
        $this->assertNull(data_get($other->fresh()->preferences, 'notifications'), 'a target user id must never redirect the write');
        $this->assertFalse((bool) data_get($me->fresh()->preferences, 'notifications.push_notifications'));
    }

    // ------------------------------------------------------------------ helpers

    /** @return list<string> */
    private function rbacAdminCodes(): array
    {
        return ['role.view', 'role.create', 'role.edit', 'role.assign', 'permission.create', 'permission.edit', 'permission.delete', 'permission.import'];
    }

    /** @return list<string> */
    private function rbacArgs(RoutingRoute $route): array
    {
        $args = [];
        foreach ($route->gatherMiddleware() as $m) {
            if (is_string($m) && (str_starts_with($m, 'rbac:') || str_starts_with($m, RoleBasedAccessControlMiddleware::class . ':'))) {
                $args[] = explode(',', explode(':', $m, 2)[1])[0];
            }
        }

        return array_values(array_unique(array_filter($args)));
    }

    /** @return list<array{0: RoutingRoute, 1: list<string>}> */
    private function platformRoutes(): array
    {
        $out = [];
        foreach (Route::getRoutes() as $route) {
            $args = $this->rbacArgs($route);
            if (PlatformPermissions::containsPlatformCode($args)) {
                $out[] = [$route, $args];
            }
        }
        $this->assertCount(62, $out);

        return $out;
    }

    /** @param list<string> $args */
    private function middlewarePasses(User $user, RoutingRoute $route, array $args): bool
    {
        $this->actingAs($user);
        $middleware = new RoleBasedAccessControlMiddleware();
        foreach ($args as $arg) {
            $request = Request::create('/' . ltrim($route->uri(), '/'), $route->methods()[0], [], [], [], ['HTTP_ACCEPT' => 'application/json']);
            $request->attributes->set('tenant_id', (string) $user->tenant_id);
            if ($middleware->handle($request, static fn (): Response => new Response('passed', 299), $arg)->getStatusCode() !== 299) {
                return false;
            }
        }

        return true;
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
            'permissions' => DB::table('permissions')->count(),
            'platform_grants' => DB::table('role_permissions')->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')->where('permissions.code', 'like', 'platform.%')->count(),
            'user_roles' => DB::table('user_roles')->count(),
            'system_user_roles' => DB::table('system_user_roles')->count(),
        ];
    }
}
