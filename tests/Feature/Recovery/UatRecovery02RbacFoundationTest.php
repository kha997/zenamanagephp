<?php

declare(strict_types=1);

namespace Tests\Feature\Recovery;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ZenaPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Src\RBAC\Services\SystemRolePivotBackfill;
use Tests\TestCase;

/**
 * UAT RECOVERY 02 — S1 (permission catalog) + S2 (role pivot alignment), verified TOGETHER on the real
 * canonical seeder. No permission is injected; actors only receive an already-seeded role.
 */
class UatRecovery02RbacFoundationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
    }

    private function staff(string $seededRole, ?Tenant $tenant = null, array $attrs = []): User
    {
        $user = User::factory()->create(array_merge(['tenant_id' => ($tenant ?? $this->tenantA)->id, 'role' => null], $attrs));
        DB::table('user_roles')->insert([
            'user_id' => $user->id, 'role_id' => $this->roleId($seededRole), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user;
    }

    private function roleId(string $name): string
    {
        return (string) DB::table('roles')->where('name', $name)->value('id');
    }

    /** users.role='admin' is only to satisfy the (out-of-scope, unchanged) parameterless rbac gate, D2. */
    private function admin(): User
    {
        return $this->staff('System Admin', null, ['role' => 'admin']);
    }

    private function assign(User $actor, string $targetId, string $roleId)
    {
        Sanctum::actingAs($actor);

        return $this->withHeaders(['X-Tenant-ID' => (string) $actor->tenant_id])
            ->postJson("/api/v1/rbac/assignments/users/{$targetId}/roles", ['role_id' => $roleId]);
    }

    private function revoke(User $actor, string $targetId, string $roleId)
    {
        Sanctum::actingAs($actor);

        return $this->withHeaders(['X-Tenant-ID' => (string) $actor->tenant_id])
            ->deleteJson("/api/v1/rbac/assignments/users/{$targetId}/roles/{$roleId}");
    }

    private function rows(string $userId, string $roleId): array
    {
        return [
            'ledger' => DB::table('system_user_roles')->where('user_id', $userId)->where('role_id', $roleId)->count(),
            'user_roles' => DB::table('user_roles')->where('user_id', $userId)->where('role_id', $roleId)->count(),
        ];
    }

    private function grantedCodes(string $roleName): array
    {
        return DB::table('role_permissions')
            ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('roles.name', $roleName)->pluck('permissions.code')->sort()->values()->all();
    }

    // ===================================================================== S1

    #[Group('uat-recovery-02-s1')]
    public function test_S1_role_assign_is_held_only_by_system_admin(): void
    {
        $holders = DB::table('role_permissions')
            ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('permissions.name', 'role.assign')->pluck('roles.name')->all();

        $this->assertSame(['System Admin'], $holders);
        $this->assertTrue($this->admin()->hasPermission('role.assign'));
    }

    #[Group('uat-recovery-02-s1')]
    public function test_S1_no_wildcard_or_super_admin_bypass_was_introduced(): void
    {
        $this->assertSame(0, DB::table('roles')->whereIn(DB::raw('LOWER(name)'), ['super_admin', 'system_admin'])->count());
        $this->assertFalse($this->admin()->isSuperAdmin());
        $this->assertSame(0, DB::table('permissions')->where('code', 'like', '%*%')->orWhere('name', 'like', '%*%')->count());
    }

    #[Group('uat-recovery-02-s1')]
    public function test_S1_reseeding_does_not_duplicate_definitions_or_widen_system_admin(): void
    {
        $perms = DB::table('permissions')->count();
        $adminBefore = $this->grantedCodes('System Admin');

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame($perms, DB::table('permissions')->count(), 'permission rows must not grow on re-seed');
        $this->assertSame(0, DB::table('permissions')->select('code')->groupBy('code')->havingRaw('COUNT(*) > 1')->count());
        $adminAfter = $this->grantedCodes('System Admin');
        $this->assertSame($adminBefore, $adminAfter, 'System Admin grants must not change on re-seed');
        $this->assertContains('role.assign', $adminAfter);
    }

    #[Group('uat-recovery-02-s1')]
    public function test_S1_null_permission_names_are_repaired_without_changing_identity(): void
    {
        foreach (['project.read', 'user.manage'] as $code) {
            $row = DB::table('permissions')->where('code', $code)->first();
            $this->assertNotNull($row, "{$code} exists");
            $this->assertSame($code, $row->name, "{$code} must carry name=code so hasPermission() can match it");
        }
        $before = DB::table('permissions')->where('code', 'project.read')->value('id');
        DB::table('permissions')->where('code', 'project.read')->update(['name' => null]);
        (new ZenaPermissionsSeeder())->run();
        $this->assertSame($before, DB::table('permissions')->where('code', 'project.read')->value('id'), 'id (identity) is stable');
        $this->assertSame('project.read', DB::table('permissions')->where('code', 'project.read')->value('name'));
    }

    /** Characterization of PRE-EXISTING drift (not introduced here): see decision package finding S1-F1. */
    #[Group('uat-recovery-02-char')]
    public function test_CHAR_member_grants_depend_on_seeder_ordering_pre_existing(): void
    {
        $first = $this->grantedCodes('Project Member');
        (new \Database\Seeders\PermissionSeeder())->run(); // second pass: codes created later by ZenaPermissionsSeeder now exist
        $second = $this->grantedCodes('Project Member');

        $this->assertContains('task.view', $second, 'PermissionSeeder intends to grant task.view to Project Member');
        $this->assertNotContains('task.view', $first, 'but a fresh DatabaseSeeder run drops it (ordering) — OWNER_DECISION_REQUIRED, not changed in this slice');
    }

    // ===================================================================== S2 + integrated

    #[Group('uat-recovery-02-s2')]
    public function test_INT_system_admin_assigns_role_and_it_takes_effect_in_both_stores(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => null]);
        $member = $this->roleId('Project Member');
        $this->assertFalse($target->hasPermission('task.read'));

        $res = $this->assign($admin, $target->id, $member);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame(['ledger' => 1, 'user_roles' => 1], $this->rows($target->id, $member));
        $this->assertTrue($target->fresh()->hasPermission('task.read'));

        // idempotent: assigning again creates no duplicate in either store
        $this->assign($admin, $target->id, $member)->assertStatus(200);
        $this->assertSame(['ledger' => 1, 'user_roles' => 1], $this->rows($target->id, $member));
    }

    #[Group('uat-recovery-02-s2')]
    public function test_INT_revocation_removes_effective_access_and_both_rows(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => null]);
        $member = $this->roleId('Project Member');
        $this->assign($admin, $target->id, $member)->assertStatus(200);
        $this->assertTrue($target->fresh()->hasPermission('task.read'));

        $this->revoke($admin, $target->id, $member)->assertStatus(200);

        $this->assertSame(['ledger' => 0, 'user_roles' => 0], $this->rows($target->id, $member));
        $this->assertFalse($target->fresh()->hasPermission('task.read'));
    }

    #[Group('uat-recovery-02-s2')]
    public function test_INT_project_manager_and_member_cannot_assign_system_roles_and_nothing_is_written(): void
    {
        $adminRole = $this->roleId('System Admin');
        foreach (['Project Manager' => ['role' => 'project_manager'], 'Project Member' => ['role' => 'team_member']] as $name => $legacy) {
            $actor = $this->staff($name, null, $legacy); // legacy value only lets them reach the permission gate (D2 unchanged)
            $this->assertFalse($actor->hasPermission('role.assign'));

            // self-escalation attempt to System Admin, and an assignment to someone else
            $res = $this->assign($actor, $actor->id, $adminRole);
            $this->assertSame(403, $res->getStatusCode(), "{$name} must be denied");
            $this->assertSame(['ledger' => 0, 'user_roles' => 0], $this->rows($actor->id, $adminRole));
            $this->assertFalse($actor->fresh()->hasPermission('role.assign'));
        }
        $this->assertSame(0, DB::table('system_user_roles')->count(), 'a denied request leaves the ledger untouched');
    }

    #[Group('uat-recovery-02-s2')]
    public function test_INT_cross_tenant_assignment_is_denied_without_mutation(): void
    {
        $admin = $this->admin(); // tenant A
        $foreign = User::factory()->create(['tenant_id' => $this->tenantB->id, 'role' => null]);
        $member = $this->roleId('Project Member');

        $res = $this->assign($admin, $foreign->id, $member);

        $this->assertContains($res->getStatusCode(), [403, 404, 422]);
        $this->assertSame(['ledger' => 0, 'user_roles' => 0], $this->rows($foreign->id, $member));
        $this->assertFalse($foreign->fresh()->hasPermission('task.read'));

        // and cross-tenant revoke cannot strip another tenant's assignment
        $ownB = User::factory()->create(['tenant_id' => $this->tenantB->id, 'role' => null]);
        DB::table('system_user_roles')->insert(['user_id' => $ownB->id, 'role_id' => $member, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('user_roles')->insert(['user_id' => $ownB->id, 'role_id' => $member, 'created_at' => now(), 'updated_at' => now()]);
        $this->revoke($admin, $ownB->id, $member);
        $this->assertSame(['ledger' => 1, 'user_roles' => 1], $this->rows($ownB->id, $member));
    }

    #[Group('uat-recovery-02-s2')]
    public function test_INT_non_system_or_tenant_bound_role_cannot_be_assigned(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => null]);
        $custom = (string) \Illuminate\Support\Str::ulid();
        DB::table('roles')->insert(['id' => $custom, 'name' => 'UAT tenant role', 'scope' => 'custom', 'allow_override' => 0, 'is_active' => 1, 'tenant_id' => $this->tenantB->id, 'created_at' => now(), 'updated_at' => now()]);

        $res = $this->assign($admin, $target->id, $custom);

        $this->assertGreaterThanOrEqual(400, $res->getStatusCode());
        $this->assertSame(['ledger' => 0, 'user_roles' => 0], $this->rows($target->id, $custom));
        $res = $this->assign($admin, $target->id, (string) \Illuminate\Support\Str::ulid());
        $this->assertGreaterThanOrEqual(400, $res->getStatusCode(), 'unknown role id fails closed');
    }

    #[Group('uat-recovery-02-s2')]
    public function test_INT_revoke_never_deletes_an_independent_user_roles_row(): void
    {
        $admin = $this->admin();
        $target = $this->staff('Project Member'); // user_roles only, no ledger row
        $member = $this->roleId('Project Member');

        $this->revoke($admin, $target->id, $member);

        $this->assertSame(['ledger' => 0, 'user_roles' => 1], $this->rows($target->id, $member), 'revoke only acts on ledger-backed assignments');
        $this->assertTrue($target->fresh()->hasPermission('task.read'));
    }

    #[Group('uat-recovery-02-s2')]
    public function test_INT_bulk_assignment_route_uses_the_same_aligned_path(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => null]);
        $member = $this->roleId('Project Member');
        Sanctum::actingAs($admin);

        $res = $this->withHeaders(['X-Tenant-ID' => (string) $admin->tenant_id])->postJson('/api/v1/rbac/bulk-assign-roles', [
            'user_ids' => [$target->id], 'role_ids' => [$member], 'scope' => 'system',
        ]);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame(['ledger' => 1, 'user_roles' => 1], $this->rows($target->id, $member));
        $this->assertTrue($target->fresh()->hasPermission('task.read'));
    }

    // ===================================================================== S2 backfill

    private function ledgerRow(string $userId, string $roleId): void
    {
        DB::table('system_user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
    }

    #[Group('uat-recovery-02-backfill')]
    public function test_BACKFILL_empty_database_is_a_noop(): void
    {
        $plan = (new SystemRolePivotBackfill())->plan();
        $this->assertSame(['INSERT' => 0, 'DUPLICATE' => 0, 'CONFLICT' => 0], $plan['summary']);
        $this->assertSame([], (new SystemRolePivotBackfill())->apply()['inserted']);
    }

    #[Group('uat-recovery-02-backfill')]
    public function test_BACKFILL_inserts_ledger_only_rows_is_idempotent_and_reversible(): void
    {
        $member = $this->roleId('Project Member');
        $a = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => null]);
        $b = User::factory()->create(['tenant_id' => $this->tenantB->id, 'role' => null]); // different tenant, same global role
        $dup = $this->staff('Project Member');                                              // already in user_roles
        foreach ([$a, $b, $dup] as $u) {
            $this->ledgerRow($u->id, $member);
        }
        $svc = new SystemRolePivotBackfill();

        $dry = $svc->plan();
        $this->assertSame(['INSERT' => 2, 'DUPLICATE' => 1, 'CONFLICT' => 0], $dry['summary']);
        $this->assertSame(0, DB::table('user_roles')->whereIn('user_id', [$a->id, $b->id])->count(), 'plan() is read-only');

        $run = $svc->apply();
        $this->assertCount(2, $run['inserted']);
        $this->assertSame(1, DB::table('user_roles')->where('user_id', $a->id)->count());
        $this->assertSame(1, DB::table('user_roles')->where('user_id', $b->id)->count());
        $this->assertSame(1, DB::table('user_roles')->where('user_id', $dup->id)->where('role_id', $member)->count(), 'no duplicate created');

        $again = $svc->apply();
        $this->assertSame([], $again['inserted'], 'second run inserts nothing');

        $this->assertSame(2, $svc->rollback($run['inserted']));
        $this->assertSame(0, DB::table('user_roles')->whereIn('user_id', [$a->id, $b->id])->count());
        $this->assertSame(1, DB::table('user_roles')->where('user_id', $dup->id)->count(), 'rollback touches only rows it inserted');
        $this->assertSame(3, DB::table('system_user_roles')->count(), 'ledger never modified');
    }

    #[Group('uat-recovery-02-backfill')]
    public function test_BACKFILL_conflicts_fail_closed_and_privileged_roles_need_explicit_approval(): void
    {
        $member = $this->roleId('Project Member');
        $sysAdmin = $this->roleId('System Admin');
        $u = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => null]);
        $noTenant = User::factory()->create(['tenant_id' => null, 'role' => null]);
        $custom = (string) \Illuminate\Support\Str::ulid();
        DB::table('roles')->insert(['id' => $custom, 'name' => 'UAT bound', 'scope' => 'custom', 'allow_override' => 0, 'is_active' => 1, 'tenant_id' => $this->tenantA->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->ledgerRow($u->id, $sysAdmin);                                             // privileged
        $this->ledgerRow($u->id, $custom);                                               // not global-system
        $this->ledgerRow($u->id, (string) \Illuminate\Support\Str::ulid());             // role missing
        $this->ledgerRow((string) \Illuminate\Support\Str::ulid(), $member);            // user missing
        $this->ledgerRow($noTenant->id, $member);                                        // tenant unknown
        DB::table('system_user_roles')->insert(['user_id' => $u->id, 'role_id' => $member, 'deleted_at' => now(), 'created_at' => now(), 'updated_at' => now()]); // revoked row ignored

        $svc = new SystemRolePivotBackfill();
        $plan = $svc->plan();
        $reasons = collect($plan['rows'])->pluck('reason')->filter()->sort()->values()->all();

        $this->assertSame(5, $plan['summary']['CONFLICT']);
        $this->assertSame(0, $plan['summary']['INSERT']);
        $this->assertSame(['PRIVILEGED_ROLE_REQUIRES_EXPLICIT_APPROVAL', 'ROLE_MISSING', 'ROLE_NOT_GLOBAL_SYSTEM', 'USER_MISSING', 'USER_TENANT_UNKNOWN'], $reasons);
        $this->assertSame([], $svc->apply()['inserted']);
        $this->assertSame(0, DB::table('user_roles')->where('user_id', $u->id)->count(), 'nothing guessed, nothing written');

        $approved = $svc->apply([$sysAdmin]);
        $this->assertCount(1, $approved['inserted'], 'privileged row is inserted only with an explicit role-id approval');
        $this->assertSame($sysAdmin, $approved['inserted'][0]['role_id']);
    }
}
