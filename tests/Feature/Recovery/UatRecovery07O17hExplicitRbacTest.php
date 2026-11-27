<?php

declare(strict_types=1);

namespace Tests\Feature\Recovery;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\ChangeRequest;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\SidebarConfig;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * UAT RECOVERY 07 — OWN-2026-024 (O-17h) H1: explicit, revocable RBAC instead of implicit `super_admin` authority.
 *
 * Owner decision 2026-11-24: O17H_OPTION_B_APPROVED, authority "H0 PREPARATION + H1 TESTS ONLY". These tests describe the
 * TARGET contract. Tests marked RED fail on purpose today and document the defect (docs/audits/UAT-RECOVERY-01/101 §4); they
 * become green only through the separately approved H2/H3/H4 slices. Nothing here changes runtime code.
 *
 * Fixture = production:rbac on a fresh database (the real production grant set: super_admin holds 222 codes). Test-owned
 * roles only; seeded grants are never edited except where a test deliberately revokes one to prove revocability.
 * "zero-grant Owner" = the single global `super_admin` role (roles.name is UNIQUE) with its grants detached inside the test.
 */
#[Group('uat-recovery-07-o17h')]
class UatRecovery07O17hExplicitRbacTest extends TestCase
{
    use RefreshDatabase;

    /** Legacy role-name arguments the middleware treats as roles, not permissions (O-8 territory, out of O-17h scope). */
    private const ROLE_ARGS = ['project_manager', 'team_member', 'client', 'viewer'];

    private Tenant $a;

    private Tenant $b;

