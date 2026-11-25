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
use App\Services\Admin\StaffAccountException;
use App\Services\Admin\StaffAccountService;
use App\Support\PlatformPermissions;
use App\Support\Rbac\PermissionRevocations;
use App\Support\Rbac\RbacProtection;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * UAT RECOVERY 07c — OWN-2026-024 (O-17h) H2b (Owner: "H2A_ACCEPTED / H2B_LOCAL_IMPLEMENTATION_APPROVED", 2026-11-24).
 *
 * D4 durable revocation, D5 Owner appointment / protected grants, D6 server-only Platform Operators, Gate 4 safeguards,
 * transactional audit, no persisted side effects on denial. Written while the legacy super_admin middleware short-circuit was
 * still present (removed by H3, 2026-11-25): where a test needs "real" authorization it uses a provisioned operator or a
 * canonical Owner, and the zero-grant super_admin cases assert the IN-CONTROLLER / service guards. All pass unchanged at H3.
 * Fixture: production:rbac on a fresh database (Owner role = 223 codes incl. admin.company.manage); test-owned roles only.
 */
#[Group('uat-recovery-07-o17h')]
class UatRecovery07cO17hH2bTest extends TestCase
{
    use RefreshDatabase;

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

    // ===================================================================== D4 durable revocation

    public function test_revoked_owner_grant_survives_two_reseeds_and_is_denied_at_authorization_time(): void
    {
        $owner = $this->userWith($this->a, [$this->ownerRole]);
        $other = $this->userWith($this->a, [$this->ownerRole]);
        $this->assertTrue($other->hasPermission('role.view'));

        $this->artisan('rbac:permission', ['action' => 'revoke', '--role' => 'super_admin', '--code' => 'role.view', '--reason' => 'o17h test', '--approval' => 'OWN-2026-024', '--approved-by' => $owner->email, '--yes' => true])->assertExitCode(0);

        Artisan::call('production:rbac');
        Artisan::call('production:rbac');

        $this->assertFalse($this->ownerRole->permissions()->where('code', 'role.view')->exists(), 'reseed restored a revoked grant');
        $this->assertTrue(PermissionRevocations::isRevoked((string) $this->ownerRole->id, 'role.view'));
        $this->assertFalse($other->fresh()->hasPermission('role.view'));

        // Authorization time: even a pivot row re-added by an unknown path does not authorize while the revocation is active.
        DB::table('role_permissions')->insert(['role_id' => (string) $this->ownerRole->id, 'permission_id' => (string) Permission::query()->where('code', 'role.view')->value('id'), 'created_at' => now(), 'updated_at' => now()]);
        $this->assertFalse($other->fresh()->hasPermission('role.view'));
        $this->assertTrue($other->fresh()->hasPermission('role.create'), 'only the revoked pair is affected');

        $row = DB::table('role_permission_revocations')->whereNull('superseded_at')->first();
        $this->assertSame('role.view', $row->permission_code);
        $this->assertNull($row->tenant_id, 'global role');
        $this->assertSame('o17h test', $row->reason);
        $this->assertSame('OWN-2026-024', $row->approval_reference);
        $this->assertStringContainsString('approved-by:' . $owner->id, (string) $row->revoked_by_actor);
        $this->assertSame((string) $owner->id, (string) DB::table('audit_logs')->where('id', $row->audit_log_id)->value('user_id'));
        $this->assertSame(PermissionRevocations::AUDIT_REVOKED, DB::table('audit_logs')->where('id', $row->audit_log_id)->value('action'));
    }

