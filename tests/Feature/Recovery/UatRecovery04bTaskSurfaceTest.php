<?php

declare(strict_types=1);

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
 * UAT RECOVERY 04b — remaining task-mutation surfaces + project pm_id persistence (D6, pm_id only).
 * Owner: O-10 (task.delete implies nothing), O-11 (pm_id), O-12 (no new lifecycle rules), O-13 (same boundaries everywhere).
 *
 * Contract under test (operation-specific, tenant-bound, no legacy users.role, no admin bypass):
 *   update/status/block/dependencies : assignee  OR  owning-project PM holding task.update
 *   assign/reassign/unassign         : owning-project PM holding task.assign
 *   delete                           : owning-project PM holding task.update  OR  holder of task.delete (independent delete authority)
 * Real DatabaseSeeder; actors receive only an already-seeded role. The ONLY permission injected anywhere is the explicitly labelled
 * adversarial `task.edit` alias simulation (group uat-recovery-04b-alias).
 */
class UatRecovery04bTaskSurfaceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private Project $project;       // PM = $pm

    private Project $otherProject;  // PM = $otherPm

    private User $pm;

    private User $otherPm;

    private User $memberA;          // assignee of $task

    private User $memberB;          // unrelated Member

    private User $observer;

    private User $admin;           // seeded System Admin (holds task.delete, task.assign, task.update, ...), NOT pm/assignee

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
        $this->memberB = $this->actor('Project Member');
        $this->observer = $this->actor('Project Member');
        $this->admin = $this->actor('System Admin');
        $this->project = Project::factory()->create(['tenant_id' => $this->tenantA->id, 'pm_id' => $this->pm->id]);
        $this->otherProject = Project::factory()->create(['tenant_id' => $this->tenantA->id, 'pm_id' => $this->otherPm->id]);
        $this->task = $this->makeTask($this->memberA);
        $this->assignRow($this->task, $this->observer, 'observer');
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

    private function makeTask(?User $assignee, ?Project $project = null): Task
    {
        $p = $project ?? $this->project;

        return Task::factory()->create([
            'tenant_id' => $p->tenant_id, 'project_id' => $p->id, 'assignee_id' => $assignee?->id,
            'status' => 'pending', 'name' => 'S4b ' . bin2hex(random_bytes(2)), 'progress_percent' => 0,
        ]);
    }

    private function assignRow(Task $t, User $u, string $role): string
    {
        $id = (string) Str::ulid();
        DB::table('task_assignments')->insert([
            'id' => $id, 'task_id' => $t->id, 'user_id' => $u->id, 'tenant_id' => $t->tenant_id, 'role' => $role,
            'status' => 'pending', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /** Full persisted picture: the task row, its assignment rows, dependency rows, and the project's pm_id. */
    private function state(Task $t): string
    {
        return json_encode([
            DB::table('tasks')->where('id', $t->id)->first(),
            DB::table('task_assignments')->where('task_id', $t->id)->orderBy('id')->get(),
            DB::table('task_dependencies')->where('task_id', $t->id)->orWhere('dependency_id', $t->id)->orderBy('id')->get(),
        ]);
    }

    private function web(User $u, string $m, string $uri, array $d = [])
    {
        return $this->actingAs($u)->withSession(['_token' => 'uat-r04b'])->call($m, $uri, $d + ['_token' => 'uat-r04b']);
    }

    private function api(User $u, string $m, string $uri, array $d = [])
    {
        Sanctum::actingAs($u);
        if (str_contains($uri, '/v1/')) {
            $this->actingAs($u, 'api'); // v1 routes authenticate with the `api` guard (Authenticate:api)
        }

        return $this->withHeaders(['X-Tenant-ID' => (string) $u->tenant_id])->json($m, $uri, $d);
    }

    /** Legacy-valued copies so the actor also passes the (unchanged, O-8) parameterless gate on legacy routes. */
    private function gated(string $seededRole, string $legacy, ?Tenant $tenant = null, ?Project $pmOf = null): User
    {
        $u = $this->actor($seededRole, $legacy, $tenant);
        if ($pmOf) {
            $pmOf->forceFill(['pm_id' => $u->id])->save();
        }

        return $u;
    }

    // ============================================================ O-10: task.delete implies nothing

    #[Group('uat-recovery-04b-red')]
    public function test_O10_task_delete_holder_cannot_update_assign_status_or_manage_dependencies_without_a_relation(): void
    {
        $this->assertTrue($this->admin->hasPermission('task.delete'), 'precondition: seeded System Admin holds task.delete');
        $t = $this->task;
        $before = $this->state($t);
        $dep = $this->makeTask($this->memberA);

        $this->web($this->admin, 'PUT', "/app/tasks/{$t->id}", ['name' => $t->name, 'status' => 'completed', 'progress_percent' => 100]);
        $this->web($this->admin, 'POST', "/app/tasks/{$t->id}/block", ['blocker_note' => 'x']);
        $this->api($this->admin, 'PUT', "/api/zena/tasks/{$t->id}", ['name' => $t->name, 'status' => 'completed']);
        $this->api($this->admin, 'PATCH', "/api/zena/tasks/{$t->id}/status", ['status' => 'done']);
        $this->api($this->admin, 'POST', "/api/zena/tasks/{$t->id}/dependencies", ['dependency_id' => $dep->id]);

        $this->assertSame($before, $this->state($t), 'task.delete (and every other System Admin permission) grants no object-level authority');
    }

    #[Group('uat-recovery-04b-guard')]
    public function test_O10_independent_delete_authority_is_preserved(): void
    {
        $t = $this->makeTask($this->memberA);
        $this->api($this->admin, 'DELETE', "/api/zena/tasks/{$t->id}");
        $this->assertNull(DB::table('tasks')->where('id', $t->id)->first(), 'task.delete holder may still delete (independent operation)');

        $mine = $this->makeTask($this->memberA);
        $this->api($this->memberA, 'DELETE', "/api/zena/tasks/{$mine->id}"); // assignee, no task.delete / not PM
        $this->assertNotNull(DB::table('tasks')->where('id', $mine->id)->first());
    }

    // ============================================================ explicit System Admin authority = a real relation, tenant bound

    #[Group('uat-recovery-04b-guard')]
    public function test_system_admin_acts_only_where_explicitly_related_and_never_across_tenants(): void
    {
        $this->otherProject->forceFill(['pm_id' => $this->admin->id])->save();      // explicit: admin is made PM of ONE project
        $own = $this->makeTask($this->memberA, $this->otherProject);
        $foreign = $this->task;

        $this->web($this->admin, 'PUT', "/app/tasks/{$own->id}", ['name' => $own->name, 'status' => 'in_progress']);
        $this->assertSame('in_progress', DB::table('tasks')->where('id', $own->id)->value('status'));

        $before = $this->state($foreign);
        $this->web($this->admin, 'PUT', "/app/tasks/{$foreign->id}", ['name' => $foreign->name, 'status' => 'in_progress']);
        $this->assertSame($before, $this->state($foreign));

        $foreignTenantAdmin = $this->actor('System Admin', 'admin', $this->tenantB);
        $foreignTenantProject = Project::factory()->create(['tenant_id' => $this->tenantB->id, 'pm_id' => $foreignTenantAdmin->id]);
        $this->web($foreignTenantAdmin, 'PUT', "/app/tasks/{$foreign->id}", ['name' => $foreign->name, 'status' => 'completed']);
        $this->assertSame($before, $this->state($foreign), 'tenant A task unreachable from tenant B even for its own PM');
        $this->assertNotNull($foreignTenantProject->id);
    }

    // ============================================================ assignment surfaces

    /** @return array<string, array{0:string,1:string,2:callable}> */
    private function assignmentCalls(Task $t, User $target, ?string $assignmentId): array
    {
        return [
            'dashboard store' => fn (User $u) => $this->api($u, 'POST', "/api/dashboard/tasks/{$t->id}/assignments", ['user_id' => $target->id, 'role' => 'assignee', 'split_percent' => 100]),
            'task-assignments store' => fn (User $u) => $this->api($u, 'POST', '/api/task-assignments', ['task_id' => $t->id, 'user_id' => $target->id, 'role' => 'assignee', 'split_percent' => 100]),
            'dashboard update' => fn (User $u) => $this->api($u, 'PUT', "/api/dashboard/assignments/{$assignmentId}", ['role' => 'reviewer']),
            'task-assignments update' => fn (User $u) => $this->api($u, 'PUT', "/api/task-assignments/{$assignmentId}", ['role' => 'reviewer']),
            'dashboard destroy' => fn (User $u) => $this->api($u, 'DELETE', "/api/dashboard/assignments/{$assignmentId}"),
            'v1 CoreProject store' => fn (User $u) => $this->api($u, 'POST', '/api/v1/task-assignments', ['task_id' => $t->id, 'user_id' => $target->id, 'role' => 'assignee', 'split_percent' => 100]),
            'v1 CoreProject update' => fn (User $u) => $this->api($u, 'PUT', "/api/v1/task-assignments/{$assignmentId}", ['role' => 'reviewer', 'split_percent' => 10]),
            'v1 CoreProject destroy' => fn (User $u) => $this->api($u, 'DELETE', "/api/v1/task-assignments/{$assignmentId}"),
        ];
    }

    #[Group('uat-recovery-04b-red')]
    public function test_unauthorised_actors_cannot_assign_reassign_or_unassign_on_any_surface(): void
    {
        $t = $this->task;
        $rowId = $this->assignRow($t, $this->memberA, 'assignee');
        $denied = [
            'Member B (legacy team_member)' => $this->actor('Project Member', 'team_member'),
            'assignee Member A (legacy)' => $this->actor('Project Member', 'team_member'),
            'observer (legacy)' => $this->actor('Project Member', 'team_member'),
            'other-project PM' => $this->gated('Project Manager', 'project_manager'),
            'System Admin, no relation' => $this->gated('System Admin', 'admin'),
            'cross-tenant PM' => $this->gated('Project Manager', 'project_manager', $this->tenantB),
        ];
        // make the "assignee"/"observer" legacy twins genuinely related to the task
        $t->forceFill(['assignee_id' => $denied['assignee Member A (legacy)']->id])->save();
        $this->assignRow($t, $denied['observer (legacy)'], 'observer');

        foreach ($denied as $who => $user) {
            foreach ($this->assignmentCalls($t, $this->memberB, $rowId) as $label => $call) {
                $before = $this->state($t);
                $res = $call($user);
                $this->assertSame($before, $this->state($t), "{$who} / {$label}: no persisted change");
                if ($label !== 'task-assignments store') { // pre-existing: resource POST api/task-assignments has no {taskId} => ArgumentCountError (500) for everyone
                    $this->assertLessThan(500, $res->getStatusCode(), "{$who} / {$label}: clean denial");
                }
            }
        }
    }

    #[Group('uat-recovery-04b-guard')]
    public function test_owning_project_pm_is_authorised_on_the_assignment_surfaces(): void
    {
        $t = $this->makeTask(null);
        $pm = $this->gated('Project Manager', 'project_manager', null, $this->project);
        $this->project->forceFill(['pm_id' => $pm->id])->save();

        $res = $this->api($pm, 'POST', "/api/dashboard/tasks/{$t->id}/assignments", ['user_id' => $this->memberB->id, 'role' => 'assignee', 'split_percent' => 100]);

        $this->assertSame(201, $res->getStatusCode());
        $this->assertSame(1, DB::table('task_assignments')->where('task_id', $t->id)->where('user_id', $this->memberB->id)->count());
        // duplicate is rejected without a second row
        $dup = $this->api($pm, 'POST', "/api/dashboard/tasks/{$t->id}/assignments", ['user_id' => $this->memberB->id, 'role' => 'assignee']);
        $this->assertGreaterThanOrEqual(400, $dup->getStatusCode());
        $this->assertSame(1, DB::table('task_assignments')->where('task_id', $t->id)->where('user_id', $this->memberB->id)->count());
        // nonexistent / cross-tenant user
        $this->assertGreaterThanOrEqual(400, $this->api($pm, 'POST', "/api/dashboard/tasks/{$t->id}/assignments", ['user_id' => (string) Str::ulid(), 'role' => 'assignee'])->getStatusCode());
        $stranger = $this->actor('Project Member', null, $this->tenantB);
        $this->api($pm, 'POST', "/api/dashboard/tasks/{$t->id}/assignments", ['user_id' => $stranger->id, 'role' => 'reviewer']);
        $this->assertSame(0, DB::table('task_assignments')->where('task_id', $t->id)->where('user_id', $stranger->id)->count(), 'assignment must not cross tenants');
    }

    #[Group('uat-recovery-04b-red')]
    public function test_assignment_does_not_grant_rbac_permissions_or_ownership_of_other_tasks(): void
    {
        $t = $this->makeTask(null);
        $pm = $this->gated('Project Manager', 'project_manager', null, $this->project);
        $this->project->forceFill(['pm_id' => $pm->id])->save();
        $perms = fn (User $u) => DB::table('role_permissions')->join('user_roles', 'user_roles.role_id', '=', 'role_permissions.role_id')->where('user_roles.user_id', $u->id)->count();
        $before = $perms($this->memberB);

        $this->api($pm, 'POST', "/api/dashboard/tasks/{$t->id}/assignments", ['user_id' => $this->memberB->id, 'role' => 'assignee', 'split_percent' => 100]);

        $this->assertSame($before, $perms($this->memberB));
        $other = $this->task;
        $snap = $this->state($other);
        $this->web($this->memberB, 'PUT', "/app/tasks/{$other->id}", ['name' => $other->name, 'status' => 'completed']);
        $this->assertSame($snap, $this->state($other), 'being assigned to one task confers nothing on another');
    }

    #[Group('uat-recovery-04b-red')]
    public function test_coreproject_assignment_accepts_ulid_identifiers(): void
    {
        $t = $this->makeTask(null);
        $pm = $this->gated('Project Manager', 'project_manager', null, $this->project);
        $this->project->forceFill(['pm_id' => $pm->id])->save();

        $res = $this->api($pm, 'POST', '/api/v1/task-assignments', ['task_id' => $t->id, 'user_id' => $this->memberB->id, 'role' => 'assignee', 'split_percent' => 50]);

        $this->assertNotSame(422, $res->getStatusCode(), 'ULID task_id/user_id must pass validation (was `integer`)');
        $this->assertNotSame(403, $res->getStatusCode(), 'owning-project PM is authorised');
    }

    // ============================================================ status/other mutation surfaces

    #[Group('uat-recovery-04b-red')]
    public function test_bulk_status_endpoint_enforces_the_same_boundary_per_task(): void
    {
        $mine = $this->makeTask($this->memberA);
        $bulkAdmin = $this->gated('System Admin', 'admin', null, $this->otherProject);   // legacy value only to pass the (unchanged, O-8) parameterless gate
        $related = $this->makeTask($this->memberA, $this->otherProject);   // bulkAdmin is its PM
        $foreignTenantProject = Project::factory()->create(['tenant_id' => $this->tenantB->id]);
        $foreign = $this->makeTask(null, $foreignTenantProject);
        $unrelatedBefore = $this->state($mine);
        $foreignBefore = $this->state($foreign);

        $this->api($bulkAdmin, 'POST', '/api/auth/bulk/tasks/update-status', ['task_ids' => [$mine->id, $related->id, $foreign->id], 'status' => 'completed']);

        $this->assertSame($unrelatedBefore, $this->state($mine), 'task with no relation to the acting admin is untouched');
        $this->assertSame($foreignBefore, $this->state($foreign), 'cross-tenant task untouched');
        $this->assertSame('completed', DB::table('tasks')->where('id', $related->id)->value('status'), 'authorised task is updated');
    }

    // ============================================================ future alias activation (ADVERSARIAL FIXTURE — not a production grant)

    private function activateTaskEditAliasForMembers(): void
    {
        $id = (string) Str::ulid();
        DB::table('permissions')->insert(['id' => $id, 'code' => 'task.edit', 'name' => 'task.edit', 'module' => 'task', 'action' => 'edit', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['Project Member', 'Project Manager', 'System Admin'] as $role) {
            DB::table('role_permissions')->insert(['role_id' => DB::table('roles')->where('name', $role)->value('id'), 'permission_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    #[Group('uat-recovery-04b-alias')]
    public function test_defining_the_task_edit_alias_cannot_bypass_ownership_on_coreproject_routes(): void
    {
        $this->activateTaskEditAliasForMembers(); // simulates a future decision to define the alias; production seed untouched
        $t = $this->task;
        $before = $this->state($t);

        foreach ([$this->memberB, $this->otherPm, $this->admin, $this->observer] as $who) {
            $this->api($who, 'PUT', "/api/v1/tasks/{$t->id}", ['name' => 'HIJACK', 'status' => 'completed']);
            $this->api($who, 'PATCH', "/api/v1/tasks/{$t->id}", ['name' => 'HIJACK']);
            $this->api($who, 'PUT', "/api/v1/work-template/projects/{$t->project_id}/tasks/{$t->id}", ['name' => 'HIJACK']);
            $this->api($who, 'PUT', "/api/v1/work-template/projects/{$t->project_id}/tasks/{$t->id}/progress", ['progress_percent' => 100]);
            $this->api($who, 'POST', "/api/v1/work-template/projects/{$t->project_id}/tasks/{$t->id}/toggle-conditional", ['is_enabled' => false]);
        }
        $this->assertSame($before, $this->state($t), 'alias activation alone must not open any task mutation');

        $foreignAdmin = $this->actor('System Admin', 'admin', $this->tenantB);
        $this->api($foreignAdmin, 'PUT', "/api/v1/tasks/{$t->id}", ['name' => 'HIJACK']);
        $this->assertSame($before, $this->state($t));
    }

    #[Group('uat-recovery-04b-alias')]
    public function test_alias_activation_still_lets_the_legitimate_actors_through_authorization(): void
    {
        $this->activateTaskEditAliasForMembers();
        foreach ([$this->memberA, $this->pm] as $who) {
            $res = $this->api($who, 'PUT', "/api/v1/tasks/{$this->task->id}", ['name' => $this->task->name]);
            $this->assertNotSame(403, $res->getStatusCode(), 'assignee / owning PM are not rejected by the ownership check');
        }
    }

    // ============================================================ S4 surfaces must stay protected (regression of S4)

    #[Group('uat-recovery-04b-guard')]
    public function test_s4_surfaces_remain_protected_and_legitimate_workflows_work(): void
    {
        $t = $this->task;
        $before = $this->state($t);
        $this->web($this->memberB, 'PUT', "/app/tasks/{$t->id}", ['name' => $t->name, 'status' => 'completed']);
        $this->web($this->memberB, 'POST', "/app/tasks/{$t->id}/block", ['blocker_note' => 'x']);
        $this->api($this->otherPm, 'PUT', "/api/zena/tasks/{$t->id}", ['name' => $t->name, 'status' => 'completed']);
        $this->web($this->memberB, 'POST', "/operator/schedule/tasks/{$t->id}", ['project_id' => $t->project_id, 'name' => $t->name, 'status' => 'completed']);
        $this->assertSame($before, $this->state($t));

        $this->web($this->memberA, 'PUT', "/app/tasks/{$t->id}", ['name' => $t->name, 'status' => 'in_progress', 'progress_percent' => 30]);   // assignee
        $this->assertSame('in_progress', DB::table('tasks')->where('id', $t->id)->value('status'));
        $this->web($this->pm, 'PUT', "/app/tasks/{$t->id}", ['name' => $t->name, 'status' => 'completed', 'progress_percent' => 100]);        // owning PM
        $this->assertSame('completed', DB::table('tasks')->where('id', $t->id)->value('status'));
    }

    // ============================================================ D6 — pm_id persistence (O-11)

    private const PROJECT = ['name' => 'S4b project', 'start_date' => '2026-12-01', 'end_date' => '2027-02-01'];

    #[Group('uat-recovery-04b-red')]
    public function test_project_creation_persists_an_authorised_pm_id_through_api_and_web(): void
    {
        $creator = $this->actor('Project Manager');
        $res = $this->api($creator, 'POST', '/api/projects', self::PROJECT + ['pm_id' => $this->pm->id]);
        $this->assertSame(201, $res->getStatusCode());
        $id = DB::table('projects')->where('name', 'S4b project')->value('id');
        $this->assertSame($this->pm->id, DB::table('projects')->where('id', $id)->value('pm_id'), 'API create persists pm_id');
        $reader = $this->actor('Project Manager', 'project_manager'); // legacy value only to pass the parameterless gate of the read endpoints
        $read = $this->api($reader, 'GET', '/api/projects?per_page=100'); // (GET api/projects/{id} additionally requires legacy Admin/team membership)
        $this->assertSame(200, $read->getStatusCode());
        $row = collect($read->json('data') ?? [])->firstWhere('id', $id);
        $this->assertNotNull($row, 'project visible through the canonical list API');
        $this->assertSame($this->pm->id, $row['pm_id'] ?? null, 'canonical API read-back');

        $this->web($creator, 'POST', '/app/projects', ['name' => 'S4b web project', 'start_date' => '2026-12-01', 'end_date' => '2027-02-01', 'pm_id' => $creator->id]);
        $this->assertSame($creator->id, DB::table('projects')->where('name', 'S4b web project')->value('pm_id'), 'web create persists pm_id');
    }

    #[Group('uat-recovery-04b-red')]
    public function test_creator_pm_can_then_manage_tasks_of_the_project_and_another_pm_cannot(): void
    {
        $creator = $this->actor('Project Manager');
        $this->web($creator, 'POST', '/app/projects', ['name' => 'S4b flow', 'start_date' => '2026-12-01', 'end_date' => '2027-02-01', 'pm_id' => $creator->id]);
        $projectId = DB::table('projects')->where('name', 'S4b flow')->value('id');
        $this->web($creator, 'POST', '/app/tasks', ['project_id' => $projectId, 'name' => 'S4b flow task']);
        $taskId = DB::table('tasks')->where('name', 'S4b flow task')->value('id');
        $this->assertNotNull($taskId);

        $this->web($creator, 'PUT', "/app/tasks/{$taskId}", ['name' => 'S4b flow task', 'status' => 'in_progress']);
        $this->assertSame('in_progress', DB::table('tasks')->where('id', $taskId)->value('status'), 'project PM manages');
        $this->web($this->otherPm, 'PUT', "/app/tasks/{$taskId}", ['name' => 'S4b flow task', 'status' => 'completed']);
        $this->assertSame('in_progress', DB::table('tasks')->where('id', $taskId)->value('status'), 'other PM denied');
    }

    #[Group('uat-recovery-04b-red')]
    public function test_cross_tenant_pm_id_is_rejected_on_create_and_update(): void
    {
        $foreignUser = $this->actor('Project Manager', null, $this->tenantB);
        $creator = $this->actor('Project Manager');
        $count = DB::table('projects')->count();

        $res = $this->api($creator, 'POST', '/api/projects', self::PROJECT + ['pm_id' => $foreignUser->id]);
        $this->assertSame(422, $res->getStatusCode());
        $this->assertSame($count, DB::table('projects')->count(), 'no project created');

        $before = DB::table('projects')->where('id', $this->project->id)->value('pm_id');
        $this->api($this->pm, 'PUT', "/api/projects/{$this->project->id}", self::PROJECT + ['pm_id' => $foreignUser->id]);
        $this->assertSame($before, DB::table('projects')->where('id', $this->project->id)->value('pm_id'));
    }

    #[Group('uat-recovery-04b-guard')]
    public function test_update_without_pm_id_does_not_erase_it_and_the_current_pm_may_transfer(): void
    {
        $this->api($this->pm, 'PUT', "/api/projects/{$this->project->id}", ['name' => 'renamed', 'start_date' => '2026-12-01', 'end_date' => '2027-02-01']);
        $this->assertSame($this->pm->id, DB::table('projects')->where('id', $this->project->id)->value('pm_id'), 'omitted pm_id preserved');

        $successor = $this->actor('Project Manager');
        $this->api($this->pm, 'PUT', "/api/projects/{$this->project->id}", self::PROJECT + ['pm_id' => $successor->id]);
        $this->assertSame($successor->id, DB::table('projects')->where('id', $this->project->id)->value('pm_id'), 'current PM may hand over');
    }

    #[Group('uat-recovery-04b-red')]
    public function test_pm_authority_cannot_be_taken_by_editing_pm_id(): void
    {
        $before = DB::table('projects')->where('id', $this->project->id)->value('pm_id');
        foreach ([[$this->otherPm, $this->otherPm->id], [$this->admin, $this->admin->id]] as [$actor, $self]) {
            $this->api($actor, 'PUT', "/api/projects/{$this->project->id}", self::PROJECT + ['pm_id' => $self]);
            $this->web($actor, 'PUT', "/app/projects/{$this->project->id}", self::PROJECT + ['pm_id' => $self]);
            $this->assertSame($before, DB::table('projects')->where('id', $this->project->id)->value('pm_id'), 'takeover via update denied');
        }
        $t = $this->task;
        $snap = $this->state($t);
        $this->web($this->otherPm, 'PUT', "/app/tasks/{$t->id}", ['name' => $t->name, 'status' => 'completed']);
        $this->assertSame($snap, $this->state($t));

        // bulk path
        $this->api($this->admin, 'POST', '/api/auth/bulk/projects/update', ['updates' => [['id' => $this->project->id, 'data' => ['pm_id' => $this->admin->id]]]]);
        $this->assertSame($before, DB::table('projects')->where('id', $this->project->id)->value('pm_id'), 'bulk update cannot reassign pm_id either');

        // Members cannot touch it at all
        $this->api($this->memberB, 'PUT', "/api/projects/{$this->project->id}", self::PROJECT + ['pm_id' => $this->memberB->id]);
        $this->assertSame($before, DB::table('projects')->where('id', $this->project->id)->value('pm_id'));
    }

    #[Group('uat-recovery-04b-guard')]
    public function test_unmanaged_project_pm_cannot_be_claimed_by_a_project_update_holder_and_customer_priority_are_untouched(): void
    {
        $orphan = Project::factory()->create(['tenant_id' => $this->tenantA->id, 'pm_id' => null, 'priority' => 'low']);
        // S4d (O-15, Owner-approved A-minimal): the claim path was REMOVED — a plain project.update holder can no longer appoint a PM
        // for a PM-less project (it needs the independent project.assign_manager permission; see UatRecovery04dPmAssignmentTest).
        $this->api($this->pm, 'PUT', "/api/projects/{$orphan->id}", self::PROJECT + ['pm_id' => $this->pm->id, 'priority' => 'high']);
        $this->assertNull(DB::table('projects')->where('id', $orphan->id)->value('pm_id'));

        $this->api($this->pm, 'POST', '/api/projects', self::PROJECT + ['pm_id' => $this->pm->id, 'priority' => 'urgent']);
        $this->assertNotSame('urgent', DB::table('projects')->where('name', 'S4b project')->value('priority'), 'O-11: priority persistence is a separate gap and is not changed here');
    }
}