    private Role $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        Artisan::call('production:rbac');
        $this->a = Tenant::factory()->create();
        $this->b = Tenant::factory()->create();
        $this->owner = Role::query()->where('name', 'super_admin')->firstOrFail();
    }

    // ---------------------------------------------------------------- 1. ordinary permission gates (GREEN since H3)

    /** RED (M1) at H1–H2b; GREEN since H3 (short-circuit removed): a zero-grant super_admin role passes no rbac:<permission> gate. */
    public function test_zero_grant_owner_is_denied_by_every_permission_gate(): void
    {
        $actor = $this->userWith($this->a, [$this->owner]);
        $this->detachGrants($this->owner);

        $passed = [];
        foreach ($this->permissionGatedRoutes() as [$route, $args]) {
            if ($this->middlewarePasses($actor, $route, $args)) {
                $passed[] = implode('|', $route->methods()) . ' ' . $route->uri() . ' [' . implode(',', $args) . ']';
            }
        }

        $this->assertSame(0, count($passed), sprintf('%d permission-gated routes admit a zero-grant super_admin, e.g. %s', count($passed), implode('; ', array_slice($passed, 0, 5))));
    }

    /**
     * Class X: every rbac:<arg> must name a real permission code. RED at H1 (`rbac:admin` on 80 routes named none); GREEN since
     * H2a re-gated those routes on the platform.* catalogue (OWN-2026-024 Gate 2 D1) — released from the RED quarantine.
     */
    public function test_every_rbac_argument_is_a_real_permission_code(): void
    {
        $codes = Permission::query()->pluck('code')->all();
        $unknown = [];
        foreach ($this->permissionGatedRoutes() as [$route, $args]) {
            foreach ($args as $arg) {
                if (!in_array($arg, $codes, true)) {
                    $unknown[$arg][] = implode('|', $route->methods()) . ' ' . $route->uri();
                }
            }
        }

        $this->assertSame(0, count($unknown), 'rbac arguments that are not permission codes (only super_admin can pass them): ' . json_encode(array_map('count', $unknown)));
    }

    // ---------------------------------------------------------------- 2. host-level maintenance (GREEN since H3)

    /**
     * RED at H1–H2b, GREEN since H3 (released from the quarantine): a fully provisioned tenant Owner must not reach host-wide operations by role name. Since H2a these routes
     * are gated on platform.* codes that NO role holds — yet the legacy super_admin middleware short-circuit still admits the
     * Owner, so H2a alone does not close this (Gate 2: "H2a alone is not a security fix").
     */
    public function test_tenant_owner_cannot_reach_host_level_routes_by_role_name(): void
    {
        $owner = $this->userWith($this->a, [$this->owner]);

        $reached = [];
        $total = 0;
        foreach ($this->permissionGatedRoutes() as [$route, $args]) {
            if (!\App\Support\PlatformPermissions::containsPlatformCode($args)) {
                continue;
            }
            $total++;
            if ($this->middlewarePasses($owner, $route, $args)) {
                $reached[] = implode('|', $route->methods()) . ' ' . $route->uri();
            }
        }

        // 80 host routes at H1: 16 retired (D2), 2 legacy redirects moved to their destination's admin.company.manage, 62 platform.
        $this->assertSame(62, $total, 'host-level (platform.*) route inventory changed; reclassify (102) before editing this test');
        $this->assertSame(0, count($reached), count($reached) . ' host-level routes reachable by the tenant Owner role name, e.g. ' . implode('; ', array_slice($reached, 0, 5)));
    }

    // ---------------------------------------------------------------- 3. revocation (GREEN since H3, plus GREEN control)

    /** RED at H1–H2b; GREEN since H3: revoking a canonical grant from the Owner role removes the access it gave. */
    public function test_revoking_a_canonical_grant_from_the_owner_removes_access(): void
    {
        $owner = $this->userWith($this->a, [$this->owner]);
        Sanctum::actingAs($owner, ['*']);
        $this->getJson('/api/v1/rbac/roles')->assertOk();

        $this->revoke($this->owner, 'role.view');
        Sanctum::actingAs($owner->fresh(), ['*']);
        $this->getJson('/api/v1/rbac/roles')->assertForbidden();
    }

    /** GREEN guard: the same revocation works for an ordinary role (proves the test mechanics, not the defect). */
    public function test_revoking_a_canonical_grant_from_an_ordinary_role_removes_access(): void
    {
        $role = $this->roleWith('o17h_role_reader', ['role.view']);
        $user = $this->userWith($this->a, [$role]);
        Sanctum::actingAs($user, ['*']);
        $this->getJson('/api/v1/rbac/roles')->assertOk();

        $this->revoke($role, 'role.view');
        Sanctum::actingAs($user->fresh(), ['*']);
        $this->getJson('/api/v1/rbac/roles')->assertForbidden();
    }

    // ---------------------------------------------------------------- 4. role / permission management escalation (GREEN since H3)

    /** RED at H1–H2b; GREEN since H3: a zero-grant super_admin cannot assign roles, create roles or create permissions; nothing persists. */
    public function test_zero_grant_owner_cannot_manage_roles_or_permissions(): void
    {
        $actor = $this->userWith($this->a, [$this->owner]);
        $victim = $this->userWith($this->a, []);
        $this->detachGrants($this->owner);
        $before = $this->rbacSnapshot();

        Sanctum::actingAs($actor, ['*']);
        $statuses = [
            'assign Owner role' => $this->postJson("/api/v1/rbac/assignments/users/{$victim->id}/roles", ['role_id' => (string) $this->owner->id])->status(),
            'create role' => $this->postJson('/api/v1/rbac/roles', ['name' => 'o17h_escalation_role', 'scope' => 'custom'])->status(),
            'create permission' => $this->postJson('/api/v1/rbac/permissions', ['code' => 'o17h.escalation', 'name' => 'o17h.escalation', 'module' => 'o17h', 'action' => 'escalation'])->status(),
        ];

        $this->assertSame(['assign Owner role' => 403, 'create role' => 403, 'create permission' => 403], $statuses);
        $this->assertSame($before, $this->rbacSnapshot(), 'role/permission tables changed after denied requests');
    }

    /** RED (M1) at H1–H2b; GREEN since H3: a zero-grant super_admin cannot approve a change request (protected workflow); status unchanged. */
    public function test_zero_grant_owner_cannot_approve_a_change_request(): void
    {
        $actor = $this->userWith($this->a, [$this->owner]);
        $pm = $this->userWith($this->a, []);
        $project = $this->project($this->a, $pm);
        $cr = ChangeRequest::factory()->create(['tenant_id' => (string) $this->a->id, 'project_id' => (string) $project->id, 'status' => 'submitted', 'requested_by' => (string) $pm->id, 'assigned_to' => (string) $pm->id]);
        $this->detachGrants($this->owner);
        $audit = DB::table('audit_logs')->count();

        Sanctum::actingAs($actor, ['*']);
        $this->postJson("/api/zena/change-requests/{$cr->id}/approve", ['approval_notes' => 'o17h'])->assertForbidden();

        $this->assertSame('submitted', ChangeRequest::query()->whereKey($cr->id)->value('status'));
        $this->assertSame($audit, DB::table('audit_logs')->count());
    }

    // ---------------------------------------------------------------- 5. policies (GREEN since H4)

    /** RED (M3) at H1–H3; GREEN since H4: DocumentPolicy must not let a role name (or users.role) stand in for document grants. */
    public function test_document_policy_does_not_bypass_canonical_grants(): void
    {
        $owner = $this->userWith($this->a, [$this->owner]);
        $legacy = $this->userWith($this->a, [], 'super_admin');
        $this->detachGrants($this->owner);
        $doc = $this->document($this->a, $owner);

        $allowed = [];
        foreach (['owner role' => $owner, 'legacy users.role' => $legacy] as $label => $user) {
            foreach (['viewAny' => Document::class, 'create' => Document::class, 'view' => $doc, 'update' => $doc, 'delete' => $doc, 'restore' => $doc, 'forceDelete' => $doc, 'download' => $doc] as $ability => $subject) {
                if (Gate::forUser($user)->allows($ability, $subject)) {
                    $allowed[] = "{$label}:{$ability}";
                }
            }
        }

        $this->assertSame(0, count($allowed), 'DocumentPolicy abilities granted by role name alone: ' . implode(', ', $allowed));
    }

    /** RED (M3) at H1–H3; GREEN since H4: SidebarConfigPolicy::before() must not grant every ability to the role name super_admin. */
    public function test_sidebar_config_policy_does_not_bypass_canonical_grants(): void
    {
        $owner = $this->userWith($this->a, [$this->owner]);
        $this->detachGrants($this->owner);

        $allowed = array_values(array_filter(['viewAny', 'create', 'manage', 'clone', 'reset'], fn (string $ability): bool => Gate::forUser($owner)->allows($ability, SidebarConfig::class)));

        $this->assertSame(0, count($allowed), 'SidebarConfigPolicy abilities granted by role name alone: ' . implode(', ', $allowed));
    }

    /** GREEN guard: a canonical document.update holder in the same tenant keeps update; another tenant does not. */
    public function test_document_policy_canonical_grant_still_works_and_stays_tenant_bound(): void
    {
        $editor = $this->userWith($this->a, [$this->roleWith('o17h_doc_editor', ['document.update', 'document.view'])]);
        $outsider = $this->userWith($this->b, [$this->roleWith('o17h_doc_editor_b', ['document.update', 'document.view'])]);
        $doc = $this->document($this->a, $editor);

        $this->assertTrue(Gate::forUser($editor)->allows('update', $doc));
        $this->assertFalse(Gate::forUser($outsider)->allows('update', $doc));
    }

    // ---------------------------------------------------------------- 6. tenant isolation (GREEN guard)

    /** GREEN guard: neither the provisioned nor the zero-grant Owner reaches another tenant's objects. */
    public function test_owner_cannot_reach_another_tenants_objects(): void
    {
        $ownerA = $this->userWith($this->a, [$this->owner]);
        $userB = $this->userWith($this->b, []);
        $pB = $this->project($this->b, $userB);
        $cB = Contract::factory()->create(['tenant_id' => (string) $this->b->id, 'project_id' => (string) $pB->id, 'created_by' => (string) $userB->id, 'title' => 'orig']);

        foreach (['provisioned' => false, 'zero-grant' => true] as $label => $detach) {
            if ($detach) {
                $this->detachGrants($this->owner);
            }
            Sanctum::actingAs($ownerA->fresh(), ['*']);
            $this->assertContains($this->getJson("/api/v1/projects/{$pB->id}/contracts/{$cB->id}")->status(), [403, 404], "{$label}: read");
            $this->assertContains($this->putJson("/api/v1/projects/{$pB->id}/contracts/{$cB->id}", ['title' => 'x'])->status(), [403, 404], "{$label}: update");
            $this->assertContains($this->getJson("/api/v1/rbac/users/{$userB->id}/effective-permissions")->status(), [403, 404], "{$label}: user permissions");
            $this->assertSame(403, $this->withHeaders(['X-Tenant-ID' => (string) $this->b->id])->getJson('/api/zena/crm/leads')->status(), "{$label}: forged tenant header");
            $this->flushHeaders();
        }
        $this->assertSame('orig', Contract::query()->withoutGlobalScopes()->whereKey($cB->id)->value('title'));
    }

    // ---------------------------------------------------------------- 7. granted Owner permissions keep working (GREEN guard)

    /**
     * GREEN guard: an Owner-equivalent role that holds exactly the production super_admin grants but is NOT named
     * super_admin passes every route gate whose code it holds. This is what the Owner keeps after M1 is removed.
     */
    public function test_granted_owner_permissions_work_without_the_role_name(): void
    {
        $clone = Role::query()->create(['name' => 'o17h_owner_equivalent', 'scope' => 'custom', 'is_active' => true, 'tenant_id' => (string) $this->a->id]);
        $clone->permissions()->sync($this->owner->permissions()->pluck('permissions.id')->all());
        $user = $this->userWith($this->a, [$clone]);
        $held = $this->owner->permissions()->pluck('code')->all();

        $denied = [];
        $checked = 0;
        foreach ($this->permissionGatedRoutes() as [$route, $args]) {
            if (array_diff($args, $held) !== []) {
                continue;
            }
            $checked++;
            if (!$this->middlewarePasses($user, $route, $args)) {
                $denied[] = implode('|', $route->methods()) . ' ' . $route->uri();
            }
        }

        $this->assertGreaterThan(700, $checked);
        $this->assertSame(0, count($denied), 'granted codes not honoured without the super_admin name: ' . implode('; ', array_slice($denied, 0, 5)));
    }

    /** GREEN guard: the provisioned Owner's grant-based HTTP workflows (read, create, approve) succeed today and must after H3. */
    public function test_provisioned_owner_keeps_granted_workflows(): void
    {
        $owner = $this->userWith($this->a, [$this->owner]);
        $pm = $this->userWith($this->a, []);
        $project = $this->project($this->a, $pm);
        $cr = ChangeRequest::factory()->create(['tenant_id' => (string) $this->a->id, 'project_id' => (string) $project->id, 'status' => 'submitted', 'requested_by' => (string) $pm->id, 'assigned_to' => (string) $pm->id]);

        Sanctum::actingAs($owner, ['*']);
        $this->getJson("/api/v1/projects/{$project->id}/contracts")->assertOk();
        $this->getJson('/api/v1/rbac/roles')->assertOk();
        $this->postJson('/api/zena/crm/leads', ['contact_hint' => 'o17h guard lead'])->assertCreated();
        $this->postJson("/api/zena/change-requests/{$cr->id}/approve", ['approval_notes' => 'ok'])->assertOk();
        $this->assertSame('approved', ChangeRequest::query()->whereKey($cr->id)->value('status'));
    }

    /** GREEN guard (no access expansion): the production grant set H2 may only extend by Owner-approved codes. */
    public function test_production_role_grant_counts_are_pinned(): void
    {
        $counts = [];
        foreach (['super_admin', 'Project Manager', 'Project Member', 'Finance', 'site_engineer'] as $name) {
            $counts[$name] = Role::query()->where('name', $name)->firstOrFail()->permissions()->count();
        }

        // H2a (Owner Gate 2, D3): super_admin 222 -> 223 = + notification.manage_rules, the only approved expansion.
        $this->assertSame(['super_admin' => 223, 'Project Manager' => 67, 'Project Member' => 27, 'Finance' => 15, 'site_engineer' => 18], $counts);
        $this->assertTrue(Role::query()->where('name', 'super_admin')->firstOrFail()->permissions()->where('code', 'notification.manage_rules')->exists());
        foreach (['task.assign', 'change_request.create', 'component.create', 'component.read', 'component.update'] as $denied) {
            $this->assertFalse(Role::query()->where('name', 'super_admin')->firstOrFail()->permissions()->where('code', $denied)->exists(), "D3: {$denied} must not be granted to the Owner");
        }
    }

    // ---------------------------------------------------------------- 8. tenant role administration cannot self-grant host level (RED?)

    /**
     * A tenant-level role administrator (role.view + role.edit + role.assign on a custom tenant role, NOT the Owner) must not
     * (a) put a host/platform-level code onto its own role, nor (b) assign itself the global Owner role.
     * RED at H1/H2a (RBAC-ESC-1); GREEN since H2b (protected codes + D5 Owner appointment) — released from the RED quarantine.
     */
    public function test_tenant_role_admin_cannot_self_grant_host_or_owner_authority(): void
    {
        $adminRole = $this->roleWith('o17h_tenant_role_admin', ['role.view', 'role.edit', 'role.assign'], (string) $this->a->id);
        $admin = $this->userWith($this->a, [$adminRole]);
        $hostCodes = ['system.admin', 'system.settings'];
        Sanctum::actingAs($admin, ['*']);

        $sync = $this->postJson("/api/v1/rbac/roles/{$adminRole->id}/permissions", ['permission_codes' => array_merge(['role.view', 'role.edit', 'role.assign'], $hostCodes)])->status();
        $selfOwner = $this->postJson("/api/v1/rbac/assignments/users/{$admin->id}/roles", ['role_id' => (string) $this->owner->id])->status();

        $held = $adminRole->fresh()->permissions()->pluck('code')->all();
        $breaches = [];
        if (array_intersect($hostCodes, $held) !== []) {
            $breaches[] = 'self-granted host-level code(s) ' . implode(',', array_intersect($hostCodes, $held)) . " (HTTP {$sync})";
        }
        if (UserRole::query()->where('user_id', $admin->id)->where('role_id', $this->owner->id)->exists()) {
            $breaches[] = "assigned itself the global Owner role (HTTP {$selfOwner})";
        }
        $this->assertSame(0, count($breaches), 'tenant role administrator: ' . implode('; ', $breaches));
    }

    // ---------------------------------------------------------------- 9. re-seeding must not restore a revoked grant (RED)

    /**
     * A deliberately revoked security-sensitive Owner grant must stay revoked after production:rbac runs again. RED at H1/H2a;
     * GREEN since H2b (D4). H2b amendment (Owner review requested): the deliberate revocation is now made through the governed
     * mechanism (rbac:permission revoke, durable record + audit) instead of a bare pivot detach — a bare detach is not a
     * deliberate revocation under D4 and is still restored by seeding by design. The assertion is unchanged.
     */
    public function test_reseeding_does_not_restore_a_deliberately_revoked_grant(): void
    {
        $approver = $this->userWith($this->a, [$this->owner]);
        $this->artisan('rbac:permission', ['action' => 'revoke', '--role' => 'super_admin', '--code' => 'role.assign', '--reason' => 'deliberate revocation (test)', '--approved-by' => $approver->email, '--yes' => true])->assertExitCode(0);
        $this->assertFalse($this->owner->permissions()->where('code', 'role.assign')->exists());

        Artisan::call('production:rbac');

        $this->assertFalse($this->owner->fresh()->permissions()->where('code', 'role.assign')->exists(), 'production:rbac silently restored the revoked role.assign grant');
    }

    // ---------------------------------------------------------------- helpers

    /** @return list<array{0: RoutingRoute, 1: list<string>}> routes with at least one rbac:<permission> argument */
    private function permissionGatedRoutes(): array
    {
        $out = [];
        foreach (Route::getRoutes() as $route) {
            $args = [];
            foreach ($route->gatherMiddleware() as $m) {
                if (!is_string($m)) {
                    continue;
                }
                if (str_starts_with($m, 'rbac:') || str_starts_with($m, RoleBasedAccessControlMiddleware::class . ':')) {
                    $arg = explode(',', explode(':', $m, 2)[1])[0];
                    if ($arg !== '' && !in_array($arg, self::ROLE_ARGS, true)) {
                        $args[] = $arg;
                    }
                }
            }
            if ($args !== []) {
                $out[] = [$route, array_values(array_unique($args))];
            }
        }

        return $out;
    }

    /** Runs only the RBAC middleware (no controller) for every rbac argument of the route; true when all pass. */
    private function middlewarePasses(User $user, RoutingRoute $route, array $args): bool
    {
        $this->actingAs($user);
        $middleware = new RoleBasedAccessControlMiddleware();
        foreach ($args as $arg) {
            $request = Request::create('/' . ltrim($route->uri(), '/'), $route->methods()[0], [], [], [], ['HTTP_ACCEPT' => 'application/json']);
            $request->attributes->set('tenant_id', (string) $user->tenant_id);
            $response = $middleware->handle($request, static fn (): Response => new Response('passed', 299), $arg);
            if ($response->getStatusCode() !== 299) {
                return false;
            }
        }

        return true;
    }

    /** @param list<Role> $roles */
    private function userWith(Tenant $tenant, array $roles, ?string $legacyRole = null): User
    {
        $user = User::factory()->create(['tenant_id' => (string) $tenant->id, 'is_active' => true]);
        if ($legacyRole !== null) {
            $user->forceFill(['role' => $legacyRole])->save();
        }
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

    private function detachGrants(Role $role): void
    {
        DB::table('role_permissions')->where('role_id', (string) $role->id)->delete();
        $this->assertSame(0, $role->permissions()->count());
    }

    private function revoke(Role $role, string $code): void
    {
        $role->permissions()->detach(Permission::query()->where('code', $code)->pluck('id')->all());
    }

    private function project(Tenant $tenant, User $pm): Project
    {
        return Project::factory()->create(['tenant_id' => (string) $tenant->id, 'created_by' => (string) $pm->id, 'pm_id' => (string) $pm->id]);
    }

    private function document(Tenant $tenant, User $uploader): Document
    {
        $pm = $this->userWith($tenant, []);

        return Document::factory()->create(['tenant_id' => (string) $tenant->id, 'project_id' => (string) $this->project($tenant, $pm)->id, 'uploaded_by' => (string) $uploader->id]);
    }

    /** @return array<string, int> */
    private function rbacSnapshot(): array
    {
        return [
            'roles' => DB::table('roles')->count(),
            'permissions' => DB::table('permissions')->count(),
            'role_permissions' => DB::table('role_permissions')->count(),
            'user_roles' => DB::table('user_roles')->count(),
            'system_user_roles' => DB::table('system_user_roles')->count(),
        ];
    }
}