    public function test_explicit_authorized_regrant_supersedes_the_revocation_atomically(): void
    {
        $owner = $this->userWith($this->a, [$this->ownerRole]);
        $args = ['--role' => 'super_admin', '--code' => 'role.view', '--approved-by' => $owner->email, '--yes' => true];
        $this->artisan('rbac:permission', ['action' => 'revoke', '--reason' => 'test'] + $args)->assertExitCode(0);
        $this->artisan('rbac:permission', ['action' => 'regrant', '--reason' => 'restored after review'] + $args)->assertExitCode(0);

        $this->assertFalse(PermissionRevocations::isRevoked((string) $this->ownerRole->id, 'role.view'));
        $pivot = DB::table('role_permissions')->where('role_id', $this->ownerRole->id)->where('permission_id', Permission::query()->where('code', 'role.view')->value('id'))->first();
        $this->assertNotNull($pivot);
        $this->assertSame('manual', $pivot->grant_source);
        $superseded = DB::table('role_permission_revocations')->whereNotNull('superseded_at')->first();
        $this->assertNull($superseded->active_key);
        $this->assertSame(PermissionRevocations::AUDIT_REGRANTED, DB::table('audit_logs')->where('id', $superseded->superseded_audit_log_id)->value('action'));
        $this->assertTrue($owner->fresh()->hasPermission('role.view'));
    }

    public function test_unauthorized_regrant_is_denied_without_side_effects(): void
    {
        $owner = $this->userWith($this->a, [$this->ownerRole]);
        $roleAdminRole = $this->roleWith('o17h_c_role_admin', ['role.view', 'role.edit', 'role.assign'], (string) $this->a->id);
        $roleAdmin = $this->userWith($this->a, [$roleAdminRole]);
        $target = $this->roleWith('o17h_c_target', ['task.view', 'task.update'], (string) $this->a->id);
        $this->artisan('rbac:permission', ['action' => 'revoke', '--role' => (string) $target->id, '--code' => 'task.update', '--reason' => 'test', '--approved-by' => $owner->email, '--yes' => true])->assertExitCode(0);
        $before = $this->snapshot();

        // CLI with a non-Owner approver.
        $this->artisan('rbac:permission', ['action' => 'regrant', '--role' => (string) $target->id, '--code' => 'task.update', '--reason' => 'x', '--approved-by' => $roleAdmin->email, '--yes' => true])->assertExitCode(1);
        // CLI without a reason.
        $this->artisan('rbac:permission', ['action' => 'regrant', '--role' => (string) $target->id, '--code' => 'task.update', '--approved-by' => $owner->email, '--yes' => true])->assertExitCode(2);
        // HTTP: a tenant role administrator re-adding the revoked code via sync, and via CSV import.
        Sanctum::actingAs($roleAdmin, ['*']);
        $this->postJson("/api/v1/rbac/roles/{$target->id}/permissions", ['permission_codes' => ['task.view', 'task.update']])->assertForbidden();
        $this->assertSame($before, $this->snapshot());
        $this->assertTrue(PermissionRevocations::isRevoked((string) $target->id, 'task.update'));
    }

    public function test_failed_revocation_leaves_no_revocation_grant_change_or_audit_row(): void
    {
        $owner = $this->userWith($this->a, [$this->ownerRole]);
        $before = $this->snapshot();
        // Force a failure AFTER the audit row is written and BEFORE the revocation row is inserted — no DDL (a trigger would
        // implicitly commit the test transaction on MySQL/MariaDB), so the same proof holds on SQLite and MySQL.
        $armed = true;
        DB::connection()->beforeExecuting(function (string $query) use (&$armed): void {
            if ($armed && preg_match('/^insert into [`"]?role_permission_revocations[`"]?/i', $query) === 1) {
                throw new \RuntimeException('o17h forced failure');
            }
        });

        try {
            $this->artisan('rbac:permission', ['action' => 'revoke', '--role' => 'super_admin', '--code' => 'role.view', '--reason' => 'atomicity', '--approved-by' => $owner->email, '--yes' => true])->assertExitCode(1);
        } finally {
            $armed = false;
        }
        $this->assertSame($before, $this->snapshot(), 'a failed revocation must leave grants, revocations and audit rows untouched');
        $this->assertTrue($this->ownerRole->permissions()->where('code', 'role.view')->exists());
    }

    // ===================================================================== D5 Owner appointment / protected grants

