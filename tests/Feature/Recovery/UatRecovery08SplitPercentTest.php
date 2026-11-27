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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * UAT RECOVERY 08 — OWN-2026-024 SPLIT-PERCENT (`task_assignments.split_percent`).
 *
 * Before the fix no migration created the column: on MySQL every assignment write / SUM failed (SQLSTATE 42S22, the two
 * UatRecovery04bTaskSurfaceTest failures), and on SQLite SUM("split_percent") silently summed a string literal (0), so the
 * 100 % rule never held and the share was dropped by mass assignment. These tests pin the repaired contract on both routed
 * write surfaces — dashboard (api/dashboard/...) and v1 (api/v1/task-assignments) — on whatever driver runs them; the
 * recovery-mysql CI job runs them on real MySQL 8.
 *
 * Contract: the share the API accepts is the share stored and returned; omitted = 100 (existing contract); recorded shares of a
 * task total ≤ 100 %; one assignment per user per task; only the owning-project PM holding task.assign may write (O-13);
 * the assignee must belong to the task's tenant; historical rows without a recorded share (NULL) are neither counted nor
 * rewritten. Concurrency is proven separately in UatRecovery08bSplitPercentConcurrencyTest (real MySQL only).
 */
#[Group('uat-recovery-08')]
class UatRecovery08SplitPercentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private Project $project;

    private User $pm;

    private User $otherPm;

    private User $memberA;

    private User $memberB;

    private User $memberC;

    private User $foreignUser;

    private User $foreignPm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        // Legacy users.role values let the actors pass the unchanged parameterless `rbac` gate (O-8) on the dashboard routes,
        // exactly as UatRecovery04bTaskSurfaceTest::gated(); authority itself is decided by the seeded role + TaskPolicy.
        $this->pm = $this->actor('Project Manager', $this->tenantA, 'project_manager');
        $this->otherPm = $this->actor('Project Manager', $this->tenantA, 'project_manager');
        $this->memberA = $this->actor('Project Member', $this->tenantA, 'team_member');
        $this->memberB = $this->actor('Project Member', $this->tenantA, 'team_member');
        $this->memberC = $this->actor('Project Member', $this->tenantA, 'team_member');
        $this->foreignUser = $this->actor('Project Member', $this->tenantB, 'team_member');
        $this->foreignPm = $this->actor('Project Manager', $this->tenantB, 'project_manager');
        $this->project = Project::factory()->create(['tenant_id' => $this->tenantA->id, 'pm_id' => $this->pm->id]);
        Project::factory()->create(['tenant_id' => $this->tenantB->id, 'pm_id' => $this->foreignPm->id]);
    }

    public function test_the_column_exists_nullable_without_default(): void
    {
        $this->assertTrue(Schema::hasColumn('task_assignments', 'split_percent'));
        $id = $this->historicRow($this->task(), $this->memberA);
        $this->assertNull(DB::table('task_assignments')->where('id', $id)->value('split_percent'), 'no implicit default for rows written without a share');
    }

    // ------------------------------------------------------------------ persistence = response

    public function test_dashboard_store_persists_share_tenant_and_actor_and_returns_the_stored_row(): void
    {
        $t = $this->task();
        $res = $this->api($this->pm, 'POST', "/api/dashboard/tasks/{$t->id}/assignments", ['user_id' => $this->memberB->id, 'role' => 'assignee', 'split_percent' => 40]);
        $this->assertSame(201, $res->getStatusCode(), (string) $res->getContent());

        $row = DB::table('task_assignments')->where('task_id', $t->id)->where('user_id', $this->memberB->id)->first();
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(40.0, (float) $row->split_percent, 0.001);
        $this->assertSame((string) $this->tenantA->id, (string) $row->tenant_id);
        $this->assertSame((string) $this->pm->id, (string) $row->assigned_by);
        $this->assertNotNull($row->assigned_at);
        $this->assertSame((string) $row->id, (string) $res->json('data.id'));
        $this->assertEqualsWithDelta((float) $row->split_percent, (float) $res->json('data.split_percent'), 0.001, 'API response must equal the stored share');
    }

    public function test_dashboard_store_without_share_stores_the_documented_default_of_100(): void
    {
        $t = $this->task();
        $res = $this->api($this->pm, 'POST', "/api/dashboard/tasks/{$t->id}/assignments", ['user_id' => $this->memberB->id, 'role' => 'assignee']);
        $this->assertSame(201, $res->getStatusCode(), (string) $res->getContent());
        $this->assertEqualsWithDelta(100.0, (float) DB::table('task_assignments')->where('task_id', $t->id)->where('user_id', $this->memberB->id)->value('split_percent'), 0.001);
        $this->assertEqualsWithDelta(100.0, (float) $res->json('data.split_percent'), 0.001);
    }

    public function test_v1_store_persists_share_tenant_and_actor_and_returns_the_stored_row(): void
    {
        $t = $this->task();
        $res = $this->api($this->pm, 'POST', '/api/v1/task-assignments', ['task_id' => $t->id, 'user_id' => $this->memberB->id, 'role' => 'assignee', 'split_percent' => 35.5]);
        $this->assertSame(201, $res->getStatusCode(), (string) $res->getContent());

        $row = DB::table('task_assignments')->where('task_id', $t->id)->where('user_id', $this->memberB->id)->first();
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(35.5, (float) $row->split_percent, 0.001);
        $this->assertSame((string) $this->tenantA->id, (string) $row->tenant_id, 'v1 writes now carry the task tenant (was NULL)');
        $this->assertSame((string) $this->pm->id, (string) $row->assigned_by);
        $this->assertNotNull($row->assigned_at);
        $payload = $res->json('data.assignment');
        $this->assertEqualsWithDelta(35.5, (float) $payload['split_percent'], 0.001);
        $this->assertEqualsWithDelta(35.5, (float) $payload['split_percentage'], 0.001, 'legacy response key carries the stored value');
    }

    // ------------------------------------------------------------------ 100 % rule and duplicates

    public function test_a_total_above_100_percent_is_rejected_on_both_surfaces_without_writing(): void
    {
        $t = $this->task();
        $this->assertSame(201, $this->api($this->pm, 'POST', "/api/dashboard/tasks/{$t->id}/assignments", ['user_id' => $this->memberA->id, 'role' => 'assignee', 'split_percent' => 60])->getStatusCode());
        $before = $this->rows($t);

        $dash = $this->api($this->pm, 'POST', "/api/dashboard/tasks/{$t->id}/assignments", ['user_id' => $this->memberB->id, 'role' => 'assignee', 'split_percent' => 50]);
        $this->assertSame(400, $dash->getStatusCode());
        $this->assertStringContainsString('cannot exceed 100%', (string) $dash->getContent());
        $v1 = $this->api($this->pm, 'POST', '/api/v1/task-assignments', ['task_id' => $t->id, 'user_id' => $this->memberC->id, 'role' => 'assignee', 'split_percent' => 50]);
        $this->assertSame(400, $v1->getStatusCode(), (string) $v1->getContent());
        $this->assertSame($before, $this->rows($t), 'nothing may be written');

        // Exactly filling the remaining 40 % is allowed.
        $this->assertSame(201, $this->api($this->pm, 'POST', "/api/dashboard/tasks/{$t->id}/assignments", ['user_id' => $this->memberB->id, 'role' => 'reviewer', 'split_percent' => 40])->getStatusCode());
        $this->assertEqualsWithDelta(100.0, $this->recordedTotal($t), 0.001);
    }

    public function test_duplicate_assignment_of_the_same_user_is_rejected_on_both_surfaces(): void
    {
        $t = $this->task();
        $this->assertSame(201, $this->api($this->pm, 'POST', "/api/dashboard/tasks/{$t->id}/assignments", ['user_id' => $this->memberB->id, 'role' => 'assignee', 'split_percent' => 20])->getStatusCode());
        $before = $this->rows($t);

        $this->assertSame(400, $this->api($this->pm, 'POST', "/api/dashboard/tasks/{$t->id}/assignments", ['user_id' => $this->memberB->id, 'role' => 'reviewer', 'split_percent' => 10])->getStatusCode());
        $this->assertSame(400, $this->api($this->pm, 'POST', '/api/v1/task-assignments', ['task_id' => $t->id, 'user_id' => $this->memberB->id, 'role' => 'assignee', 'split_percent' => 10])->getStatusCode());
        $this->assertSame($before, $this->rows($t));
    }

    // ------------------------------------------------------------------ update and unassign

    public function test_update_changes_the_stored_share_within_the_limit_and_rejects_overflow(): void
    {
        $t = $this->task();
        $a = $this->api($this->pm, 'POST', "/api/dashboard/tasks/{$t->id}/assignments", ['user_id' => $this->memberA->id, 'role' => 'assignee', 'split_percent' => 50])->json('data.id');
        $b = $this->api($this->pm, 'POST', '/api/v1/task-assignments', ['task_id' => $t->id, 'user_id' => $this->memberB->id, 'role' => 'assignee', 'split_percent' => 30])->json('data.assignment.id');
        $this->assertNotNull($a);
        $this->assertNotNull($b);

        // Raising A to 70 fits (70 + 30); the stored value and the response agree.
        $ok = $this->api($this->pm, 'PUT', "/api/dashboard/assignments/{$a}", ['split_percent' => 70]);
        $this->assertSame(200, $ok->getStatusCode(), (string) $ok->getContent());
        $this->assertEqualsWithDelta(70.0, (float) DB::table('task_assignments')->where('id', $a)->value('split_percent'), 0.001);
        $this->assertEqualsWithDelta(70.0, (float) $ok->json('data.split_percent'), 0.001);

        // Raising B to 40 would make 110 → rejected on v1, B unchanged.
        $over = $this->api($this->pm, 'PUT', "/api/v1/task-assignments/{$b}", ['split_percent' => 40]);
        $this->assertSame(400, $over->getStatusCode(), (string) $over->getContent());
        $this->assertEqualsWithDelta(30.0, (float) DB::table('task_assignments')->where('id', $b)->value('split_percent'), 0.001);

        // An explicit null never erases a recorded share.
        $this->assertSame(200, $this->api($this->pm, 'PUT', "/api/dashboard/assignments/{$a}", ['split_percent' => null, 'notes' => 'n'])->getStatusCode());
        $this->assertEqualsWithDelta(70.0, (float) DB::table('task_assignments')->where('id', $a)->value('split_percent'), 0.001);

        // Unassigning frees the capacity.
        $this->assertSame(200, $this->api($this->pm, 'DELETE', "/api/dashboard/assignments/{$a}")->getStatusCode());
        $this->assertFalse(DB::table('task_assignments')->where('id', $a)->exists());
        $this->assertSame(200, $this->api($this->pm, 'PUT', "/api/v1/task-assignments/{$b}", ['split_percent' => 100])->getStatusCode());
        $this->assertEqualsWithDelta(100.0, $this->recordedTotal($t), 0.001);
    }

    // ------------------------------------------------------------------ authority and tenant isolation

    public function test_only_the_owning_project_pm_may_assign_and_never_across_tenants(): void
    {
        $t = $this->task();
        $before = $this->rows($t);

        foreach (['project member' => $this->memberA, 'PM of another project' => $this->otherPm, 'foreign-tenant PM' => $this->foreignPm] as $label => $actor) {
            $dash = $this->api($actor, 'POST', "/api/dashboard/tasks/{$t->id}/assignments", ['user_id' => $this->memberB->id, 'role' => 'assignee', 'split_percent' => 10]);
            $this->assertContains($dash->getStatusCode(), [403, 404], "{$label} (dashboard): HTTP {$dash->getStatusCode()}");
            $v1 = $this->api($actor, 'POST', '/api/v1/task-assignments', ['task_id' => $t->id, 'user_id' => $this->memberB->id, 'role' => 'assignee', 'split_percent' => 10]);
            $this->assertContains($v1->getStatusCode(), [403, 404], "{$label} (v1): HTTP {$v1->getStatusCode()}");
        }
        $this->assertSame($before, $this->rows($t), 'unauthorised actors write nothing');

        // The owning PM cannot assign a user of another tenant.
        $cross = $this->api($this->pm, 'POST', "/api/dashboard/tasks/{$t->id}/assignments", ['user_id' => $this->foreignUser->id, 'role' => 'assignee', 'split_percent' => 10]);
        $this->assertSame(422, $cross->getStatusCode());
        $crossV1 = $this->api($this->pm, 'POST', '/api/v1/task-assignments', ['task_id' => $t->id, 'user_id' => $this->foreignUser->id, 'role' => 'assignee', 'split_percent' => 10]);
        $this->assertSame(422, $crossV1->getStatusCode());
        $this->assertSame($before, $this->rows($t), 'no cross-tenant assignment');

        // An existing assignment cannot be updated or removed by a non-PM.
        $id = $this->api($this->pm, 'POST', "/api/dashboard/tasks/{$t->id}/assignments", ['user_id' => $this->memberB->id, 'role' => 'assignee', 'split_percent' => 10])->json('data.id');
        $snapshot = $this->rows($t);
        $this->assertContains($this->api($this->memberA, 'PUT', "/api/dashboard/assignments/{$id}", ['split_percent' => 90])->getStatusCode(), [403, 404]);
        $this->assertContains($this->api($this->foreignPm, 'DELETE', "/api/v1/task-assignments/{$id}")->getStatusCode(), [403, 404]);
        $this->assertSame($snapshot, $this->rows($t));
    }

    // ------------------------------------------------------------------ task creation with assignments (api/v1/tasks)

    public function test_v1_task_creation_enforces_the_share_rules_atomically(): void
    {
        $participant = $this->participantRole();
        foreach ([$this->memberA, $this->memberB] as $u) {
            DB::table('project_user_roles')->insert([
                'id' => (string) Str::ulid(), 'project_id' => $this->project->id, 'user_id' => $u->id, 'role_id' => $participant,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $a = (string) $this->memberA->id;
        $b = (string) $this->memberB->id;

        $cases = [
            'total 120 %' => [['user_id' => $a, 'role' => 'assignee', 'split_percent' => 60], ['user_id' => $b, 'role' => 'assignee', 'split_percent' => 60]],
            'same user twice' => [['user_id' => $a, 'role' => 'assignee', 'split_percent' => 30], ['user_id' => $a, 'role' => 'reviewer', 'split_percent' => 30]],
        ];
        foreach ($cases as $label => $assignments) {
            $tasksBefore = DB::table('tasks')->count();
            $rowsBefore = DB::table('task_assignments')->count();
            $res = $this->api($this->pm, 'POST', '/api/v1/tasks', $this->taskPayload($label, $assignments));
            $this->assertSame(400, $res->getStatusCode(), "{$label}: " . $res->getContent());
            $this->assertSame($tasksBefore, DB::table('tasks')->count(), "{$label}: no task may be persisted");
            $this->assertSame($rowsBefore, DB::table('task_assignments')->count(), "{$label}: no assignment may be persisted");
        }

        $res = $this->api($this->pm, 'POST', '/api/v1/tasks', $this->taskPayload('valid', [
            ['user_id' => $a, 'role' => 'assignee', 'split_percent' => 60], ['user_id' => $b, 'role' => 'reviewer', 'split_percent' => 40],
        ]));
        $this->assertSame(201, $res->getStatusCode(), (string) $res->getContent());
        $taskId = (string) DB::table('tasks')->where('name', 'S08 v1 valid')->value('id');
        $rows = DB::table('task_assignments')->where('task_id', $taskId)->orderBy('split_percent')->get(['user_id', 'split_percent', 'tenant_id', 'assigned_at']);
        $this->assertCount(2, $rows);
        $this->assertSame([$b, $a], $rows->pluck('user_id')->map(fn ($v) => (string) $v)->all());
        $this->assertEqualsWithDelta(40.0, (float) $rows[0]->split_percent, 0.001);
        $this->assertEqualsWithDelta(60.0, (float) $rows[1]->split_percent, 0.001);
        foreach ($rows as $row) {
            $this->assertSame((string) $this->tenantA->id, (string) $row->tenant_id);
            $this->assertNotNull($row->assigned_at);
        }
        $returned = collect((array) $res->json('data.task.assignments'))->mapWithKeys(fn ($x) => [(string) $x['user_id'] => (float) $x['split_percent']])->all();
        $this->assertEqualsWithDelta([$a => 60.0, $b => 40.0], $returned, 0.001, 'the response carries the stored shares');
    }

    /**
     * Guard: every check-then-write path of the 100 % / duplicate rule must hold the task-row lock. The race itself is proven
     * on real MySQL by UatRecovery08bSplitPercentConcurrencyTest; this pins that the routed write paths actually use the lock.
     */
    public function test_every_share_write_path_holds_the_task_row_lock(): void
    {
        $paths = [
            [\App\Http\Controllers\Api\TaskAssignmentController::class, 'store', 'TaskAssignmentShares::withTaskLock('],
            [\App\Http\Controllers\Api\TaskAssignmentController::class, 'update', 'TaskAssignmentShares::withTaskLock('],
            [\Src\CoreProject\Controllers\TaskAssignmentController::class, 'store', 'TaskAssignmentShares::withTaskLock('],
            [\Src\CoreProject\Controllers\TaskAssignmentController::class, 'update', 'TaskAssignmentShares::withTaskLock('],
            [\Src\CoreProject\Services\TaskService::class, 'createTaskAssignments', '->lockForUpdate()'],
        ];
        foreach ($paths as [$class, $method, $needle]) {
            $m = new \ReflectionMethod($class, $method);
            $lines = file((string) $m->getFileName()) ?: [];
            $body = implode('', array_slice($lines, $m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1));
            $this->assertStringContainsString($needle, $body, "{$class}::{$method} must hold the task-row lock");
            $this->assertStringContainsString('TaskAssignmentShares::', $body, "{$class}::{$method} must use the single share rule");
        }
    }

    // ------------------------------------------------------------------ historical rows

    public function test_historical_rows_without_a_recorded_share_are_not_counted_or_rewritten(): void
    {
        $t = $this->task();
        $historic = $this->historicRow($t, $this->memberA);

        $res = $this->api($this->pm, 'POST', "/api/dashboard/tasks/{$t->id}/assignments", ['user_id' => $this->memberB->id, 'role' => 'assignee', 'split_percent' => 100]);
        $this->assertSame(201, $res->getStatusCode(), 'a NULL (unrecorded) share does not consume capacity');
        $this->assertNull(DB::table('task_assignments')->where('id', $historic)->value('split_percent'), 'historical rows are never rewritten');
        $this->assertEqualsWithDelta(100.0, $this->recordedTotal($t), 0.001);
    }

    // ------------------------------------------------------------------ helpers

    private function actor(string $seededRole, Tenant $tenant, string $legacy): User
    {
        $u = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $u->forceFill(['role' => $legacy])->save();
        DB::table('user_roles')->insert([
            'user_id' => $u->id, 'role_id' => DB::table('roles')->where('name', $seededRole)->value('id'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $u;
    }

    private function task(): Task
    {
        return Task::factory()->create([
            'tenant_id' => $this->project->tenant_id, 'project_id' => $this->project->id, 'assignee_id' => null,
            'status' => 'pending', 'name' => 'S08 ' . bin2hex(random_bytes(2)), 'progress_percent' => 0,
        ]);
    }

    /** A row as written before the column existed (no share recorded). */
    private function historicRow(Task $t, User $u): string
    {
        $id = (string) Str::ulid();
        DB::table('task_assignments')->insert([
            'id' => $id, 'task_id' => $t->id, 'user_id' => $u->id, 'tenant_id' => $t->tenant_id, 'role' => 'assignee',
            'status' => 'pending', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /** A project-scope participant role with no permissions (participation only), as in UatRecovery06O17. */
    private function participantRole(): string
    {
        $id = (string) Str::ulid();
        $row = ['id' => $id, 'name' => 'S08 Participant', 'scope' => 'project', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()];
        if (Schema::hasColumn('roles', 'tenant_id')) {
            $row['tenant_id'] = (string) $this->tenantA->id;
        }
        DB::table('roles')->insert($row);

        return $id;
    }

    /**
     * @param list<array<string, mixed>> $assignments
     * @return array<string, mixed>
     */
    private function taskPayload(string $label, array $assignments): array
    {
        return ['project_id' => (string) $this->project->id, 'name' => "S08 v1 {$label}", 'start_date' => '2026-12-01', 'end_date' => '2026-12-05', 'assignments' => $assignments];
    }

    private function rows(Task $t): string
    {
        return json_encode(DB::table('task_assignments')->where('task_id', $t->id)->orderBy('id')->get(['id', 'user_id', 'role', 'split_percent', 'tenant_id'])) ?: '';
    }

    private function recordedTotal(Task $t): float
    {
        return (float) DB::table('task_assignments')->where('task_id', $t->id)->whereNotNull('split_percent')->sum('split_percent');
    }

    /** @param array<string, mixed> $d */
    private function api(User $u, string $m, string $uri, array $d = []): \Illuminate\Testing\TestResponse
    {
        Sanctum::actingAs($u);
        if (str_contains($uri, '/v1/')) {
            $this->actingAs($u, 'api');
        }

        return $this->withHeaders(['X-Tenant-ID' => (string) $u->tenant_id])->json($m, $uri, $d);
    }
}
