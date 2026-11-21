<?php declare(strict_types=1);

namespace Tests\Feature\Recovery;

use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * UAT RECOVERY 04c — (1) remaining task-mutation surfaces, (2) CoreProject model bridge, (3) O-15 (PM self-appointment).
 * Real DatabaseSeeder; actors get only an already-seeded role. No permission is injected in this file.
 * Groups: -surface (reachable path reproduction), -bridge (App\Models\Task <-> Src\CoreProject\Models\Task), -o15 (OWNER_DECISION_REQUIRED).
 */
class UatRecovery04cSurfaceAndPmTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    protected User $pm1;       // PM of $projectX
    protected User $pm2;       // PM of $projectY (holds project.update, task.update, task.assign, task.create via the seeded PM role)
    private User $memberA;
    protected User $memberB;
    private User $admin;     // seeded System Admin, no relation to any project
    protected Project $projectX;
    private Project $projectY;
    private Project $unmanaged; // pm_id NULL — the state of EVERY project created before the D6 fix

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->pm1 = $this->actor('Project Manager');
        $this->pm2 = $this->actor('Project Manager');
        $this->memberA = $this->actor('Project Member');
        $this->memberB = $this->actor('Project Member');
        $this->admin = $this->actor('System Admin');
        $this->projectX = Project::factory()->create(['tenant_id' => $this->tenantA->id, 'pm_id' => $this->pm1->id]);
        $this->projectY = Project::factory()->create(['tenant_id' => $this->tenantA->id, 'pm_id' => $this->pm2->id]);
        $this->unmanaged = Project::factory()->create(['tenant_id' => $this->tenantA->id, 'pm_id' => null]);
    }

    // ------------------------------------------------------------------ helpers

    private function actor(?string $seededRole, ?string $legacy = null, ?Tenant $tenant = null): User
    {
        $u = User::factory()->create(['tenant_id' => ($tenant ?? $this->tenantA)->id, 'role' => $legacy]);
        if ($seededRole !== null) {
            DB::table('user_roles')->insert([
                'user_id' => $u->id, 'role_id' => DB::table('roles')->where('name', $seededRole)->value('id'),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $u;
    }

    private function task(Project $p, ?User $assignee = null): Task
    {
        return Task::factory()->create([
            'tenant_id' => $p->tenant_id, 'project_id' => $p->id, 'assignee_id' => $assignee?->id,
            'status' => 'pending', 'name' => 'S4c ' . bin2hex(random_bytes(2)), 'progress_percent' => 0,
        ]);
    }

    private function state(Task $t): string
    {
        return json_encode([
            DB::table('tasks')->where('id', $t->id)->first(),
            DB::table('task_assignments')->where('task_id', $t->id)->orderBy('id')->get(),
            DB::table('task_dependencies')->where('task_id', $t->id)->orWhere('dependency_id', $t->id)->orderBy('id')->get(),
        ]);
    }

    protected function api(User $u, string $m, string $uri, array $d = [])
    {
        Sanctum::actingAs($u);
        if (str_starts_with($uri, '/api/v1/')) {
            $this->actingAs($u, 'api');
        }

        return $this->withHeaders(['X-Tenant-ID' => (string) $u->tenant_id])->json($m, $uri, $d);
    }

    private function web(User $u, string $m, string $uri, array $d = [])
    {
        return $this->actingAs($u)->withSession(['_token' => 'uat-r04c'])->call($m, $uri, $d + ['_token' => 'uat-r04c']);
    }

    private function projectPayload(array $extra = []): array
    {
        return ['name' => 'S4c renamed', 'start_date' => '2026-12-01', 'end_date' => '2027-02-01'] + $extra;
    }

    // ================================================================== (1) creation-with-assignee surfaces

    /**
     * SAFE_BY_EXISTING_GUARD: task creation does not persist `assignee_id` (the model/controller drop it — UAT D5), so creating a task
     * can never be used to assign it. Pinned so that a future change that starts honouring assignee_id at creation must add the
     * owning-PM check (TaskPolicy::assign) at the same time.
     */
    #[Group('uat-recovery-04c-surface')]
    public function test_creating_a_task_never_assigns_it_so_creation_is_not_an_assignment_bypass(): void
    {
        foreach ([$this->pm1, $this->pm2] as $who) {
            $this->api($who, 'POST', '/api/zena/tasks', ['project_id' => $this->projectX->id, 'name' => 'created-by-' . $who->id, 'assignee_id' => $this->memberB->id]);
            $row = DB::table('tasks')->where('name', 'created-by-' . $who->id)->first();
            $this->assertNotNull($row);
            $this->assertNull($row->assignee_id, 'assignee_id is not honoured at creation');
            $this->assertSame(0, DB::table('task_assignments')->where('task_id', $row->id)->count());
        }
    }

    /** OWNER_DECISION_REQUIRED: task CREATION has no project constraint (any task.create holder, any project of the tenant; O-3 tenant-visible). */
    #[Group('uat-recovery-04c-o15')]
    public function test_OWNER_DECISION_task_creation_has_no_project_ownership_constraint_today(): void
    {
        $this->api($this->pm2, 'POST', '/api/zena/tasks', ['project_id' => $this->projectX->id, 'name' => 'foreign-project-create']);

        $this->assertSame($this->projectX->id, DB::table('tasks')->where('name', 'foreign-project-create')->value('project_id'));
    }

    #[Group('uat-recovery-04c-surface')]
    public function test_a_legacy_valued_member_cannot_create_or_assign_tasks_on_the_generic_gate_route(): void
    {
        $legacyMember = $this->actor('Project Member', 'team_member'); // passes the parameterless gate, holds no task.create/assign
        $before = DB::table('tasks')->count();

        $this->api($legacyMember, 'POST', '/api/tasks', ['project_id' => $this->projectX->id, 'name' => 'planted-by-member', 'assignee_id' => $this->memberB->id]);

        $this->assertSame($before, DB::table('tasks')->count(), 'a Member without task.create must not create tasks through api/tasks');
    }

    // ================================================================== (2) CoreProject <-> App model bridge

    #[Group('uat-recovery-04c-bridge')]
    public function test_both_models_address_the_same_physical_row_and_the_bridge_fails_closed(): void
    {
        $this->assertSame('tasks', (new \Src\CoreProject\Models\Task())->getTable());
        $this->assertSame('tasks', (new Task())->getTable());

        $t = $this->task($this->projectX, $this->memberA);
        $same = \Src\CoreProject\Models\Task::query()->where('tenant_id', $this->tenantA->id)->find($t->id);
        $this->assertSame((string) $t->id, (string) $same->id);
        $this->assertSame((string) $t->tenant_id, (string) $same->tenant_id);
        $this->assertSame((string) $t->project_id, (string) $same->project_id);
    }

    #[Group('uat-recovery-04c-bridge')]
    public function test_bridge_rejects_nonexistent_forged_cross_tenant_and_project_mismatched_ids_without_mutation(): void
    {
        $alias = (string) Str::ulid();
        DB::table('permissions')->insert(['id' => $alias, 'code' => 'task.edit', 'name' => 'task.edit', 'module' => 'task', 'action' => 'edit', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['Project Member', 'Project Manager', 'System Admin'] as $role) { // ADVERSARIAL fixture: future alias definition (production seed untouched)
            DB::table('role_permissions')->insert(['role_id' => DB::table('roles')->where('name', $role)->value('id'), 'permission_id' => $alias, 'created_at' => now(), 'updated_at' => now()]);
        }
        $mine = $this->task($this->projectX, $this->memberA);
        $foreignProject = Project::factory()->create(['tenant_id' => $this->tenantB->id]);
        $foreign = $this->task($foreignProject, null);
        $before = [$this->state($mine), $this->state($foreign)];

        // nonexistent id
        $this->assertContains($this->api($this->memberA, 'PUT', '/api/v1/tasks/' . Str::ulid(), ['name' => 'x'])->getStatusCode(), [403, 404]);
        // forged id of ANOTHER tenant's task, by an actor that fully owns tasks in its own tenant
        $this->assertContains($this->api($this->pm1, 'PUT', "/api/v1/tasks/{$foreign->id}", ['name' => 'HIJACK'])->getStatusCode(), [403, 404]);
        $this->assertContains($this->api($this->pm1, 'DELETE', "/api/v1/tasks/{$foreign->id}")->getStatusCode(), [403, 404]);
        // compatibility parameter naming another (managed-by-me) project must not change whose task is authorised
        $mismatch = $this->api($this->pm2, 'PUT', "/api/v1/tasks/{$mine->id}?project_id={$this->projectY->id}", ['name' => 'HIJACK']);
        $this->assertSame(403, $mismatch->getStatusCode(), 'authorisation follows the TASK\'s own project, never a caller-supplied project_id');

        $this->assertSame($before, [$this->state($mine), $this->state($foreign)]);
    }

    // ================================================================== (3) O-15 — pm_id self-appointment (OWNER_DECISION_REQUIRED)

    /**
     * O-15 (Owner-approved A-minimal, implemented in S4d): these three tests pinned the escalation path while it existed
     * (S4c "characterisation"). They now assert the REMEDIED behaviour: a project.update holder can no longer appoint
     * themselves PM of a PM-less project, so no task authority is acquired.
     */
    #[Group('uat-recovery-04c-o15')]
    public function test_O15_a_project_update_holder_can_no_longer_acquire_pm_authority_by_claiming_an_unmanaged_project(): void
    {
        $t = $this->task($this->unmanaged, $this->memberA);
        $before = $this->state($t);

        $this->api($this->pm2, 'PUT', "/api/projects/{$this->unmanaged->id}", $this->projectPayload(['pm_id' => $this->pm2->id]));

        $this->assertNull(DB::table('projects')->where('id', $this->unmanaged->id)->value('pm_id'), 'self-appointment is denied');
        $this->web($this->pm2, 'PUT', "/app/tasks/{$t->id}", ['name' => $t->name, 'status' => 'completed']);
        $this->assertSame($before, $this->state($t), 'no task authority was acquired');
    }

    #[Group('uat-recovery-04c-o15')]
    public function test_O15_system_admin_cannot_reacquire_task_authority_by_claiming_unmanaged_projects(): void
    {
        $t = $this->task($this->unmanaged, $this->memberA);
        $before = $this->state($t);

        $this->api($this->admin, 'PUT', "/api/projects/{$this->unmanaged->id}", $this->projectPayload(['pm_id' => $this->admin->id]));
        $this->web($this->admin, 'PUT', "/app/tasks/{$t->id}", ['name' => $t->name, 'status' => 'in_progress']);

        $this->assertNull(DB::table('projects')->where('id', $this->unmanaged->id)->value('pm_id'));
        $this->assertSame($before, $this->state($t));
    }

    /** Neither PM can win a race for a PM-less project; the claim path no longer exists. */
    #[Group('uat-recovery-04c-o15')]
    public function test_O15_no_pm_can_lock_out_another_by_claiming_a_pm_less_project(): void
    {
        $this->api($this->pm2, 'PUT', "/api/projects/{$this->unmanaged->id}", $this->projectPayload(['pm_id' => $this->pm2->id]));
        $this->api($this->pm1, 'PUT', "/api/projects/{$this->unmanaged->id}", $this->projectPayload(['pm_id' => $this->pm1->id]));

        $this->assertNull(DB::table('projects')->where('id', $this->unmanaged->id)->value('pm_id'));
    }

    /** Was RED before S4d (Owner-approved A-minimal): appointing a PM is not a side effect of editing project metadata. Now GREEN. */
    #[Group('uat-recovery-04c-o15')]
    public function test_O15_PROPOSED_a_plain_project_update_holder_cannot_appoint_themselves_pm(): void
    {
        $this->api($this->pm2, 'PUT', "/api/projects/{$this->unmanaged->id}", $this->projectPayload(['pm_id' => $this->pm2->id]));

        $this->assertNull(DB::table('projects')->where('id', $this->unmanaged->id)->value('pm_id'), 'PROPOSED rule: only the project creator / an explicit project-assignment authority may set the first PM');
    }

    #[Group('uat-recovery-04c-o15')]
    public function test_O15_guards_hold_today_other_actors_and_scenarios(): void
    {
        $t = $this->task($this->projectX, $this->memberA);
        $before = $this->state($t);
        // F: without changing pm_id the second PM has no authority
        $this->web($this->pm2, 'PUT', "/app/tasks/{$t->id}", ['name' => $t->name, 'status' => 'completed']);
        $this->api($this->pm2, 'POST', "/api/dashboard/tasks/{$t->id}/assignments", ['user_id' => $this->memberB->id, 'role' => 'assignee']);
        $this->assertSame($before, $this->state($t));
        // G: cannot set a DIFFERENT PM on a managed project it does not manage
        $this->api($this->pm2, 'PUT', "/api/projects/{$this->projectX->id}", $this->projectPayload(['pm_id' => $this->memberB->id]));
        $this->assertSame($this->pm1->id, DB::table('projects')->where('id', $this->projectX->id)->value('pm_id'));
        // H: cross-tenant PM
        $foreign = $this->actor('Project Manager', null, $this->tenantB);
        $this->api($this->pm2, 'PUT', "/api/projects/{$this->unmanaged->id}", $this->projectPayload(['pm_id' => $foreign->id]));
        $this->assertNull(DB::table('projects')->where('id', $this->unmanaged->id)->value('pm_id'));
        // I: existing PM hands over legitimately
        $this->api($this->pm1, 'PUT', "/api/projects/{$this->projectX->id}", $this->projectPayload(['pm_id' => $this->memberA->id]));
        $this->assertSame($this->memberA->id, DB::table('projects')->where('id', $this->projectX->id)->value('pm_id'));
        // B: a Member (no project.update) cannot change it at all
        $this->api($this->memberB, 'PUT', "/api/projects/{$this->unmanaged->id}", $this->projectPayload(['pm_id' => $this->memberB->id]));
        $this->assertNull(DB::table('projects')->where('id', $this->unmanaged->id)->value('pm_id'));
    }
}