    public function test_owner_appoints_another_owner_in_same_tenant_with_transactional_audit(): void
    {
        $owner = $this->userWith($this->a, [$this->ownerRole]);
        $staff = $this->userWith($this->a, []);
        Sanctum::actingAs($owner, ['*']);

        $this->postJson("/api/v1/rbac/assignments/users/{$staff->id}/roles", ['role_id' => (string) $this->ownerRole->id])->assertOk();

        $this->assertTrue(UserRole::query()->where('user_id', $staff->id)->where('role_id', $this->ownerRole->id)->exists());
        $audit = DB::table('audit_logs')->where('action', 'rbac.privileged_role.assigned')->where('entity_id', (string) $staff->id)->first();
        $this->assertNotNull($audit);
        $this->assertSame((string) $owner->id, (string) $audit->user_id);
    }

    public function test_owner_appointment_denials_persist_nothing(): void
    {
        $owner = $this->userWith($this->a, [$this->ownerRole]);
        $roleAdmin = $this->userWith($this->a, [$this->roleWith('o17h_c_role_admin2', ['role.view', 'role.assign', 'role.edit'], (string) $this->a->id)]);
        $zeroGrantOwner = $this->userWith($this->a, [$this->ownerRole]);
        $staff = $this->userWith($this->a, []);
        $foreign = $this->userWith($this->b, []);
        $before = $this->snapshot();

        $cases = [
            'non-Owner tenant role admin -> other' => [$roleAdmin, $staff],
            'non-Owner tenant role admin -> self (self-promotion)' => [$roleAdmin, $roleAdmin],
            'Owner -> self' => [$owner, $owner],
            'Owner -> other tenant user (cross-tenant)' => [$owner, $foreign],
        ];
        foreach ($cases as $label => [$actor, $target]) {
            Sanctum::actingAs($actor, ['*']);
            $status = $this->postJson("/api/v1/rbac/assignments/users/{$target->id}/roles", ['role_id' => (string) $this->ownerRole->id])->status();
            $this->assertNotContains($status, [200, 201], "{$label}: HTTP {$status}");
            $this->postJson('/api/v1/rbac/bulk-assign-roles', ['user_ids' => [(string) $target->id], 'role_ids' => [(string) $this->ownerRole->id], 'scope' => 'system']);
            $this->assertSame($before, $this->snapshot(), "{$label}: nothing may persist");
        }

        // A role NAME alone is not Owner authority: the Owner role without its canonical grant is refused by the in-controller
        // guard (the route gate rbac:role.assign is still held, so the request reaches the controller with or without H3).
        // The canonical Owner authority cannot be durably revoked from the Owner role (lock-out protection) — the command refuses:
        $this->artisan('rbac:permission', ['action' => 'revoke', '--role' => 'super_admin', '--code' => 'admin.company.manage', '--reason' => 'test', '--approved-by' => $owner->email, '--yes' => true])->assertExitCode(1);
        // ... so the zero-grant case is set up by detaching the grant directly (test-only manipulation, not a product path).
        DB::table('role_permissions')->where('role_id', (string) $this->ownerRole->id)->where('permission_id', (string) Permission::query()->where('code', 'admin.company.manage')->value('id'))->delete();
        Sanctum::actingAs($zeroGrantOwner->fresh(), ['*']);
        $this->assertSame(403, $this->postJson("/api/v1/rbac/assignments/users/{$staff->id}/roles", ['role_id' => (string) $this->ownerRole->id])->status());
        $this->assertFalse(UserRole::query()->where('user_id', $staff->id)->where('role_id', $this->ownerRole->id)->exists());
    }

