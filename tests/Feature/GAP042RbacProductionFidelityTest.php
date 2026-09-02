<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Permission as AppPermission;
use App\Models\Project;
use App\Models\Role as AppRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Src\RBAC\Models\Permission;
use Src\RBAC\Models\Role;
use Src\RBAC\Services\RBACManager;
use Tests\TestCase;

/**
 * GAP-042 — RBAC production-fidelity acceptance matrix.
 *
 * Implements the 20 acceptance items from the Owner-approved Gate 2 design
 * (docs/superpowers/specs/2026-09-01-gap-042-rbac-model-consolidation-design.md §10).
 *
 * Items 1, 2, 9 additionally require genuine MySQL 8.0 evidence beyond what this
 * SQLite-run file can prove on its own (see the Gate-3 packet for that evidence);
 * this file still exercises their service-level/schema-shape assertions on
 * whichever connection PHPUnit is configured against.
 */
class GAP042RbacProductionFidelityTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected string $tokenA;
    protected string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->userA = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->userB = User::factory()->create(['tenant_id' => $this->tenantB->id]);

        $this->grantFullRbacGateAccess($this->userA);
        $this->grantFullRbacGateAccess($this->userB);

        $this->tokenA = $this->userA->createToken('t')->plainTextToken;
        $this->tokenB = $this->userB->createToken('t')->plainTextToken;
    }

    /**
     * Grants every `rbac:*` gate permission this test suite exercises to the
     * given user, via the real App\Models\Role/Permission path the
     * RoleBasedAccessControlMiddleware actually authorizes against — mirrors
     * tests/Feature/RbacApiTest.php's existing, already-approved pattern.
     */
    private function grantFullRbacGateAccess(User $user): void
    {
        $codes = [
            'role.view', 'role.create', 'role.edit', 'role.delete', 'role.assign',
            'permission.view', 'permission.create', 'permission.edit', 'permission.delete',
            'permission.export', 'permission.import',
            'user.view', 'project.view', 'audit.view',
        ];

        $ids = collect($codes)->map(function (string $code) {
            [$module, $action] = explode('.', $code, 2);

            return AppPermission::firstOrCreate(
                ['code' => $code],
                ['name' => $code, 'module' => $module, 'action' => $action, 'description' => $code]
            )->id;
        })->all();

        $role = AppRole::firstOrCreate(
            ['name' => 'gap042_full_access', 'scope' => 'system'],
            ['description' => 'GAP-042 test role', 'allow_override' => true]
        );

        $role->permissions()->syncWithoutDetaching($ids);
        $user->roles()->syncWithoutDetaching([$role->id]);
    }

    private function headers(string $token, string $tenantId): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => $tenantId,
            'Accept' => 'application/json',
        ];
    }

    // ------------------------------------------------------------------
    // Item 3 — authenticated + authorized real HTTP roles/permissions
    // ------------------------------------------------------------------
    public function test_authenticated_authorized_roles_and_permissions_endpoints_succeed(): void
    {
        Sanctum::actingAs($this->userA);

        $response = $this->withHeaders($this->headers($this->tokenA, $this->tenantA->id))
            ->getJson('/api/v1/rbac/roles');
        $response->assertStatus(200);

        $response = $this->withHeaders($this->headers($this->tokenA, $this->tenantA->id))
            ->getJson('/api/v1/rbac/permissions');
        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    // Item 4 — denied access remains denied
    // ------------------------------------------------------------------
    public function test_denied_access_without_permission_still_403(): void
    {
        $noPermUser = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        Sanctum::actingAs($noPermUser);

        $response = $this->withHeaders($this->headers(
            $noPermUser->createToken('t')->plainTextToken,
            $this->tenantA->id
        ))->postJson('/api/v1/rbac/roles', [
            'name' => 'Should Not Create',
            'scope' => 'custom',
        ]);

        $response->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // Item 5 — tenant A cannot read/write tenant B's role
    // ------------------------------------------------------------------
    public function test_tenant_a_cannot_read_or_write_tenant_bs_role(): void
    {
        $roleA = Role::create(['name' => 'RoleA-' . uniqid(), 'scope' => 'custom', 'tenant_id' => $this->tenantA->id]);
        $roleB = Role::create(['name' => 'RoleB-' . uniqid(), 'scope' => 'custom', 'tenant_id' => $this->tenantB->id]);
        $systemRole = Role::create(['name' => 'GlobalSys-' . uniqid(), 'scope' => 'system', 'tenant_id' => null]);

        Sanctum::actingAs($this->userA);
        $h = $this->headers($this->tokenA, $this->tenantA->id);

        // list must not contain B's role, must contain A's + global
        $listResponse = $this->withHeaders($h)->getJson('/api/v1/rbac/roles?per_page=100');
        $listResponse->assertStatus(200);
        $ids = collect($listResponse->json('data.roles.data'))->pluck('id')->all();
        $this->assertContains($roleA->id, $ids);
        $this->assertContains($systemRole->id, $ids);
        $this->assertNotContains($roleB->id, $ids);

        // show
        $this->withHeaders($h)->getJson("/api/v1/rbac/roles/{$roleB->id}")->assertStatus(404);

        // update
        $this->withHeaders($h)->putJson("/api/v1/rbac/roles/{$roleB->id}", ['name' => 'Hacked'])
            ->assertStatus(404);
        $this->assertDatabaseHas('roles', ['id' => $roleB->id, 'name' => $roleB->name]);

        // delete
        $this->withHeaders($h)->deleteJson("/api/v1/rbac/roles/{$roleB->id}")->assertStatus(404);
        $this->assertDatabaseHas('roles', ['id' => $roleB->id]);

        // sync permissions
        $this->withHeaders($h)->postJson("/api/v1/rbac/roles/{$roleB->id}/permissions", ['permission_codes' => []])
            ->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // Item 6 — role creation binds server-derived tenant
    // ------------------------------------------------------------------
    public function test_role_creation_binds_server_derived_tenant(): void
    {
        Sanctum::actingAs($this->userA);

        $response = $this->withHeaders($this->headers($this->tokenA, $this->tenantA->id))
            ->postJson('/api/v1/rbac/roles', [
                'name' => 'ClientSuppliedTenant-' . uniqid(),
                'scope' => 'custom',
                'tenant_id' => $this->tenantB->id, // attacker-supplied, must be ignored
            ]);

        $response->assertStatus(201);
        $roleId = $response->json('data.role.id');

        $this->assertDatabaseHas('roles', [
            'id' => $roleId,
            'tenant_id' => $this->tenantA->id,
        ]);
    }

    // ------------------------------------------------------------------
    // Item 7 — effective-permissions / check-permission live routes work
    // ------------------------------------------------------------------
    public function test_effective_permissions_and_check_permission_routes_return_200(): void
    {
        Sanctum::actingAs($this->userA);
        $h = $this->headers($this->tokenA, $this->tenantA->id);

        $response = $this->withHeaders($h)
            ->getJson("/api/v1/rbac/users/{$this->userA->id}/effective-permissions");
        $response->assertStatus(200);

        $response = $this->withHeaders($h)
            ->postJson("/api/v1/rbac/users/{$this->userA->id}/check-permission", [
                'permission_code' => 'role.view',
            ]);
        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    // Item 8 — 3-layer permission computation
    // ------------------------------------------------------------------
    public function test_three_layer_permission_computation_system_custom_project(): void
    {
        /** @var RBACManager $manager */
        $manager = app(RBACManager::class);

        $perm1 = Permission::create(['code' => 'gap042.view', 'module' => 'gap042', 'action' => 'view']);
        $perm2 = Permission::create(['code' => 'gap042.edit', 'module' => 'gap042', 'action' => 'edit']);

        $systemRole = Role::create(['name' => 'Sys-' . uniqid(), 'scope' => 'system']);
        $systemRole->permissions()->attach([$perm1->id, $perm2->id]);

        $customRole = Role::create(['name' => 'Cus-' . uniqid(), 'scope' => 'custom', 'tenant_id' => $this->tenantA->id]);
        $customRole->permissions()->attach([$perm1->id]);

        $project = Project::factory()->create(['tenant_id' => $this->tenantA->id]);
        $projectRole = Role::create(['name' => 'Proj-' . uniqid(), 'scope' => 'project', 'tenant_id' => $this->tenantA->id]);
        $projectRole->permissions()->attach([$perm1->id]);

        // user with system-only role
        $sysOnlyUser = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $manager->assignSystemRole($sysOnlyUser->id, $systemRole->id, $this->tenantA->id);
        $sysOnlyPerms = $manager->calculateEffectivePermissions($sysOnlyUser->id);
        $this->assertContains('gap042.view', $sysOnlyPerms);
        $this->assertContains('gap042.edit', $sysOnlyPerms);

        // user with system + custom -> least-privilege intersection
        $sysCustomUser = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $manager->assignSystemRole($sysCustomUser->id, $systemRole->id, $this->tenantA->id);
        $manager->assignCustomRole($sysCustomUser->id, $customRole->id, $this->tenantA->id);
        $sysCustomPerms = $manager->calculateEffectivePermissions($sysCustomUser->id);
        $this->assertContains('gap042.view', $sysCustomPerms);
        $this->assertNotContains('gap042.edit', $sysCustomPerms); // intersected away

        // user with project-scope role for that specific project
        $projUser = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $manager->assignProjectRole($projUser->id, $projectRole->id, $project->id, $this->tenantA->id);
        $projPerms = $manager->calculateEffectivePermissions($projUser->id, $project->id);
        $this->assertContains('gap042.view', $projPerms);
    }

    // ------------------------------------------------------------------
    // Item 9 — custom_user_roles proven at service level
    // ------------------------------------------------------------------
    public function test_custom_user_roles_service_level_write_read(): void
    {
        /** @var RBACManager $manager */
        $manager = app(RBACManager::class);

        $perm = Permission::create(['code' => 'gap042.custom.check', 'module' => 'gap042', 'action' => 'customcheck']);
        $role = Role::create(['name' => 'CustomRole-' . uniqid(), 'scope' => 'custom', 'tenant_id' => $this->tenantA->id]);
        $role->permissions()->attach([$perm->id]);

        $user = User::factory()->create(['tenant_id' => $this->tenantA->id]);

        $result = $manager->assignCustomRole($user->id, $role->id, $this->tenantA->id);
        $this->assertTrue($result);

        $this->assertDatabaseHas('custom_user_roles', [
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        $perms = $manager->calculateEffectivePermissions($user->id);
        $this->assertContains('gap042.custom.check', $perms);
    }

    // ------------------------------------------------------------------
    // Item 10 / 16 — assignment writes hit the correct canonical table
    // ------------------------------------------------------------------
    public function test_assignment_paths_write_canonical_tables(): void
    {
        Sanctum::actingAs($this->userA);

        $systemRole = Role::create(['name' => 'SysAssign-' . uniqid(), 'scope' => 'system']);
        $targetUser = User::factory()->create(['tenant_id' => $this->tenantA->id]);

        $response = $this->withHeaders($this->headers($this->tokenA, $this->tenantA->id))
            ->postJson('/api/v1/rbac/user-roles', [
                'user_id' => $targetUser->id,
                'role_id' => $systemRole->id,
                'scope' => 'system',
            ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('system_user_roles', [
            'user_id' => $targetUser->id,
            'role_id' => $systemRole->id,
        ]);

        /** @var RBACManager $manager */
        $manager = app(RBACManager::class);

        $customRole = Role::create(['name' => 'CustAssign-' . uniqid(), 'scope' => 'custom', 'tenant_id' => $this->tenantA->id]);
        $this->assertTrue($manager->assignCustomRole($targetUser->id, $customRole->id, $this->tenantA->id));
        $this->assertDatabaseHas('custom_user_roles', ['user_id' => $targetUser->id, 'role_id' => $customRole->id]);

        $project = Project::factory()->create(['tenant_id' => $this->tenantA->id]);
        $projectRole = Role::create(['name' => 'ProjAssign-' . uniqid(), 'scope' => 'project', 'tenant_id' => $this->tenantA->id]);
        $this->assertTrue($manager->assignProjectRole($targetUser->id, $projectRole->id, $project->id, $this->tenantA->id));
        $this->assertDatabaseHas('project_user_roles', [
            'user_id' => $targetUser->id,
            'role_id' => $projectRole->id,
            'project_id' => $project->id,
        ]);

        $permission = Permission::create(['code' => 'gap042.sync.check', 'module' => 'gap042', 'action' => 'synccheck']);
        $syncResponse = $this->withHeaders($this->headers($this->tokenA, $this->tenantA->id))
            ->postJson("/api/v1/rbac/roles/{$customRole->id}/permissions", [
                'permission_codes' => [$permission->code],
            ]);
        $syncResponse->assertStatus(200);
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $customRole->id,
            'permission_id' => $permission->id,
        ]);
    }

    // ------------------------------------------------------------------
    // Item 11 — bulkAssignRoles project-scope no false success
    // ------------------------------------------------------------------
    public function test_bulk_assign_project_scope_no_false_success(): void
    {
        Sanctum::actingAs($this->userA);

        $project = Project::factory()->create(['tenant_id' => $this->tenantA->id]);
        $projectRole = Role::create(['name' => 'BulkProj-' . uniqid(), 'scope' => 'project', 'tenant_id' => $this->tenantA->id]);
        $targetUser = User::factory()->create(['tenant_id' => $this->tenantA->id]);

        $response = $this->withHeaders($this->headers($this->tokenA, $this->tenantA->id))
            ->postJson('/api/v1/rbac/bulk-assign-roles', [
                'user_ids' => [$targetUser->id],
                'role_ids' => [$projectRole->id],
                'project_id' => $project->id,
                'scope' => 'project',
            ]);

        $response->assertStatus(200);
        $result = $response->json('data.results.0');
        $this->assertTrue($result['assigned']);

        $this->assertDatabaseHas('project_user_roles', [
            'user_id' => $targetUser->id,
            'role_id' => $projectRole->id,
            'project_id' => $project->id,
        ]);
    }

    // ------------------------------------------------------------------
    // Item 12 — tenant A cannot assign tenant B's role
    // ------------------------------------------------------------------
    public function test_tenant_a_cannot_assign_tenant_bs_role(): void
    {
        /** @var RBACManager $manager */
        $manager = app(RBACManager::class);

        $bRole = Role::create(['name' => 'BOnlyCustom-' . uniqid(), 'scope' => 'custom', 'tenant_id' => $this->tenantB->id]);
        $aUser = User::factory()->create(['tenant_id' => $this->tenantA->id]);

        $result = $manager->assignCustomRole($aUser->id, $bRole->id, $this->tenantA->id);
        $this->assertFalse($result);

        $this->assertDatabaseMissing('custom_user_roles', ['user_id' => $aUser->id, 'role_id' => $bRole->id]);
        $this->assertDatabaseMissing('system_user_roles', ['user_id' => $aUser->id]);
        $this->assertDatabaseMissing('project_user_roles', ['user_id' => $aUser->id]);
    }

    // ------------------------------------------------------------------
    // Item 13 — tenant A cannot assign a role to tenant B's user
    // ------------------------------------------------------------------
    public function test_tenant_a_cannot_assign_role_to_tenant_bs_user(): void
    {
        /** @var RBACManager $manager */
        $manager = app(RBACManager::class);

        $aRole = Role::create(['name' => 'AOnlyCustom-' . uniqid(), 'scope' => 'custom', 'tenant_id' => $this->tenantA->id]);
        $bUser = User::factory()->create(['tenant_id' => $this->tenantB->id]);

        $result = $manager->assignCustomRole($bUser->id, $aRole->id, $this->tenantA->id);
        $this->assertFalse($result);

        $this->assertDatabaseMissing('custom_user_roles', ['user_id' => $bUser->id, 'role_id' => $aRole->id]);
    }

    // ------------------------------------------------------------------
    // Item 14 — project-role assignment cannot target tenant B's project
    // ------------------------------------------------------------------
    public function test_project_role_assignment_cannot_target_tenant_bs_project(): void
    {
        /** @var RBACManager $manager */
        $manager = app(RBACManager::class);

        $aUser = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $aProjectRole = Role::create(['name' => 'AProjRole-' . uniqid(), 'scope' => 'project', 'tenant_id' => $this->tenantA->id]);
        $bProject = Project::factory()->create(['tenant_id' => $this->tenantB->id]);

        $result = $manager->assignProjectRole($aUser->id, $aProjectRole->id, $bProject->id, $this->tenantA->id);
        $this->assertFalse($result);

        $this->assertDatabaseMissing('project_user_roles', ['user_id' => $aUser->id, 'project_id' => $bProject->id]);
    }

    // ------------------------------------------------------------------
    // Item 15 — route user identity cannot be overridden by conflicting body
    // ------------------------------------------------------------------
    public function test_route_user_identity_not_overridable_by_body(): void
    {
        Sanctum::actingAs($this->userA);

        $userX = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $userY = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $role = Role::create(['name' => 'IdentityCheck-' . uniqid(), 'scope' => 'system']);

        $response = $this->withHeaders($this->headers($this->tokenA, $this->tenantA->id))
            ->postJson("/api/v1/rbac/assignments/users/{$userX->id}/roles", [
                'user_id' => $userY->id,
                'role_id' => $role->id,
                'scope' => 'system',
            ]);

        $response->assertStatus(400);

        $this->assertDatabaseMissing('system_user_roles', ['user_id' => $userX->id, 'role_id' => $role->id]);
        $this->assertDatabaseMissing('system_user_roles', ['user_id' => $userY->id, 'role_id' => $role->id]);
    }

    // ------------------------------------------------------------------
    // Item 17 — the three project-assignment routes are restored
    // ------------------------------------------------------------------
    public function test_project_assignment_routes_restored(): void
    {
        Sanctum::actingAs($this->userA);
        $h = $this->headers($this->tokenA, $this->tenantA->id);

        $project = Project::factory()->create(['tenant_id' => $this->tenantA->id]);
        $projectRole = Role::create(['name' => 'RestoredProj-' . uniqid(), 'scope' => 'project', 'tenant_id' => $this->tenantA->id]);
        $targetUser = User::factory()->create(['tenant_id' => $this->tenantA->id]);

        // POST assign
        $assignResponse = $this->withHeaders($h)
            ->postJson("/api/v1/rbac/assignments/projects/{$project->id}/users/{$targetUser->id}/roles", [
                'role_id' => $projectRole->id,
            ]);
        $this->assertContains($assignResponse->status(), [200, 201]);
        $this->assertDatabaseHas('project_user_roles', [
            'project_id' => $project->id,
            'user_id' => $targetUser->id,
            'role_id' => $projectRole->id,
        ]);

        // GET list
        $listResponse = $this->withHeaders($h)
            ->getJson("/api/v1/rbac/assignments/projects/{$project->id}/users");
        $listResponse->assertStatus(200);

        // DELETE
        $deleteResponse = $this->withHeaders($h)
            ->deleteJson("/api/v1/rbac/assignments/projects/{$project->id}/users/{$targetUser->id}/roles/{$projectRole->id}");
        $this->assertContains($deleteResponse->status(), [200, 204]);
        $this->assertDatabaseMissing('project_user_roles', [
            'project_id' => $project->id,
            'user_id' => $targetUser->id,
            'role_id' => $projectRole->id,
            'deleted_at' => null,
        ]);
    }

    public function test_project_assignment_route_rejects_tenant_bs_project(): void
    {
        Sanctum::actingAs($this->userA);
        $h = $this->headers($this->tokenA, $this->tenantA->id);

        $bProject = Project::factory()->create(['tenant_id' => $this->tenantB->id]);
        $aRole = Role::create(['name' => 'CrossProj-' . uniqid(), 'scope' => 'project', 'tenant_id' => $this->tenantA->id]);
        $targetUser = User::factory()->create(['tenant_id' => $this->tenantA->id]);

        $response = $this->withHeaders($h)
            ->postJson("/api/v1/rbac/assignments/projects/{$bProject->id}/users/{$targetUser->id}/roles", [
                'role_id' => $aRole->id,
            ]);

        $this->assertNotEquals(200, $response->status());
        $this->assertNotEquals(201, $response->status());
        $this->assertDatabaseMissing('project_user_roles', [
            'project_id' => $bProject->id,
            'user_id' => $targetUser->id,
        ]);
    }

    // ------------------------------------------------------------------
    // Item 18 — no test-only shim can manufacture production-impossible schema
    // ------------------------------------------------------------------
    public function test_shim_removed_from_test_case(): void
    {
        $reflection = new \ReflectionClass(TestCase::class);
        $this->assertFalse(
            $reflection->hasMethod('ensureSqliteZenaRbacTables'),
            'ensureSqliteZenaRbacTables() must be deleted, not merely unused.'
        );
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasTable('zena_roles'),
            'zena_roles must not exist after migrate.'
        );
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasTable('zena_permissions'),
            'zena_permissions must not exist after migrate.'
        );
    }

    // ------------------------------------------------------------------
    // Item 19 — grouped tenant-visibility predicate under OR-precedence pressure
    // ------------------------------------------------------------------
    public function test_grouped_tenant_visibility_predicate_discriminates(): void
    {
        $global = Role::create(['name' => 'GlobalPrecedence-' . uniqid(), 'scope' => 'system', 'tenant_id' => null]);
        $roleA = Role::create(['name' => 'PrecedenceA-' . uniqid(), 'scope' => 'custom', 'tenant_id' => $this->tenantA->id]);
        $roleB = Role::create(['name' => 'PrecedenceB-' . uniqid(), 'scope' => 'custom', 'tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->userA);
        $h = $this->headers($this->tokenA, $this->tenantA->id);

        // GET /roles/{id} for each of the three ids, as tenant A
        $this->withHeaders($h)->getJson("/api/v1/rbac/roles/{$global->id}")->assertStatus(200);
        $this->withHeaders($h)->getJson("/api/v1/rbac/roles/{$roleA->id}")->assertStatus(200);
        $this->withHeaders($h)->getJson("/api/v1/rbac/roles/{$roleB->id}")->assertStatus(404);

        // GET /roles?scope=custom must contain roleA + not roleB (global is scope=system, excluded by filter)
        $listResponse = $this->withHeaders($h)->getJson('/api/v1/rbac/roles?scope=custom&per_page=100');
        $ids = collect($listResponse->json('data.roles.data'))->pluck('id')->all();
        $this->assertContains($roleA->id, $ids);
        $this->assertNotContains($roleB->id, $ids);

        // RBACController::getRolesByScope must return exactly the intended rows
        $byScopeResponse = $this->withHeaders($h)->getJson('/api/v1/rbac/roles/by-scope?scope=custom');
        $byScopeResponse->assertStatus(200);
        $byScopeIds = collect($byScopeResponse->json('data.roles'))->pluck('id')->all();
        $this->assertContains($roleA->id, $byScopeIds);
        $this->assertNotContains($roleB->id, $byScopeIds);
    }

    // ------------------------------------------------------------------
    // Item 20 — global roles readable but not writable through tenant surface
    // ------------------------------------------------------------------
    public function test_global_role_readonly_through_tenant_surface(): void
    {
        $global = Role::create(['name' => 'GlobalRO-' . uniqid(), 'scope' => 'system', 'tenant_id' => null]);
        $permission = Permission::create(['code' => 'gap042.global.ro', 'module' => 'gap042', 'action' => 'ro']);
        $global->permissions()->attach([$permission->id]);

        Sanctum::actingAs($this->userA);
        $h = $this->headers($this->tokenA, $this->tenantA->id);

        // (a) readable
        $this->withHeaders($h)->getJson('/api/v1/rbac/roles')->assertStatus(200);
        $this->withHeaders($h)->getJson("/api/v1/rbac/roles/{$global->id}")->assertStatus(200);

        // (b) POST scope=system rejected, no new row
        $countBefore = Role::count();
        $createResponse = $this->withHeaders($h)->postJson('/api/v1/rbac/roles', [
            'name' => 'AttemptGlobalCreate-' . uniqid(),
            'scope' => 'system',
        ]);
        $this->assertNotEquals(201, $createResponse->status());
        $this->assertSame($countBefore, Role::count());

        // (c) update/delete/sync rejected, row + role_permissions unchanged
        $this->withHeaders($h)->putJson("/api/v1/rbac/roles/{$global->id}", ['name' => 'Hacked'])
            ->assertStatus(404);
        $this->assertDatabaseHas('roles', ['id' => $global->id, 'name' => $global->name]);

        $this->withHeaders($h)->postJson("/api/v1/rbac/roles/{$global->id}/permissions", ['permission_codes' => []])
            ->assertStatus(404);
        $this->assertDatabaseHas('role_permissions', ['role_id' => $global->id, 'permission_id' => $permission->id]);

        $this->withHeaders($h)->deleteJson("/api/v1/rbac/roles/{$global->id}")->assertStatus(404);
        $this->assertDatabaseHas('roles', ['id' => $global->id]);

        // (d) control: own-tenant role mutation still succeeds
        $ownRole = Role::create(['name' => 'OwnMutable-' . uniqid(), 'scope' => 'custom', 'tenant_id' => $this->tenantA->id]);
        $this->withHeaders($h)->putJson("/api/v1/rbac/roles/{$ownRole->id}", ['name' => 'Renamed'])
            ->assertStatus(200);

        // (e) control: assigning the global role to a tenant-A user still succeeds
        /** @var RBACManager $manager */
        $manager = app(RBACManager::class);
        $targetUser = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->assertTrue($manager->assignSystemRole($targetUser->id, $global->id, $this->tenantA->id));
        $this->assertDatabaseHas('system_user_roles', ['user_id' => $targetUser->id, 'role_id' => $global->id]);
    }
}
