<?php

declare(strict_types=1);

namespace Tests\Feature\Recovery;

use App\Models\Document;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * UAT RECOVERY 01 — identity / RBAC / project isolation evidence.
 *
 * Source: docs/uat/ZENA-UAT-DESIGN-2026-001 (reported evidence, re-verified here).
 *
 * Rules for this file (see docs/audits/UAT-RECOVERY-01/):
 *  - The REAL canonical seeder (DatabaseSeeder) provisions roles + permissions.
 *  - NO permission/role is created or granted by the tests beyond attaching an
 *    ALREADY-SEEDED role to a user (what an operator would do in production).
 *  - Group `uat-recovery-01-red`   = expected to FAIL on current main (defect reproduced).
 *    A RED test that starts passing means the defect was fixed (or the test is wrong).
 *  - Group `uat-recovery-01-char`  = characterization of CURRENT behaviour; each one is an
 *    Owner-decision item or a regression guard and is expected to PASS today.
 *  - Assertions are never weakened to pass: RED tests assert the EXPECTED behaviour.
 */
class UatRecovery01IdentityRbacTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
    }

    // ------------------------------------------------------------------ helpers

    /** Attach an already-seeded global role (no legacy users.role, like POST /api/users produces). */
    private function staff(string $seededRole, ?Tenant $tenant = null, array $attrs = []): User
    {
        $user = User::factory()->create(array_merge([
            'tenant_id' => ($tenant ?? $this->tenantA)->id,
            'role' => null,
        ], $attrs));
        $roleId = DB::table('roles')->where('name', $seededRole)->value('id');
        $this->assertNotNull($roleId, "seeded role '{$seededRole}' must exist");
        DB::table('user_roles')->insert([
            'user_id' => $user->id, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user;
    }

    private function project(?Tenant $tenant = null, array $attrs = []): Project
    {
        return Project::factory()->create(array_merge(['tenant_id' => ($tenant ?? $this->tenantA)->id], $attrs));
    }

    private function task(Project $project, ?User $assignee = null): Task
    {
        return Task::factory()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'assignee_id' => $assignee?->id,
            'status' => 'pending',
            'name' => 'UAT-R01 task',
        ]);
    }

    /** Web (session) request with a real CSRF token in session — CSRF is NOT bypassed. */
    private function web(User $u, string $method, string $uri, array $data = [])
    {
        return $this->actingAs($u)->withSession(['_token' => 'uat-r01'])->call($method, $uri, $data + ['_token' => 'uat-r01']);
    }

    private function api(User $u): array
    {
        Sanctum::actingAs($u);

        return ['X-Tenant-ID' => (string) $u->tenant_id];
    }

    // ================================================================== RED (defects)

    #[Group('uat-recovery-01-red')]
    public function test_D1_seed_contains_role_assign_permission(): void
    {
        $this->assertTrue(
            DB::table('permissions')->where('name', 'role.assign')->orWhere('code', 'role.assign')->exists(),
            'role.assign is required by 7 routes in src/RBAC/routes/api.php but is not in the canonical seed'
        );
    }

    #[Group('uat-recovery-01-red')]
    public function test_D1_system_admin_can_assign_a_seeded_role_via_api(): void
    {
        // Most favourable actor: BOTH legacy users.role=admin and the seeded DB role System Admin.
        $admin = $this->staff('System Admin', null, ['role' => 'admin']);
        $target = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => null]);
        $memberRole = DB::table('roles')->where('name', 'Project Member')->value('id');

        $headers = $this->api($admin);
        $res = $this->withHeaders($headers)->postJson("/api/v1/rbac/assignments/users/{$target->id}/roles", ['role_id' => $memberRole]);

        $this->assertContains($res->getStatusCode(), [200, 201], 'System Admin must be able to assign roles; got ' . $res->getStatusCode());
        $this->assertDatabaseHas('user_roles', ['user_id' => $target->id, 'role_id' => $memberRole]);
    }

    // Released from quarantine on integration: upstream GAP-098 now seeds the missing route permissions, so this passes unchanged.
    // (The original evidence-file side effect was removed so the test no longer writes into the repository; assertions unchanged.)
    #[Group('uat-recovery-01-red')]
    public function test_D3_every_rbac_route_permission_exists_in_canonical_seed(): void
    {
        $required = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $mw) {
                if (is_string($mw) && preg_match('/(?:RoleBasedAccessControlMiddleware|^rbac):([a-z0-9_.\-]+)$/i', $mw, $m)
                    && (str_contains($m[1], '.') || str_contains($m[1], '-'))) {
                    $required[$m[1]] = true;
                }
            }
        }
        $seeded = DB::table('permissions')->pluck('name')->filter()->all();
        $missing = array_values(array_diff(array_keys($required), $seeded));
        sort($missing);

        $this->assertSame([], $missing, 'Fail-closed gate: unseeded permissions make routes unreachable for every non-super_admin user');
    }

    #[Group('uat-recovery-01-red')]
    // QUARANTINED (OWNER-DECISION-REGISTER: O-2 / O-8 (parameterless rbac gate)). Assertions intentionally unchanged: this test FAILS today and documents an open Owner decision.
    // Excluded from ordinary CI by group only; see tests/Feature/Recovery/QUARANTINE.md for the removal conditions.
    #[Group('uat-recovery-01-quarantine')]
    public function test_D2_user_with_only_seeded_project_manager_role_passes_api_gate(): void
    {
        $pm = $this->staff('Project Manager'); // users.role NULL, exactly what POST /api/users creates
        $headers = $this->api($pm);
        $res = $this->withHeaders($headers)->getJson('/api/projects');

        $this->assertSame(200, $res->getStatusCode(), 'seeded Project Manager role must pass the generic rbac gate');
    }

    #[Group('uat-recovery-01-red')]
    public function test_D2_seeded_project_manager_can_submit_a_draft_document(): void
    {
        $pm = $this->staff('Project Manager');
        $project = $this->project(null, ['pm_id' => $pm->id]);
        $member = $this->staff('Project Member');

        $this->web($member, 'POST', '/app/documents', [
            'title' => 'UAT-R01 drawing', 'project_id' => $project->id, 'document_type' => 'drawing',
            'file' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\n%%EOF\n"),
        ])->assertStatus(302);
        $doc = Document::withoutGlobalScopes()->where('title', 'UAT-R01 drawing')->firstOrFail();

        $this->web($pm, 'POST', "/app/documents/{$doc->id}/submit");

        $doc->refresh();
        $this->assertNotSame('not-submitted', $doc->getRawOriginal('approval_status'), 'PM submit was rejected by DocumentPolicy::update legacy role names');
    }

    #[Group('uat-recovery-01-red')]
    // QUARANTINED (OWNER-DECISION-REGISTER: O-18 (Member task.view is seed-order dependent)). Assertions intentionally unchanged: this test FAILS today and documents an open Owner decision.
    // Excluded from ordinary CI by group only; see tests/Feature/Recovery/QUARANTINE.md for the removal conditions.
    #[Group('uat-recovery-01-quarantine')]
    public function test_D3_project_member_can_open_my_work(): void
    {
        $member = $this->staff('Project Member');
        $res = $this->actingAs($member)->get('/app/my-work');

        $this->assertSame(200, $res->getStatusCode(), 'route needs task.view; seeded Project Member holds task.read');
    }

    #[Group('uat-recovery-01-red')]
    public function test_TASK_OWNERSHIP_non_assignee_member_cannot_change_task_status(): void
    {
        $project = $this->project();
        $assignee = $this->staff('Project Member');
        $other = $this->staff('Project Member');
        $task = $this->task($project, $assignee);

        $this->web($other, 'PUT', "/app/tasks/{$task->id}", ['name' => 'UAT-R01 task', 'status' => 'in_progress']);

        $this->assertSame(
            'pending',
            Task::withoutGlobalScopes()->find($task->id)->status,
            'TaskPolicy::update (assignee / project PM / admin) exists but is not invoked by Web/Api TaskController::update'
        );
    }

    #[Group('uat-recovery-01-red')]
    // QUARANTINED (OWNER-DECISION-REGISTER: O-2 / O-8 (legacy users.role vs DB roles)). Assertions intentionally unchanged: this test FAILS today and documents an open Owner decision.
    // Excluded from ordinary CI by group only; see tests/Feature/Recovery/QUARANTINE.md for the removal conditions.
    #[Group('uat-recovery-01-quarantine')]
    public function test_D2_no_seeded_user_can_create_users_through_canonical_api(): void
    {
        $owner = User::where('email', 'admin+zena@zena.local')->firstOrFail(); // seeded tenant admin (users.role=admin)
        $headers = $this->api($owner);
        $res = $this->withHeaders($headers)->postJson('/api/users', [
            'name' => 'UAT-R01 new', 'email' => 'uat-r01-new@example.test',
            'password' => 'Zz!uat-r01-pass-9', 'password_confirmation' => 'Zz!uat-r01-pass-9',
        ]);

        $this->assertSame(201, $res->getStatusCode(), 'seeded tenant admin has users.role=admin but no DB role → user.create denied');
    }

    /**
     * assignSystemRole() writes the system_user_roles ledger while User::hasPermission()/roles() read
     * user_roles. S2 makes both rows land in one transaction. (Before S1/S2 this needed an injected
     * role.assign row just to reach the code path; S1 now seeds it, so no injection remains.).
     */
    #[Group('uat-recovery-01-red')]
    public function test_D1b_role_assigned_through_api_takes_effect_for_permission_checks(): void
    {
        $admin = $this->staff('System Admin', null, ['role' => 'admin']);
        $target = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => null]);
        $memberRole = DB::table('roles')->where('name', 'Project Member')->value('id');
        $headers = $this->api($admin);
        $this->withHeaders($headers)->postJson("/api/v1/rbac/assignments/users/{$target->id}/roles", ['role_id' => $memberRole])->assertSuccessful();

        $this->assertTrue($target->fresh()->hasPermission('task.read'), 'assigned role must grant its permissions (pivot used by write path must equal pivot used by read path)');
    }

    // ================================================ CONTROLS (root-cause isolation)
    // Each control changes EXACTLY ONE variable versus a RED test, proving that variable is the cause.
    // They deliberately inject the missing piece and are NOT evidence that the product works.

    #[Group('uat-recovery-01-control')]
    public function test_CONTROL_D1_role_assign_grant_is_what_enables_assignment(): void
    {
        // Single-variable control: detach ONLY role.assign from System Admin → the same request must be denied.
        $permId = DB::table('permissions')->where('name', 'role.assign')->value('id');
        $adminRoleId = DB::table('roles')->where('name', 'System Admin')->value('id');
        DB::table('role_permissions')->where('role_id', $adminRoleId)->where('permission_id', $permId)->delete();

        $admin = $this->staff('System Admin', null, ['role' => 'admin']);
        $target = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => null]);
        $memberRole = DB::table('roles')->where('name', 'Project Member')->value('id');
        $headers = $this->api($admin);
        $res = $this->withHeaders($headers)->postJson("/api/v1/rbac/assignments/users/{$target->id}/roles", ['role_id' => $memberRole]);

        $this->assertSame(403, $res->getStatusCode());
        $this->assertSame(0, DB::table('user_roles')->where('user_id', $target->id)->count());
        $this->assertSame(0, DB::table('system_user_roles')->where('user_id', $target->id)->count());
    }

    #[Group('uat-recovery-01-control')]
    public function test_CONTROL_D2_only_legacy_users_role_blocks_pm_api_gate_and_document_submit(): void
    {
        $pm = $this->staff('Project Manager', null, ['role' => 'project_manager']);
        $headers = $this->api($pm);
        $this->assertSame(200, $this->withHeaders($headers)->getJson('/api/projects')->getStatusCode(), 'legacy project_manager value satisfies the generic gate');

        $pm2 = $this->staff('Project Manager', null, ['role' => 'pm']);
        $project = $this->project(null, ['pm_id' => $pm2->id]);
        $member = $this->staff('Project Member');
        $this->web($member, 'POST', '/app/documents', [
            'title' => 'UAT-R01 control', 'project_id' => $project->id, 'document_type' => 'drawing',
            'file' => UploadedFile::fake()->createWithContent('c.pdf', "%PDF-1.4\n%%EOF\n"),
        ]);
        $doc = Document::withoutGlobalScopes()->where('title', 'UAT-R01 control')->firstOrFail();
        $this->web($pm2, 'POST', "/app/documents/{$doc->id}/submit");
        $doc->refresh();
        $this->assertNotSame('not-submitted', $doc->getRawOriginal('approval_status'), "legacy value 'pm' satisfies DocumentPolicy");
    }

    // ============================================== CHARACTERIZATION (current behaviour)

    /** OWNER DECISION O-3: project visibility. ProjectPolicy::view is tenant+permission only. */
    #[Group('uat-recovery-01-char')]
    public function test_CHAR_same_tenant_non_member_can_read_project_and_task_list(): void
    {
        $project = $this->project();
        $seen = $this->task($project);
        $outsider = $this->staff('Project Member');
        $this->assertSame(0, DB::table('project_team_members')->where('project_id', $project->id)->count());

        $this->actingAs($outsider)->get("/app/projects/{$project->id}")->assertOk();
        $this->actingAs($outsider)->get('/app/tasks')->assertOk()->assertSee($seen->name);
    }

    /** Regression guard (PASS today): cross-tenant read AND mutation denied, no state change. */
    #[Group('uat-recovery-01-char')]
    public function test_GUARD_cross_tenant_reads_and_writes_are_denied_without_mutation(): void
    {
        $project = $this->project();
        $task = $this->task($project, $this->staff('Project Member'));
        $originalName = $task->name;
        $foreignPm = $this->staff('Project Manager', $this->tenantB, ['role' => 'project_manager']);

        $this->assertContains($this->actingAs($foreignPm)->get("/app/projects/{$project->id}")->getStatusCode(), [403, 404]);
        $this->web($foreignPm, 'PUT', "/app/tasks/{$task->id}", ['name' => 'HACK', 'status' => 'completed']);
        $headers = $this->api($foreignPm);
        $r = $this->withHeaders($headers)->postJson("/api/dashboard/tasks/{$task->id}/assignments", ['user_id' => $foreignPm->id, 'role' => 'reviewer']);
        $this->assertContains($r->getStatusCode(), [403, 404]);

        $fresh = Task::withoutGlobalScopes()->find($task->id);
        $this->assertSame('pending', $fresh->status);
        $this->assertSame($originalName, $fresh->name);
        $this->assertSame(0, DB::table('task_assignments')->where('task_id', $task->id)->where('user_id', $foreignPm->id)->count());
    }

    /** Regression guard (PASS today): a Project Member cannot approve; no state change. */
    #[Group('uat-recovery-01-char')]
    public function test_GUARD_project_member_cannot_approve_document(): void
    {
        $project = $this->project();
        $member = $this->staff('Project Member');
        $this->web($member, 'POST', '/app/documents', [
            'title' => 'UAT-R01 approve-guard', 'project_id' => $project->id, 'document_type' => 'drawing',
            'file' => UploadedFile::fake()->createWithContent('b.pdf', "%PDF-1.4\n%%EOF\n"),
        ]);
        $doc = Document::withoutGlobalScopes()->where('title', 'UAT-R01 approve-guard')->firstOrFail();
        $before = [$doc->getRawOriginal('status'), $doc->getRawOriginal('approval_status')];

        $res = $this->web($member, 'POST', "/app/documents/{$doc->id}/approve", ['decision_note' => 'self']);

        $this->assertNotSame(200, $res->getStatusCode());
        $doc->refresh();
        $this->assertSame($before, [$doc->getRawOriginal('status'), $doc->getRawOriginal('approval_status')]);
        $this->assertSame(0, DB::table('document_approval_events')->where('document_id', $doc->id)->count());
    }

    /** OWNER DECISION O-6: RFI authority. Seed gives rfi.* to System Admin only. */
    #[Group('uat-recovery-01-char')]
    public function test_CHAR_project_manager_cannot_create_rfi_under_current_seed(): void
    {
        $pm = $this->staff('Project Manager');
        $project = $this->project(null, ['pm_id' => $pm->id]);
        $before = DB::table('rfis')->count();

        $this->web($pm, 'POST', '/operator/rfis', [
            'project_id' => $project->id, 'title' => 'x', 'description' => 'y', 'priority' => 'high',
        ]);

        $this->assertSame($before, DB::table('rfis')->count());
        // Integration note: the original second assertion (Project Manager holds no rfi.* permission) was falsified by upstream
        // seeds merged while this work was in flight (PM now holds rfi.* via GAP-030/GAP-098). It is retired; the creation-denied
        // assertion above is unchanged. See docs/audits/UAT-RECOVERY-01/85-retired-assertions.md.
    }
}