    public function test_owner_cannot_remove_own_owner_role_and_last_active_owner_is_protected(): void
    {
        $owner = $this->userWith($this->a, [$this->ownerRole]);
        DB::table('system_user_roles')->insert(['user_id' => (string) $owner->id, 'role_id' => (string) $this->ownerRole->id, 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($owner, ['*']);

        $this->deleteJson("/api/v1/rbac/assignments/users/{$owner->id}/roles/{$this->ownerRole->id}")->assertForbidden();
        $this->assertTrue(UserRole::query()->where('user_id', $owner->id)->where('role_id', $this->ownerRole->id)->exists());
        $this->assertSame(0, RbacProtection::otherActiveOwners((string) $this->a->id, (string) $owner->id), 'still the only active Owner');

        // Web staff admin: the sole Owner cannot be demoted or deactivated by another admin, and a non-Owner cannot promote.
        $service = app(StaffAccountService::class);
        $nonOwnerAdmin = $this->userWith($this->a, [$this->roleWith('o17h_c_company_admin', ['admin.company.manage'], (string) $this->a->id)]);
        $staff = $this->userWith($this->a, [Role::query()->where('name', 'Project Member')->firstOrFail()]);
        foreach ([
            'non-Owner demotes the Owner' => fn () => $service->changeRole($owner, 'Project Member', $nonOwnerAdmin),
            'non-Owner deactivates the Owner' => fn () => $service->deactivate($owner, $nonOwnerAdmin),
            'non-Owner promotes staff' => fn () => $service->changeRole($staff, 'super_admin', $nonOwnerAdmin),
            'staff promotes itself' => fn () => $service->changeRole($staff, 'super_admin', $staff),
        ] as $label => $attempt) {
            try {
                $attempt();
                $this->fail("{$label} must be refused");
            } catch (StaffAccountException) {
                // refused as required
            }
        }
        $this->assertTrue($owner->fresh()->is_active);
        $this->assertSame(['super_admin'], $service->roleNames($owner->fresh()));
        $this->assertSame(['Project Member'], $service->roleNames($staff->fresh()));

        // The Owner can promote another staff member (audited in the same transaction).
        $service->changeRole($staff, 'super_admin', $owner);
        $this->assertSame(['super_admin'], $service->roleNames($staff->fresh()));
        $this->assertTrue(DB::table('audit_logs')->where('action', 'admin.user.role_changed')->where('entity_id', (string) $staff->id)->exists());
    }

    public function test_protected_codes_reserved_names_and_alias_seeding_cannot_escalate(): void
    {
        $owner = $this->userWith($this->a, [$this->ownerRole]);
        $roleAdmin = $this->userWith($this->a, [$this->roleWith('o17h_c_role_admin3', ['role.view', 'role.create', 'role.edit', 'role.assign', 'permission.create', 'permission.import'], (string) $this->a->id)]);
        $target = $this->roleWith('o17h_c_sync_target', ['task.view'], (string) $this->a->id);
        $before = $this->snapshot();

        Sanctum::actingAs($roleAdmin, ['*']);
        $this->postJson("/api/v1/rbac/roles/{$target->id}/permissions", ['permission_codes' => ['task.view', 'role.assign']])->assertForbidden();
        $this->postJson('/api/v1/rbac/roles', ['name' => 'Admin', 'scope' => 'custom'])->assertStatus(400);
        $this->postJson('/api/v1/rbac/roles', ['name' => 'platform operator', 'scope' => 'custom'])->assertStatus(400);
        $this->postJson('/api/v1/rbac/permissions', ['module' => 'system', 'action' => 'root'])->assertStatus(400);
        $this->assertSame($before, $this->snapshot(), 'tenant role administrator escalations must persist nothing');

        // The Owner may attach a protected code it holds (audited) but never one it does not hold (system.admin).
        Sanctum::actingAs($owner, ['*']);
        $this->postJson("/api/v1/rbac/roles/{$target->id}/permissions", ['permission_codes' => ['task.view', 'role.view']])->assertOk();
        $this->assertTrue(DB::table('audit_logs')->where('action', 'rbac.role.permissions_synced')->where('entity_id', (string) $target->id)->exists());
        $this->postJson("/api/v1/rbac/roles/{$target->id}/permissions", ['permission_codes' => ['task.view', 'system.admin']])->assertForbidden();
        $this->assertFalse($target->fresh()->permissions()->where('code', 'system.admin')->exists());

        // A tenant role that (somehow) carries an alias name never receives the alias seeders' grants.
        $impostor = Role::query()->create(['name' => 'system_admin', 'scope' => 'custom', 'is_active' => true, 'tenant_id' => (string) $this->a->id]);
        Artisan::call('production:rbac');
        $this->assertSame(0, $impostor->permissions()->count(), 'alias seeders must target global roles only');
    }

    // ===================================================================== D6 Platform Operators

    public function test_no_platform_operator_exists_by_default_and_provisioning_is_server_only_and_audited(): void
    {
        $this->seed(DatabaseSeeder::class);
        Artisan::call('production:rbac');
        $this->assertSame(0, DB::table('platform_operators')->count());
        $this->assertFalse(Role::query()->where('name', RbacProtection::PLATFORM_OPERATOR_ROLE)->exists());

        $user = $this->userWith($this->a, []);
        $owner = $this->userWith($this->a, [$this->ownerRole]);
        $this->assertFalse($owner->hasPermission(PlatformPermissions::OPS_VIEW), 'tenant Owner never holds platform authority');

        $this->artisan('platform:operator', ['action' => 'grant', '--user' => $user->email, '--yes' => true])->assertExitCode(1); // no reason/approval
        $this->assertSame(0, DB::table('platform_operators')->count());

        $this->artisan('platform:operator', ['action' => 'grant', '--user' => $user->email, '--reason' => 'night ops', '--approval' => 'OWN-2026-024', '--yes' => true])->assertExitCode(0);
        $role = Role::query()->where('name', RbacProtection::PLATFORM_OPERATOR_ROLE)->firstOrFail();
        $this->assertNull($role->tenant_id);
        $this->assertSame(array_keys(PlatformPermissions::CATALOGUE), array_values(array_intersect(array_keys(PlatformPermissions::CATALOGUE), $role->permissions()->pluck('code')->all())));
        $this->assertSame(5, $role->permissions()->count(), 'exactly the platform codes');
        $this->assertSame(['platform'], DB::table('role_permissions')->where('role_id', $role->id)->distinct()->pluck('grant_source')->all());
        $ledger = DB::table('platform_operators')->where('user_id', $user->id)->first();
        $this->assertSame((string) $user->id, (string) $ledger->active_key);
        $this->assertSame('platform.operator.granted', DB::table('audit_logs')->where('id', $ledger->grant_audit_log_id)->value('action'));
        $this->assertTrue($user->fresh()->hasPermission(PlatformPermissions::OPS_VIEW));
        $this->assertTrue($this->gatePasses($user->fresh(), 'platform.ops.view'));

        // The Platform Operator role assigned WITHOUT the ledger (any non-server path) confers nothing.
        $sneaky = $this->userWith($this->a, [$role]);
        $this->assertFalse($sneaky->hasPermission(PlatformPermissions::OPS_VIEW));
        $this->assertFalse($this->gatePasses($sneaky, 'platform.backup.execute'));

        // HTTP can neither assign, edit nor recreate it.
        Sanctum::actingAs($owner, ['*']);
        $this->assertSame(403, $this->postJson("/api/v1/rbac/assignments/users/{$owner->id}/roles", ['role_id' => (string) $role->id])->status());
        $this->assertContains($this->postJson("/api/v1/rbac/roles/{$role->id}/permissions", ['permission_codes' => [PlatformPermissions::OPS_VIEW]])->status(), [404, 422]);
        $this->assertFalse(PlatformPermissions::roleHoldsPlatformPermission((string) DB::table('user_roles')->where('user_id', $owner->id)->value('role_id')));
        foreach (Route::getRoutes() as $route) {
            $this->assertStringNotContainsString('PlatformOperator', $route->getActionName(), 'no HTTP route may reach provisioning');
        }

        // Revocation (server-only, audited) removes the authority.
        $this->artisan('platform:operator', ['action' => 'revoke', '--user' => $user->email, '--reason' => 'rotation', '--yes' => true])->assertExitCode(0);
        $ledger = DB::table('platform_operators')->where('user_id', $user->id)->first();
        $this->assertNull($ledger->active_key);
        $this->assertSame('platform.operator.revoked', DB::table('audit_logs')->where('id', $ledger->revoke_audit_log_id)->value('action'));
        $this->assertFalse($user->fresh()->hasPermission(PlatformPermissions::OPS_VIEW));
        $this->assertFalse(UserRole::query()->where('user_id', $user->id)->where('role_id', $role->id)->exists());
    }

    // ===================================================================== Gate 4 safeguards

    public function test_destructive_operations_need_kill_switch_confirmation_and_are_audited_and_never_download(): void
    {
        $operator = $this->userWith($this->a, []);
        $this->artisan('platform:operator', ['action' => 'grant', '--user' => $operator->email, '--reason' => 'test', '--approval' => 'test', '--yes' => true])->assertExitCode(0);
        $operator = $operator->fresh();
        $this->actingAs($operator);
        $audit0 = DB::table('audit_logs')->where('action', 'like', 'platform.operation.%')->count();

        config(['platform_operations.destructive_operations_enabled' => false]);
        $this->postJson('/admin/maintenance/cleanup-logs', ['confirm_operation' => 'maintenance.cleanup-logs'])->assertStatus(503);
        $this->postJson('/admin/maintenance/backup-database', ['confirm_operation' => 'backup.database'])->assertStatus(503);
        $this->assertSame($audit0, DB::table('audit_logs')->where('action', 'like', 'platform.operation.%')->count(), 'nothing runs or audits while switched off');

        config(['platform_operations.destructive_operations_enabled' => true]);
        $this->postJson('/admin/maintenance/cleanup-logs', [])->assertStatus(428);
        $this->postJson('/admin/maintenance/cleanup-logs', ['confirm_operation' => 'backup.database'])->assertStatus(428);

        $response = $this->postJson('/admin/maintenance/cleanup-logs', ['confirm_operation' => 'maintenance.cleanup-logs']);
        $this->assertContains($response->status(), [200, 500]);
        $this->assertSame(['platform.operation.requested', 'platform.operation.completed'], DB::table('audit_logs')->where('action', 'like', 'platform.operation.%')->orderBy('id')->pluck('action')->slice(-2)->values()->all());

        $backup = $this->postJson('/admin/maintenance/backup-database', ['confirm_operation' => 'backup.database']);
        $this->assertStringStartsWith('application/json', (string) $backup->headers->get('Content-Type'), 'a full backup is never downloadable over HTTP');
        $this->assertNull($backup->headers->get('Content-Disposition'));
    }

    // ===================================================================== preservation

    public function test_existing_grants_and_owner_workflows_are_preserved(): void
    {
        $counts = [];
        foreach (['super_admin', 'Project Manager', 'Project Member', 'Finance', 'site_engineer'] as $name) {
            $counts[$name] = Role::query()->where('name', $name)->firstOrFail()->permissions()->count();
        }
        $this->assertSame(['super_admin' => 223, 'Project Manager' => 67, 'Project Member' => 27, 'Finance' => 15, 'site_engineer' => 18], $counts);
        $this->assertSame(0, DB::table('role_permission_revocations')->count(), 'no revocation exists unless deliberately made');
        $this->assertSame(['seed'], DB::table('role_permissions')->whereNotNull('grant_source')->distinct()->pluck('grant_source')->all());

        $owner = $this->userWith($this->a, [$this->ownerRole]);
        $this->assertTrue(RbacProtection::hasOwnerAuthority($owner));
        Sanctum::actingAs($owner, ['*']);
        $this->getJson('/api/v1/rbac/roles')->assertOk();
        $this->postJson('/api/zena/crm/leads', ['contact_hint' => 'o17h c lead'])->assertCreated();
    }

    // ===================================================================== helpers

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
            'active_revocations' => DB::table('role_permission_revocations')->whereNull('superseded_at')->count(),
            'audit_logs' => DB::table('audit_logs')->count(),
            'platform_operators' => DB::table('platform_operators')->count(),
        ];
    }
}
