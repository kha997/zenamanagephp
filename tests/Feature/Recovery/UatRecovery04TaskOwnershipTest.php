<?php declare(strict_types=1);

namespace Tests\Feature\Recovery;

use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * UAT RECOVERY 04 — S4: task ownership / lifecycle authorization.
 *
 * Real DatabaseSeeder, actors receive only an already-seeded role. No permission is injected.
 * Proposed S4 contract (docs/audits/UAT-RECOVERY-01/30-recovery-04-s4-report.md):
 *   update/status/block/unblock allowed iff tenant matches AND
 *     (a) the user is an assignee (tasks.assignee_id OR a task_assignments row with role=assignee), OR
 *     (b) the user is the owning project's PM (projects.pm_id) and holds task.update, OR
 *     (c) the user holds BOTH task.update and task.delete (full task-management authority; today: System Admin).
 *   delete allowed iff tenant matches AND ((b-with-task.delete? no) — project PM per TaskPolicy::delete OR task.delete holder).
 * STATUS: APPLIED (Owner O-14 option A: 5 existing fixtures were given an assignee relation; no assertion changed).
 * Groups: -red = defect reproduced before the patch; -guard = must hold before and after; -owner = OWNER_DECISION_REQUIRED.
 */
class UatRecovery04TaskOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Project $project;
    private User $pm;          // seeded Project Manager, pm_id of $project
    private User $otherPm;     // seeded Project Manager of ANOTHER project
    private User $memberA;     // assignee via tasks.assignee_id
    private User $memberA2;    // assignee via task_assignments(role=assignee)
    private User $memberB;     // ordinary Project Member, not assigned
    private User $observer;    // Project Member with task_assignments(role=observer)
    private User $admin;       // seeded System Admin, DB role only (users.role NULL)
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->pm = $this->actor('Project Manager');
        $this->otherPm = $this->actor('Project Manager');
        $this->memberA = $this->actor('Project Member');
        $this->memberA2 = $this->actor('Project Member');
        $this->memberB = $this->actor('Project Member');
        $this->observer = $this->actor('Project Member');
        $this->admin = $this->actor('System Admin');

        $this->project = Project::factory()->create(['tenant_id' => $this->tenantA->id, 'pm_id' => $this->pm->id]);
        Project::factory()->create(['tenant_id' => $this->tenantA->id, 'pm_id' => $this->otherPm->id]);
        $this->task = $this->makeTask($this->memberA);
        $this->assignRow($this->task, $this->memberA2, 'assignee');
        $this->assignRow($this->task, $this->observer, 'observer');
    }

    // ----------------------------------------------------------------- helpers

    private function actor(?string $seededRole, ?string $legacy = null, ?Tenant $tenant = null): User
    {
        $user = User::factory()->create(['tenant_id' => ($tenant ?? $this->tenantA)->id, 'role' => $legacy]);
        if ($seededRole !== null) {
            DB::table('user_roles')->insert([
                'user_id' => $user->id,
                'role_id' => DB::table('roles')->where('name', $seededRole)->value('id'),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $user;
    }

    private function makeTask(?User $assignee, ?Project $project = null): Task
    {
        return Task::factory()->create([
            'tenant_id' => ($project ?? $this->project)->tenant_id,
            'project_id' => ($project ?? $this->project)->id,
            'assignee_id' => $assignee?->id,
            'status' => 'pending',
            'name' => 'S4 task ' . bin2hex(random_bytes(2)),
            'progress_percent' => 0,
        ]);
    }

    private function assignRow(Task $task, User $user, string $role): void
    {
        DB::table('task_assignments')->insert([
            'id' => (string) \Illuminate\Support\Str::ulid(), 'task_id' => $task->id, 'user_id' => $user->id, 'tenant_id' => $task->tenant_id,
            'role' => $role, 'status' => 'pending', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function snap(Task $t): array
    {
        $r = DB::table('tasks')->where('id', $t->id)->first();   // raw row: sees soft-deleted rows too

        return $r === null ? ['gone'] : [$r->name, $r->status, (int) $r->progress_percent, $r->blocked_at === null ? 'unblocked' : 'blocked', ($r->deleted_at ?? null) === null ? 'live' : 'deleted'];
    }

    private function web(User $u, string $method, string $uri, array $data = [])
    {
        return $this->actingAs($u)->withSession(['_token' => 'uat-r04'])->call($method, $uri, $data + ['_token' => 'uat-r04']);
    }

    private function api(User $u, string $method, string $uri, array $data = [])
    {
        Sanctum::actingAs($u);

        return $this->withHeaders(['X-Tenant-ID' => (string) $u->tenant_id])->json($method, $uri, $data);
    }

    /** Every endpoint that can mutate a task's fields/status, reachable by Project Member's seeded task.update. */
    private function mutationEndpoints(Task $t): array
    {
        $n = $t->name;

        return [
            'web PUT app/tasks'              => fn (User $u) => $this->web($u, 'PUT', "/app/tasks/{$t->id}", ['name' => $n, 'status' => 'in_progress', 'progress_percent' => 40]),
            'web block'                      => fn (User $u) => $this->web($u, 'POST', "/app/tasks/{$t->id}/block", ['blocker_note' => 'x']),
            'api/zena PUT'                   => fn (User $u) => $this->api($u, 'PUT', "/api/zena/tasks/{$t->id}", ['name' => $n, 'status' => 'in_progress', 'progress_percent' => 40]),
            'schedule POST'                  => fn (User $u) => $this->web($u, 'POST', "/operator/schedule/tasks/{$t->id}", ['project_id' => $t->project_id, 'name' => $n, 'status' => 'in_progress']),
        ];
    }

    // ----------------------------------------------------------------- RED (defect)

    #[Group('uat-recovery-04-red')]
    public function test_unassigned_member_cannot_change_status_or_complete_anyone_elses_task_on_any_endpoint(): void
    {
        foreach ($this->mutationEndpoints($this->task) as $label => $call) {
            $before = $this->snap($this->task);
            $call($this->memberB);
            $this->assertSame($before, $this->snap($this->task), "{$label}: Member B must not mutate Member A's task");
        }
        // completion specifically
        $this->web($this->memberB, 'PUT', "/app/tasks/{$this->task->id}", ['name' => $this->task->name, 'status' => 'completed', 'progress_percent' => 100]);
        $this->assertSame('pending', $this->snap($this->task)[1]);
    }

    #[Group('uat-recovery-04-red')]
    public function test_observer_and_other_project_pm_cannot_mutate(): void
    {
        foreach ([$this->observer, $this->otherPm] as $who) {
            foreach ($this->mutationEndpoints($this->task) as $label => $call) {
                $before = $this->snap($this->task);
                $call($who);
                $this->assertSame($before, $this->snap($this->task), "{$label}: {$who->id}");
            }
        }
    }

    #[Group('uat-recovery-04-red')]
    public function test_legacy_role_strings_cannot_elevate_a_member(): void
    {
        foreach (['admin', 'pm', 'project_manager', 'super_admin', 'designer'] as $legacy) {
            $m = $this->actor('Project Member', $legacy);
            foreach ($this->mutationEndpoints($this->task) as $label => $call) {
                $before = $this->snap($this->task);
                $call($m);
                $this->assertSame($before, $this->snap($this->task), "{$label}: users.role={$legacy} Member");
            }
        }
    }

    #[Group('uat-recovery-04-red')]
    public function test_legacy_generic_gate_task_endpoint_is_also_guarded(): void
    {
        // api/tasks/{id}: behind the parameterless gate only. A Member that carries the legacy value passes the gate.
        $m = $this->actor('Project Member', 'team_member');
        $before = $this->snap($this->task);

        $this->api($m, 'PUT', "/api/tasks/{$this->task->id}", ['name' => $this->task->name, 'status' => 'completed']);
        $this->api($m, 'DELETE', "/api/tasks/{$this->task->id}");

        $this->assertSame($before, $this->snap($this->task));
    }

    // ----------------------------------------------------------------- GUARDS (must hold before and after)

    #[Group('uat-recovery-04-guard')]
    public function test_assignees_still_update_their_own_task(): void
    {
        foreach ([$this->memberA, $this->memberA2] as $who) {
            $t = $this->makeTask($who === $this->memberA ? $this->memberA : null);
            if ($who === $this->memberA2) {
                $this->assignRow($t, $who, 'assignee');
            }
            $this->web($who, 'PUT', "/app/tasks/{$t->id}", ['name' => $t->name, 'status' => 'in_progress', 'progress_percent' => 40]);
            $this->assertSame(['in_progress', 40], [$this->snap($t)[1], $this->snap($t)[2]], 'assignee updates own task');
            $this->web($who, 'POST', "/app/tasks/{$t->id}/block", ['blocker_note' => 'chờ bản vẽ']);
            $this->assertSame('blocked', $this->snap($t)[3]);
            $this->web($who, 'POST', "/app/tasks/{$t->id}/unblock");
            $this->assertSame('unblocked', $this->snap($t)[3]);
        }
    }

    #[Group('uat-recovery-04-guard')]
    public function test_project_pm_and_explicitly_related_system_admin_can_manage_inside_their_scope(): void
    {
        // O-10 (S4b): System Admin has no authority from task.delete; it acts only where it is the project PM or an assignee.
        $adminProject = Project::factory()->create(['tenant_id' => $this->tenantA->id, 'pm_id' => $this->admin->id]);
        foreach ([[$this->pm, $this->project], [$this->admin, $adminProject]] as [$who, $proj]) {
            $t = $this->makeTask($this->memberA, $proj);
            $this->web($who, 'PUT', "/app/tasks/{$t->id}", ['name' => $t->name, 'status' => 'in_progress', 'progress_percent' => 10]);
            $this->assertSame('in_progress', $this->snap($t)[1]);
            $this->api($who, 'PUT', "/api/zena/tasks/{$t->id}", ['name' => $t->name, 'status' => 'completed', 'progress_percent' => 100]);
            $this->assertSame('completed', $this->snap($t)[1]);
        }
    }

    #[Group('uat-recovery-04-guard')]
    public function test_cross_tenant_actors_never_mutate_even_with_every_privilege(): void
    {
        $foreignAdmin = $this->actor('System Admin', 'admin', $this->tenantB);
        $foreignPm = $this->actor('Project Manager', 'project_manager', $this->tenantB);
        foreach ([$foreignAdmin, $foreignPm] as $who) {
            foreach ($this->mutationEndpoints($this->task) as $label => $call) {
                $before = $this->snap($this->task);
                $call($who);
                $this->assertSame($before, $this->snap($this->task), "{$label}: cross-tenant");
            }
            $this->api($who, 'DELETE', "/api/zena/tasks/{$this->task->id}");
            $this->assertSame('live', $this->snap($this->task)[4]);
        }
    }

    #[Group('uat-recovery-04-guard')]
    public function test_role_less_user_and_unauthorised_delete_are_denied(): void
    {
        $nobody = $this->actor(null);
        $before = $this->snap($this->task);
        foreach ($this->mutationEndpoints($this->task) as $call) {
            $call($nobody);
        }
        $this->api($this->memberA, 'DELETE', "/api/zena/tasks/{$this->task->id}");   // assignee lacks task.delete route permission
        $this->api($this->pm, 'DELETE', "/api/zena/tasks/{$this->task->id}");        // PM lacks task.delete (no grant added)
        $this->assertSame($before, $this->snap($this->task));
    }

    /** Guards against false-green: a denial must be a clean 403, never a server error that merely "didn't mutate". */
    #[Group('uat-recovery-04-guard')]
    public function test_denials_are_clean_403_and_never_server_errors(): void
    {
        $t = $this->task;
        $this->assertSame(403, $this->api($this->memberB, 'PUT', "/api/zena/tasks/{$t->id}", ['name' => $t->name, 'status' => 'completed'])->getStatusCode());
        $legacyMember = $this->actor('Project Member', 'team_member'); // passes the parameterless gate, not the task policy
        $this->assertSame(403, $this->api($legacyMember, 'PUT', "/api/tasks/{$t->id}", ['name' => $t->name])->getStatusCode());
        $this->assertSame(403, $this->api($legacyMember, 'DELETE', "/api/tasks/{$t->id}")->getStatusCode());
        $this->assertSame(403, $this->web($this->memberB, 'POST', "/app/tasks/{$t->id}/block", ['blocker_note' => 'x'])->getStatusCode());
        $this->assertSame(403, $this->web($this->memberB, 'POST', "/app/tasks/{$t->id}/unblock")->getStatusCode());
        $res = $this->web($this->memberB, 'PUT', "/app/tasks/{$t->id}", ['name' => $t->name, 'status' => 'completed']);
        $this->assertLessThan(500, $res->getStatusCode());
        $this->assertNotNull(session('error'), 'web update surfaces the denial to the user');
    }

    // ----------------------------------------------------------------- OWNER_DECISION_REQUIRED (pin current, decide nothing)

    /** No transition table, no completion prerequisites exist in the task model/controllers today. */
    #[Group('uat-recovery-04-owner')]
    public function test_OWNER_DECISION_lifecycle_has_no_transition_or_completion_guards_today(): void
    {
        $t = $this->makeTask($this->memberA);

        $this->web($this->memberA, 'PUT', "/app/tasks/{$t->id}", ['name' => $t->name, 'status' => 'completed', 'progress_percent' => 0]);
        $this->assertSame('completed', $this->snap($t)[1], 'assignee may complete at 0% (no approved completion rule)');
        $this->web($this->memberA, 'PUT', "/app/tasks/{$t->id}", ['name' => $t->name, 'status' => 'pending']);
        $this->assertSame('pending', $this->snap($t)[1], 'completed -> pending reopen has no guard');
    }

    /** Task rows with neither an assignee nor a project PM are manageable only by full task managers. */
    #[Group('uat-recovery-04-owner')]
    public function test_OWNER_DECISION_unassigned_task_in_a_project_without_pm_is_unmanageable_until_a_pm_is_set(): void
    {
        $noPm = Project::factory()->create(['tenant_id' => $this->tenantA->id, 'pm_id' => null]);
        $t = $this->makeTask(null, $noPm);

        $this->web($this->pm, 'PUT', "/app/tasks/{$t->id}", ['name' => $t->name, 'status' => 'in_progress']);
        $this->assertSame('pending', $this->snap($t)[1], 'a PM who is not the project PM cannot manage it (pm_id is not set by project creation — UAT D6)');
        $this->web($this->admin, 'PUT', "/app/tasks/{$t->id}", ['name' => $t->name, 'status' => 'in_progress']);
        $this->assertSame('pending', $this->snap($t)[1], 'O-10 (S4b): even System Admin / task.delete holders have no authority over an unrelated task');
    }
}
