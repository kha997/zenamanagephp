<?php

declare(strict_types=1);

namespace Tests\Feature\Recovery;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Api\Concerns\InteractsWithWorkTemplateV2;
use Tests\TestCase;

/**
 * UAT RECOVERY 06 — O-17 Option B (Owner-approved 2026-11-24) + CB-3 / CB-4 for task creation.
 *
 * Contract: same tenant AND canonical task.create (template apply: + template.apply) AND (project pm_id OR active
 * project_user_roles participant). No System Admin / super_admin bypass, nothing from users.role.
 *
 * Real DatabaseSeeder. Test-owned fixtures (never seed grants): a "O17 Creator" role holding only task.create, a "O17 Template
 * Applier" role holding template.apply + template.view, a project-scope participant role with no permissions, and a role
 * literally named `super_admin` with ZERO permissions. Every denial asserts zero new tasks, assignments, audit rows,
 * watchers and work instances.
 */
class UatRecovery06O17TaskCreationAuthorityTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithWorkTemplateV2;

    private Tenant $a;

    private Tenant $b;

    private User $pm1;          // seeded PM, pm_id of $p1

    private User $pm2;          // seeded PM, pm_id of $p2

    private User $admin;        // seeded System Admin (every permission), not PM / participant of anything by default

    private User $creator;      // task.create only, active participant of $p1

    private User $lonely;       // task.create only, no relationship anywhere

    private User $member;       // seeded Project Member (no task.create), active participant of $p1

    private User $applier;      // template.apply + template.view, NO task.create, participant of $p1

    private User $super;        // only role: `super_admin` with zero permissions

    private User $userB;        // seeded PM of tenant B

    private Project $p1;

    private Project $p2;

    private Project $pmLess;

    private Project $pB;

    private string $participantRole;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->setUpWorkTemplateV2Routes();
        $this->seed(DatabaseSeeder::class);

        $this->a = Tenant::factory()->create();
        $this->b = Tenant::factory()->create();

        $this->pm1 = $this->user($this->a, $this->roleId('Project Manager'), 'project_manager');
        $this->pm2 = $this->user($this->a, $this->roleId('Project Manager'), 'project_manager');
        $this->admin = $this->user($this->a, $this->roleId('System Admin'), 'admin');
        $creatorRole = $this->customRole('O17 Creator', ['task.create']);
        $this->creator = $this->user($this->a, $creatorRole, 'team_member');
        $this->lonely = $this->user($this->a, $creatorRole, 'team_member');
        $this->member = $this->user($this->a, $this->roleId('Project Member'), 'team_member');
        $this->applier = $this->user($this->a, $this->customRole('O17 Template Applier', ['template.apply', 'template.view']), 'team_member');
        $superRole = $this->roleId('super_admin') ?? $this->customRole('super_admin', []);
        DB::table('role_permissions')->where('role_id', $superRole)->delete(); // fixture: the role carries ZERO canonical grants
        $this->super = $this->user($this->a, $superRole, null);
        $this->userB = $this->user($this->b, $this->roleId('Project Manager'), 'project_manager');

        $this->p1 = Project::factory()->create(['tenant_id' => $this->a->id, 'pm_id' => $this->pm1->id]);
        $this->p2 = Project::factory()->create(['tenant_id' => $this->a->id, 'pm_id' => $this->pm2->id]);
        $this->pmLess = Project::factory()->create(['tenant_id' => $this->a->id, 'pm_id' => null]);
        $this->pB = Project::factory()->create(['tenant_id' => $this->b->id, 'pm_id' => $this->userB->id]);

        $this->participantRole = $this->customRole('O17 Participant', [], 'project', (string) $this->a->id);
        foreach ([$this->creator, $this->member, $this->applier] as $u) {
            $this->participate($u, $this->p1);
        }
    }

    // ------------------------------------------------------------------ fixtures

    private function roleId(string $name): ?string
    {
        return DB::table('roles')->where('name', $name)->value('id');
    }

    private function user(Tenant $t, ?string $roleId, ?string $legacy): User
    {
        $u = User::factory()->create(['tenant_id' => $t->id, 'role' => $legacy, 'is_active' => true]);
        if ($roleId !== null) {
            DB::table('user_roles')->insert(['user_id' => $u->id, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $u;
    }

    /** EXPLICIT TEST FIXTURE (not a seed grant). */
    private function customRole(string $name, array $codes, string $scope = 'system', ?string $tenantId = null): string
    {
        $id = (string) Str::ulid();
        $row = ['id' => $id, 'name' => $name, 'scope' => $scope, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()];
        if (Schema::hasColumn('roles', 'tenant_id')) {
            $row['tenant_id'] = $tenantId;
        }
        DB::table('roles')->insert($row);
        foreach (DB::table('permissions')->whereIn('code', $codes)->pluck('id') as $pid) {
            DB::table('role_permissions')->insert(['role_id' => $id, 'permission_id' => $pid, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $id;
    }

    private function participate(User $u, Project $p, bool $active = true): void
    {
        DB::table('project_user_roles')->insert([
            'id' => (string) Str::ulid(),
            'project_id' => $p->id,
            'user_id' => $u->id,
            'role_id' => $this->participantRole,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => $active ? null : now(),
        ]);
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $c = [];
        foreach (['tasks', 'task_assignments', 'audit_logs', 'task_watchers', 'work_instances', 'task_dependencies'] as $t) {
            $c[$t] = Schema::hasTable($t) ? DB::table($t)->count() : 0;
        }

        return $c;
    }

    private function assertNothingPersisted(array $before, string $context): void
    {
        $this->assertSame($before, $this->counts(), "{$context}: a rejected creation must persist no task, assignment, audit, watcher, dependency or work instance");
    }

    private function payload(Project $p, array $extra = []): array
    {
        $this->n++;

        return ['project_id' => (string) $p->id, 'name' => "o17 task {$this->n}", 'start_date' => '2026-12-01', 'end_date' => '2026-12-05'] + $extra;
    }

    /** @return array<int, array{0: string, 1: string}> */
    private function endpoints(): array
    {
        return [['api', '/api/zena/tasks'], ['api', '/api/tasks'], ['v1', '/api/v1/tasks'], ['web', '/app/tasks'], ['web', '/operator/schedule/tasks']];
    }

    private function send(User $u, string $kind, string $uri, array $data): TestResponse
    {
        $this->app['auth']->forgetGuards();
        if ($kind === 'web') {
            return $this->actingAs($u)->post($uri, $data, ['Accept' => 'text/html']);
        }
        if ($kind === 'v1') {
            $this->actingAs($u, 'api');
        }
        Sanctum::actingAs($u);

        return $this->withHeaders(['X-Tenant-ID' => (string) $u->tenant_id, 'Accept' => 'application/json'])->postJson($uri, $data);
    }

    private function assertCreatesEverywhere(User $u, Project $p, string $case): void
    {
        foreach ($this->endpoints() as [$kind, $uri]) {
            $before = $this->counts();
            $data = $this->payload($p);
            $res = $this->send($u, $kind, $uri, $data);
            if ($kind === 'web') {
                $res->assertStatus(302);
                $res->assertSessionHas('success');
            } else {
                $this->assertSame(201, $res->getStatusCode(), "{$case} {$uri}: " . $res->getContent());
            }
            $task = DB::table('tasks')->where('name', $data['name'])->first();
            $this->assertNotNull($task, "{$case} {$uri}: task persisted");
            $this->assertSame((string) $p->id, (string) $task->project_id);
            $this->assertSame((string) $p->tenant_id, (string) $task->tenant_id);
            $this->assertSame($before['tasks'] + 1, $this->counts()['tasks']);
            $this->assertAuditedOnce($task->id, $u, $p, "{$case} {$uri}");
        }
    }

    private function assertDeniedEverywhere(User $u, Project $p, string $case, int $apiStatus = 403): void
    {
        foreach ($this->endpoints() as [$kind, $uri]) {
            $before = $this->counts();
            $res = $this->send($u, $kind, $uri, $this->payload($p));
            if ($kind === 'web') {
                $this->assertContains($res->getStatusCode(), [302, 403], "{$case} {$uri}");
                $res->assertSessionMissing('success');
            } else {
                $this->assertSame($apiStatus, $res->getStatusCode(), "{$case} {$uri}: " . $res->getContent());
            }
            $this->assertNothingPersisted($before, "{$case} {$uri}");
        }
    }

    private function assertAuditedOnce(string $taskId, User $actor, Project $p, string $context): void
    {
        $rows = DB::table('audit_logs')->where('action', 'zena.task.created')->where('entity_id', $taskId)->get();
        $this->assertCount(1, $rows, "{$context}: exactly one zena.task.created audit row per created task");
        $this->assertSame((string) $actor->id, (string) $rows[0]->user_id);
        $this->assertSame((string) $p->tenant_id, (string) $rows[0]->tenant_id);
        $this->assertSame((string) $p->id, (string) $rows[0]->project_id);
        $this->assertSame('task', $rows[0]->entity_type);
    }

    private function publishedTemplate(): string
    {
        [$template] = $this->seedV2Template($this->a, $this->pm1, 'WT-O17-' . Str::random(4));
        DB::table('work_template_versions')->where('work_template_id', $template->id)->update(['published_at' => now(), 'is_immutable' => true, 'published_by' => $this->pm1->id]);

        return (string) $template->id;
    }

    // ------------------------------------------------------------------ O-17 Option B, direct creation (#1–#5)

    #[Group('uat-recovery-06')]
    public function test_owning_pm_is_authorised_on_every_direct_creation_route_and_each_creation_is_audited(): void
    {
        $this->assertCreatesEverywhere($this->pm1, $this->p1, 'A owning PM');
    }

    #[Group('uat-recovery-06')]
    public function test_active_participant_holding_task_create_is_authorised(): void
    {
        $this->assertCreatesEverywhere($this->creator, $this->p1, 'C participant + task.create');
    }

    #[Group('uat-recovery-06')]
    public function test_participant_without_task_create_is_denied(): void
    {
        $this->assertDeniedEverywhere($this->member, $this->p1, 'C0 participant without task.create');
    }

    #[Group('uat-recovery-06')]
    public function test_pm_of_another_project_is_denied(): void
    {
        $this->assertDeniedEverywhere($this->pm1, $this->p2, 'B PM1 -> PM2 project');
    }

    #[Group('uat-recovery-06')]
    public function test_same_tenant_task_create_holder_without_relationship_is_denied(): void
    {
        $this->assertDeniedEverywhere($this->lonely, $this->p1, 'E no relationship');
        $this->assertDeniedEverywhere($this->creator, $this->p2, 'D participant of P1 -> P2');
    }

    #[Group('uat-recovery-06')]
    public function test_system_admin_satisfies_the_same_contract_no_bypass(): void
    {
        $this->assertTrue($this->admin->hasPermission('task.create'), 'precondition: System Admin holds task.create');
        $this->assertDeniedEverywhere($this->admin, $this->p1, 'F System Admin, non-member');

        $this->participate($this->admin, $this->p1);
        $this->assertCreatesEverywhere($this->admin, $this->p1, 'F System Admin as active participant');
    }

    #[Group('uat-recovery-06')]
    public function test_super_admin_role_without_canonical_grant_is_denied_on_every_creation_path(): void
    {
        $this->assertTrue($this->super->isSuperAdmin(), 'precondition: the actor holds a role literally named super_admin');
        $this->assertFalse($this->super->hasPermission('task.create'));
        $this->participate($this->super, $this->p1); // even a recorded relationship does not replace the canonical grant

        $this->assertDeniedEverywhere($this->super, $this->p1, 'F2 super_admin, zero permissions');

        $before = $this->counts();
        $this->send($this->super, 'api', '/api/auth/bulk/tasks/create', ['project_id' => $this->pB->id, 'tenant_id' => $this->b->id, 'tasks' => [['title' => 'x', 'description' => 'x']]])
            ->assertStatus(403);
        $this->send($this->super, 'api', '/api/auth/bulk/tasks/create', ['project_id' => $this->p1->id, 'tasks' => [['title' => 'x', 'description' => 'x']]])
            ->assertStatus(403);
        $template = $this->publishedTemplate();
        $this->send($this->super, 'api', "/api/zena/projects/{$this->p1->id}/apply-template", ['work_template_id' => $template])->assertStatus(403);
        $this->send($this->super, 'api', "/app/projects/{$this->p1->id}/work-templates/apply", ['work_template_id' => $template])->assertStatus(403);
        $this->assertNothingPersisted($before, 'F3 super_admin on bulk / template');
        $this->assertSame(0, DB::table('tasks')->where('tenant_id', $this->b->id)->count(), 'nothing written into tenant B');
    }

    #[Group('uat-recovery-06')]
    public function test_inactive_or_foreign_participation_gives_no_authority(): void
    {
        $revoked = $this->user($this->a, $this->roleId('Project Manager'), 'project_manager');
        $this->participate($revoked, $this->p2, false); // deleted_at set
        $this->assertDeniedEverywhere($revoked, $this->p2, 'T-7 revoked participation');

        // a participation row pointing a tenant-B user at a tenant-A project confers nothing on that user anywhere
        $this->participate($this->userB, $this->p1);
        $this->assertDeniedEverywhere($this->userB, $this->p1, 'T-7 foreign-tenant participant', 422);
    }

    #[Group('uat-recovery-06')]
    public function test_project_without_pm_accepts_only_participants(): void
    {
        $this->assertDeniedEverywhere($this->pm1, $this->pmLess, 'B2 PM1 -> pm-less project');
        $this->participate($this->creator, $this->pmLess);
        $this->assertCreatesEverywhere($this->creator, $this->pmLess, 'B2 participant of pm-less project');
    }

    #[Group('uat-recovery-06')]
    public function test_cross_tenant_creation_is_rejected_without_side_effects(): void
    {
        $this->assertDeniedEverywhere($this->pm1, $this->pB, 'G cross-tenant', 422);
        $this->assertSame(0, DB::table('tasks')->where('tenant_id', $this->b->id)->count());
    }

    #[Group('uat-recovery-06')]
    public function test_unauthorised_actor_cannot_probe_reference_validation(): void
    {
        // authority is decided before any reference is validated: an unrelated actor gets 403, never a 422 that would reveal
        // whether a referenced user/task id exists
        $before = $this->counts();
        $this->send($this->lonely, 'api', '/api/zena/tasks', $this->payload($this->p1, ['assignee_id' => (string) $this->userB->id]))->assertStatus(403);
        $this->send($this->lonely, 'v1', '/api/v1/tasks', $this->payload($this->p1, ['assignments' => [['user_id' => (string) $this->userB->id]]]))->assertStatus(403);
        $this->assertNothingPersisted($before, 'probe');
    }

    // ------------------------------------------------------------------ references at creation (Gate 5)

    #[Group('uat-recovery-06')]
    public function test_foreign_or_ineligible_assignee_watcher_parent_and_dependency_are_rejected_with_422(): void
    {
        $bTask = Task::factory()->create(['tenant_id' => $this->b->id, 'project_id' => $this->pB->id]);
        $p2Task = Task::factory()->create(['tenant_id' => $this->a->id, 'project_id' => $this->p2->id]);
        $outsider = $this->user($this->a, $this->roleId('Project Member'), 'team_member'); // same tenant, no relationship to P1

        $cases = [
            'assignee other tenant' => ['assignee_id' => (string) $this->userB->id],
            'assignee same-tenant non-participant' => ['assignee_id' => (string) $outsider->id],
            'watcher other tenant' => ['watchers' => [(string) $this->userB->id]],
            'watcher non-participant' => ['watchers' => [(string) $outsider->id]],
            'parent other tenant' => ['parent_id' => (string) $bTask->id],
            'parent other project' => ['parent_id' => (string) $p2Task->id],
            'dependency other tenant' => ['dependencies' => [(string) $bTask->id]],
            'dependency other project' => ['dependencies' => [(string) $p2Task->id]],
            'unknown assignee id' => ['assignee_id' => (string) Str::ulid()],
        ];
        foreach ($cases as $label => $extra) {
            $before = $this->counts();
            $res = $this->send($this->pm1, 'api', '/api/zena/tasks', $this->payload($this->p1, $extra));
            $this->assertSame(422, $res->getStatusCode(), "zena tasks route, {$label}: " . $res->getContent());
            $this->assertNothingPersisted($before, "zena tasks route, {$label}");
        }

        $v1Cases = [
            'assignment other tenant' => ['assignments' => [['user_id' => (string) $this->userB->id, 'role' => 'assignee']]],
            'assignment non-participant' => ['assignments' => [['user_id' => (string) $outsider->id, 'role' => 'assignee']]],
            'dependency other tenant' => ['dependencies' => [(string) $bTask->id]],
        ];
        foreach ($v1Cases as $label => $extra) {
            $before = $this->counts();
            $res = $this->send($this->pm1, 'v1', '/api/v1/tasks', $this->payload($this->p1, $extra));
            $this->assertSame(422, $res->getStatusCode(), "v1 {$label}: " . $res->getContent());
            $this->assertNothingPersisted($before, "v1 {$label}");
        }
    }

    #[Group('uat-recovery-06')]
    public function test_eligible_assignee_and_same_project_parent_and_dependency_are_accepted(): void
    {
        $parent = Task::factory()->create(['tenant_id' => $this->a->id, 'project_id' => $this->p1->id]);
        $sibling = Task::factory()->create(['tenant_id' => $this->a->id, 'project_id' => $this->p1->id]);
        $res = $this->send($this->pm1, 'api', '/api/zena/tasks', $this->payload($this->p1, [
            'assignee_id' => (string) $this->creator->id,
            // eligible `watchers` are NOT exercised on success: any watcher row fails on a pre-existing task_watchers.id
            // defect (NOT NULL id, never generated) -> 500, recorded in the O-17 report, out of scope here.
            'parent_id' => (string) $parent->id,
            'dependencies' => [(string) $sibling->id],
        ]));
        $this->assertSame(201, $res->getStatusCode(), $res->getContent());
        $res = $this->send($this->pm1, 'v1', '/api/v1/tasks', $this->payload($this->p1, ['dependencies' => [(string) $sibling->id]]));
        $this->assertSame(201, $res->getStatusCode(), $res->getContent());
    }

    // ------------------------------------------------------------------ CB-3 bulk (#7)

    #[Group('uat-recovery-06')]
    public function test_bulk_create_never_trusts_request_tenant_and_rejects_foreign_malformed_or_missing_projects(): void
    {
        $this->participate($this->admin, $this->p1); // the only seeded auth.bulk.manage holder, made a legitimate participant of P1
        $row = fn (string $t) => ['title' => $t, 'description' => 'x'];

        $cases = [
            'K1 foreign tenant_id + foreign project' => [['project_id' => $this->pB->id, 'tenant_id' => $this->b->id, 'tasks' => [$row('k1')]], 422],
            'conflicting tenant_id + own project' => [['project_id' => $this->p1->id, 'tenant_id' => $this->b->id, 'tasks' => [$row('k1b')]], 422],
            'foreign project, no tenant_id' => [['project_id' => $this->pB->id, 'tasks' => [$row('k1c')]], 422],
            'malformed project_id' => [['project_id' => 'not-a-project', 'tasks' => [$row('k1d')]], 422],
            'missing project_id' => [['tasks' => [$row('k1e')]], 422],
            'K2 own tenant, not PM/participant' => [['project_id' => $this->p2->id, 'tasks' => [$row('k2')]], 403],
            'row assignee from other tenant' => [['project_id' => $this->p1->id, 'tasks' => [$row('k3') + ['assignee_id' => (string) $this->userB->id]]], 422],
        ];
        foreach ($cases as $label => [$body, $status]) {
            $before = $this->counts();
            $res = $this->send($this->admin, 'api', '/api/auth/bulk/tasks/create', $body);
            $this->assertSame($status, $res->getStatusCode(), "{$label}: " . $res->getContent());
            $this->assertNothingPersisted($before, $label);
        }
        $this->assertSame(0, DB::table('tasks')->where('tenant_id', $this->b->id)->count(), 'CB-3: nothing written into tenant B');

        // PM without auth.bulk.manage: still blocked by the route gate
        $this->send($this->pm1, 'api', '/api/auth/bulk/tasks/create', ['project_id' => $this->p1->id, 'tasks' => [$row('pm')]])->assertStatus(403);
    }

    #[Group('uat-recovery-06')]
    public function test_bulk_create_for_authorised_actor_writes_only_into_the_authorised_project_and_audits_each_task(): void
    {
        $this->participate($this->admin, $this->p1);
        $before = $this->counts();
        $res = $this->send($this->admin, 'api', '/api/auth/bulk/tasks/create', [
            'project_id' => $this->p1->id,
            'tenant_id' => $this->a->id, // equal to the authenticated tenant: accepted, but never used as authority
            'tasks' => [
                ['title' => 'bulk ok 1', 'description' => 'x'],
                // smuggled row-level identifiers must be ignored, never persisted
                ['title' => 'bulk ok 2', 'description' => 'x', 'tenant_id' => $this->b->id, 'project_id' => $this->pB->id, 'created_by' => $this->userB->id],
            ],
        ]);
        $res->assertStatus(200);
        $this->assertSame(2, $res->json('data.created'), $res->getContent());
        $rows = DB::table('tasks')->whereIn('name', ['bulk ok 1', 'bulk ok 2'])->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $t) {
            $this->assertSame((string) $this->a->id, (string) $t->tenant_id);
            $this->assertSame((string) $this->p1->id, (string) $t->project_id);
            $this->assertNotSame((string) $this->userB->id, (string) $t->created_by);
            $this->assertAuditedOnce((string) $t->id, $this->admin, $this->p1, 'bulk');
        }
        $this->assertSame($before['tasks'] + 2, $this->counts()['tasks']);
    }

    #[Group('uat-recovery-06')]
    public function test_bulk_import_never_trusts_request_tenant_and_enforces_project_authority(): void
    {
        $this->participate($this->admin, $this->p1);
        $csv = fn () => UploadedFile::fake()->createWithContent('tasks.csv', "title,description\nimported one,desc\n");

        $cases = [
            'foreign project' => [['project_id' => $this->pB->id], 422],
            'conflicting options.tenant_id' => [['project_id' => $this->p1->id, 'options' => ['tenant_id' => $this->b->id]], 422],
            'malformed project' => [['project_id' => 'nope'], 422],
            'not PM/participant' => [['project_id' => $this->p2->id], 403],
        ];
        foreach ($cases as $label => [$body, $status]) {
            $before = $this->counts();
            $this->app['auth']->forgetGuards();
            Sanctum::actingAs($this->admin);
            $res = $this->withHeaders(['X-Tenant-ID' => (string) $this->a->id, 'Accept' => 'application/json'])
                ->post('/api/auth/bulk/import/tasks', $body + ['file' => $csv()]);
            $this->assertSame($status, $res->getStatusCode(), "import {$label}: " . $res->getContent());
            $this->assertNothingPersisted($before, "import {$label}");
        }

        // An AUTHORISED import gets past authority, but the import itself is non-functional before this patch and after it:
        // ImportExportService references an un-imported CsvReader class (pre-existing, recorded, out of scope). Whatever the
        // outcome, nothing may land outside the authorised project / tenant.
        $outsideBefore = DB::table('tasks')->where('project_id', '!=', $this->p1->id)->count();
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->admin);
        $this->withHeaders(['X-Tenant-ID' => (string) $this->a->id, 'Accept' => 'application/json'])
            ->post('/api/auth/bulk/import/tasks', ['project_id' => $this->p1->id, 'file' => $csv()]);
        $this->assertSame(0, DB::table('tasks')->where('tenant_id', $this->b->id)->count());
        $this->assertSame($outsideBefore, DB::table('tasks')->where('project_id', '!=', $this->p1->id)->count());
        $created = DB::table('tasks')->where('name', 'imported one')->get();
        foreach ($created as $t) { // only if the pre-existing import defect is ever fixed
            $this->assertAuditedOnce((string) $t->id, $this->admin, $this->p1, 'import');
        }
    }

    // ------------------------------------------------------------------ template application (#8)

    #[Group('uat-recovery-06')]
    public function test_template_apply_alone_is_not_creation_authority(): void
    {
        $template = $this->publishedTemplate();
        $this->assertFalse($this->applier->hasPermission('task.create'));
        foreach (["/api/zena/projects/{$this->p1->id}/apply-template", "/app/projects/{$this->p1->id}/work-templates/apply"] as $uri) {
            foreach ([false, true] as $dry) {
                $before = $this->counts();
                $res = $this->send($this->applier, 'api', $uri, ['work_template_id' => $template, 'dry_run' => $dry]);
                $this->assertSame(403, $res->getStatusCode(), "H1 {$uri} dry={$dry}: " . $res->getContent());
                $this->assertNothingPersisted($before, "H1 {$uri}");
            }
        }
    }

    #[Group('uat-recovery-06')]
    public function test_template_apply_requires_the_same_project_relationship(): void
    {
        $template = $this->publishedTemplate();
        foreach ([[$this->pm1, $this->p2, 'H3 PM1 -> PM2 project'], [$this->admin, $this->p1, 'System Admin non-member'], [$this->lonely, $this->p1, 'task.create only, no template.apply']] as [$u, $p, $label]) {
            $before = $this->counts();
            $res = $this->send($u, 'api', "/api/zena/projects/{$p->id}/apply-template", ['work_template_id' => $template]);
            $this->assertSame(403, $res->getStatusCode(), "{$label}: " . $res->getContent());
            $this->assertNothingPersisted($before, $label);
        }
    }

    #[Group('uat-recovery-06')]
    public function test_owning_pm_template_apply_is_authorised_and_every_created_task_is_audited_atomically(): void
    {
        $template = $this->publishedTemplate();
        $before = $this->counts();
        $res = $this->send($this->pm1, 'api', "/api/zena/projects/{$this->p1->id}/apply-template", ['work_template_id' => $template]);
        $this->assertSame(201, $res->getStatusCode(), $res->getContent());
        $created = DB::table('tasks')->where('project_id', $this->p1->id)->whereNotNull('work_instance_id')->get();
        $this->assertGreaterThan(0, $created->count());
        $this->assertSame($before['tasks'] + $created->count(), $this->counts()['tasks']);
        foreach ($created as $t) {
            $this->assertSame((string) $this->a->id, (string) $t->tenant_id);
            $this->assertAuditedOnce((string) $t->id, $this->pm1, $this->p1, 'template apply');
        }
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'zena.work-template.apply')->where('project_id', $this->p1->id)->count());
        foreach (DB::table('task_assignments')->whereIn('task_id', $created->pluck('id'))->pluck('user_id') as $assignee) {
            $this->assertSame((string) $this->pm1->id, (string) $assignee, 'template assignee resolved to the project PM only');
        }
    }

    #[Group('uat-recovery-06')]
    public function test_template_user_selector_must_name_an_eligible_assignee(): void
    {
        $template = $this->publishedTemplate();
        $versionIds = DB::table('work_template_versions')->where('work_template_id', $template)->pluck('id');
        $outsider = $this->user($this->a, $this->roleId('Project Member'), 'team_member');
        DB::table('work_template_steps')->whereIn('work_template_version_id', $versionIds)->update(['assignee_rule_json' => json_encode(['user_id' => (string) $outsider->id])]);

        $before = $this->counts();
        $res = $this->send($this->pm1, 'api', "/api/zena/projects/{$this->p1->id}/apply-template", ['work_template_id' => $template]);
        $this->assertSame(422, $res->getStatusCode(), $res->getContent());
        $this->assertNothingPersisted($before, 'template user selector non-participant');

        DB::table('work_template_steps')->whereIn('work_template_version_id', $versionIds)->update(['assignee_rule_json' => json_encode(['user_id' => (string) $this->creator->id])]);
        $res = $this->send($this->pm1, 'api', "/api/zena/projects/{$this->p1->id}/apply-template", ['work_template_id' => $template]);
        $this->assertSame(201, $res->getStatusCode(), 'an active participant is an eligible template assignee: ' . $res->getContent());
    }

    // ------------------------------------------------------------------ atomicity (Gate 6)

    #[Group('uat-recovery-06')]
    public function test_a_failed_audit_write_rolls_the_creation_back_on_every_path(): void
    {
        $this->participate($this->admin, $this->p1);
        $template = $this->publishedTemplate();
        Event::listen(QueryExecuted::class, function (QueryExecuted $q): void {
            if (str_contains($q->sql, 'audit_logs') && stripos($q->sql, 'insert') === 0 && in_array('zena.task.created', $q->bindings, true)) {
                throw new \RuntimeException('simulated audit store failure');
            }
        });

        $attempts = [
            [$this->pm1, 'api', '/api/zena/tasks', $this->payload($this->p1)],
            [$this->pm1, 'v1', '/api/v1/tasks', $this->payload($this->p1)],
            [$this->pm1, 'web', '/app/tasks', $this->payload($this->p1)],
            [$this->admin, 'api', '/api/auth/bulk/tasks/create', ['project_id' => $this->p1->id, 'tasks' => [['title' => 'atomic bulk', 'description' => 'x']]]],
            [$this->pm1, 'api', "/api/zena/projects/{$this->p1->id}/apply-template", ['work_template_id' => $template]],
        ];
        foreach ($attempts as [$u, $kind, $uri, $body]) {
            $before = $this->counts();
            $res = $this->send($u, $kind, $uri, $body);
            if (str_contains($uri, '/bulk/')) {
                // bulk convention: per-row outcome in the 200 body; the failed row must be reported and leave nothing behind
                $this->assertSame(0, $res->json('data.created'), $res->getContent());
                $this->assertSame(1, $res->json('data.failed'), $res->getContent());
            } elseif ($kind === 'web') {
                $res->assertSessionMissing('success');
            } else {
                $this->assertNotContains($res->getStatusCode(), [200, 201], "{$uri} must not report success when its audit row could not be written: " . $res->getContent());
            }
            $this->assertNothingPersisted($before, "{$uri} audit failure");
        }
    }

    // ------------------------------------------------------------------ CB-4 on derived creation (change-request apply, #9)

    #[Group('uat-recovery-06')]
    public function test_change_request_apply_needs_the_canonical_grant_and_audits_its_delta_task(): void
    {
        $cr = fn () => \App\Models\ChangeRequest::factory()->create([
            'tenant_id' => $this->a->id, 'project_id' => $this->p1->id, 'requested_by' => $this->pm1->id, 'status' => 'approved',
        ]);

        $denied = $cr();
        $before = $this->counts();
        $this->send($this->super, 'api', "/api/zena/change-requests/{$denied->id}/apply", [])->assertStatus(403);
        $this->assertNothingPersisted($before, 'CB-4 super_admin change-request apply');
        $this->assertSame('approved', DB::table('change_requests')->where('id', $denied->id)->value('status'));

        // the canonical holder (seeded: System Admin only) is unchanged: who may apply is O-17i, not decided in this slice
        $ok = $cr();
        $this->participate($this->admin, $this->p1);
        $res = $this->send($this->admin, 'api', "/api/zena/change-requests/{$ok->id}/apply", []);
        $this->assertSame(200, $res->getStatusCode(), $res->getContent());
        $taskId = (string) DB::table('change_requests')->where('id', $ok->id)->value('task_id');
        $this->assertNotSame('', $taskId);
        $this->assertAuditedOnce($taskId, $this->admin, $this->p1, 'change-request apply');
    }

    /**
     * Owner pre-push gate (2026-11-24, O17_APPROVED_FOR_CONDITIONAL_COMMIT_AND_PUSH): apply creates a task, so the actor must
     * hold change-request.apply AND task.create, be of the project's tenant AND be its PM or an active participant.
     */
    #[Group('uat-recovery-06')]
    public function test_change_request_apply_requires_the_full_task_creation_contract(): void
    {
        $crIn = fn (Project $p) => \App\Models\ChangeRequest::factory()->create([
            'tenant_id' => $p->tenant_id, 'project_id' => $p->id, 'requested_by' => $p->pm_id, 'status' => 'approved',
        ]);
        $sideEffects = fn (): array => $this->counts() + [
            'cr_links' => Schema::hasTable('cr_links') ? DB::table('cr_links')->count() : 0,
            'baselines' => Schema::hasTable('baselines') ? DB::table('baselines')->count() : 0,
        ];
        $assertDenied = function (User $u, $cr, string $case, array $codes = [403]) use ($sideEffects): void {
            $before = $sideEffects();
            $res = $this->send($u, 'api', "/api/zena/change-requests/{$cr->id}/apply", []);
            $this->assertContains($res->getStatusCode(), $codes, "{$case}: " . $res->getContent());
            $this->assertSame($before, $sideEffects(), "{$case}: a denied apply must persist no task, assignment, audit, link or baseline");
            $row = DB::table('change_requests')->where('id', $cr->id)->first();
            $this->assertSame('approved', $row->status, "{$case}: change request must stay unapplied");
            $this->assertNull($row->task_id, "{$case}: no delta task linked");
        };

        // 4. holder of change-request.apply (+ task.create) who is neither PM nor participant of the same-tenant project p2
        $this->participate($this->admin, $this->p1); // participant elsewhere does not help
        $assertDenied($this->admin, $crIn($this->p2), 'nonparticipant System Admin on another project');

        // change-request.apply without task.create, even as a participant
        $crOnly = $this->user($this->a, $this->customRole('O17 CR Applier', ['change-request.apply']), 'team_member');
        $this->participate($crOnly, $this->p2);
        $assertDenied($crOnly, $crIn($this->p2), 'change-request.apply without task.create');

        // task.create participant without change-request.apply
        $assertDenied($this->creator, $crIn($this->p1), 'task.create participant without change-request.apply');

        // 5. zero-permission super_admin, also as a participant
        $this->participate($this->super, $this->p2);
        $assertDenied($this->super, $crIn($this->p2), 'zero-permission super_admin participant');

        // cross tenant: the change request of tenant B is invisible to tenant A
        $assertDenied($this->admin, $crIn($this->pB), 'cross-tenant change request', [403, 404]);

        // the full contract (PM of p2 holding both grants via an explicit fixture role) is allowed and audited
        DB::table('user_roles')->insert(['user_id' => $this->pm2->id, 'role_id' => $this->customRole('O17 PM CR Applier', ['change-request.apply']), 'created_at' => now(), 'updated_at' => now()]);
        $ok = $crIn($this->p2);
        $res = $this->send($this->pm2, 'api', "/api/zena/change-requests/{$ok->id}/apply", []);
        $this->assertSame(200, $res->getStatusCode(), $res->getContent());
        $taskId = (string) DB::table('change_requests')->where('id', $ok->id)->value('task_id');
        $this->assertSame((string) $this->p2->id, (string) DB::table('tasks')->where('id', $taskId)->value('project_id'));
        $this->assertAuditedOnce($taskId, $this->pm2, $this->p2, 'PM change-request apply');
    }

    // ------------------------------------------------------------------ CB-3 / CB-4 latent paths

    #[Group('uat-recovery-06')]
    public function test_latent_core_project_template_apply_route_is_tenant_scoped_and_authority_checked(): void
    {
        $template = $this->publishedTemplate();
        foreach ([[$this->member, $this->pB, [422, 403]], [$this->member, $this->p1, [403]], [$this->super, $this->p1, [403]]] as [$u, $p, $codes]) {
            $before = $this->counts();
            $res = $this->send($u, 'api', "/api/work-templates/{$template}/apply", ['project_id' => $p->id]);
            $this->assertContains($res->getStatusCode(), $codes, $res->getContent());
            $this->assertNothingPersisted($before, 'H5 CoreProject apply');
        }
    }
}
