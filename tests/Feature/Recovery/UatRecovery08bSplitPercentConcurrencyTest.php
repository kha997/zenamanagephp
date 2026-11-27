<?php

declare(strict_types=1);

namespace Tests\Feature\Recovery;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * UAT RECOVERY 08b — OWN-2026-024 SPLIT-PERCENT: concurrent assignment writes cannot together exceed 100 %.
 *
 * Two genuinely concurrent OS processes each try to allocate 60 % of the same task through the production rule
 * (TaskAssignmentShares::withTaskLock + assertFits), with a pause between check and insert. Without the task-row lock both
 * would pass the check and the recorded total would be 120 %. Required: exactly one succeeds, the other fails with a clean
 * CONFLICT (not a deadlock / exception), and the recorded total is 60 %.
 *
 * Needs committed rows visible to other connections, so this class does NOT use RefreshDatabase; it creates and deletes its own
 * synthetic tenant/project/task/users. Real row locking is a MySQL property: on any other driver the test is skipped with that
 * reason (the recovery-mysql CI job fails on any skip, so there it can never be skipped silently).
 *
 * @group stress
 */
#[Group('uat-recovery-08')]
#[Group('stress')]
class UatRecovery08bSplitPercentConcurrencyTest extends TestCase
{
    /** @var array<string, list<string>> table => ids created by this test */
    private array $created = [];

    protected function tearDown(): void
    {
        try {
            foreach (['task_assignments' => 'task_id', 'tasks' => 'id', 'projects' => 'id', 'users' => 'id', 'tenants' => 'id'] as $table => $column) {
                $ids = $table === 'task_assignments' ? ($this->created['tasks'] ?? []) : ($this->created[$table] ?? []);
                if ($ids !== []) {
                    DB::table($table)->whereIn($column, $ids)->delete();
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_two_concurrent_60_percent_allocations_on_one_task_only_one_succeeds(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('dependency: real MySQL row locking is required to prove concurrent allocation safety; this run uses ' . DB::connection()->getDriverName() . '.');
        }

        $usersBefore = DB::table('users')->count();
        $tenantsBefore = DB::table('tenants')->count();
        $tenantId = $this->insert('tenants', ['name' => 'S08b tenant', 'slug' => 's08b-' . Str::lower((string) Str::ulid()), 'status' => 'active']);
        $pm = $this->insert('users', ['name' => 'S08b PM', 'email' => 's08b-pm-' . Str::lower((string) Str::ulid()) . '@example.test', 'password' => 'x', 'tenant_id' => $tenantId, 'is_active' => 1]);
        $userA = $this->insert('users', ['name' => 'S08b A', 'email' => 's08b-a-' . Str::lower((string) Str::ulid()) . '@example.test', 'password' => 'x', 'tenant_id' => $tenantId, 'is_active' => 1]);
        $userB = $this->insert('users', ['name' => 'S08b B', 'email' => 's08b-b-' . Str::lower((string) Str::ulid()) . '@example.test', 'password' => 'x', 'tenant_id' => $tenantId, 'is_active' => 1]);
        $projectId = $this->insertFactory('projects', \App\Models\Project::factory()->raw(['tenant_id' => $tenantId, 'pm_id' => $pm, 'created_by' => $pm]));
        $taskId = $this->insertFactory('tasks', \App\Models\Task::factory()->raw(['tenant_id' => $tenantId, 'project_id' => $projectId, 'assignee_id' => null, 'created_by' => $pm, 'status' => 'pending']));

        $php = (new PhpExecutableFinder())->find();
        $script = base_path('tests/Support/Concurrency/task-assignment-share-race.php');
        $env = $this->childEnv();
        $procA = new Process([$php, $script, $taskId, $userA, $tenantId, '60', '1500'], base_path(), $env, null, 60);
        $procB = new Process([$php, $script, $taskId, $userB, $tenantId, '60', '1500'], base_path(), $env, null, 60);
        $procA->start();
        $procB->start();
        $procA->wait();
        $procB->wait();

        $codes = [$procA->getExitCode(), $procB->getExitCode()];
        sort($codes);
        $this->assertSame([0, 1], $codes, 'exactly one allocation may succeed. A: ' . $procA->getOutput() . $procA->getErrorOutput() . ' B: ' . $procB->getOutput() . $procB->getErrorOutput());
        $loser = $procA->getExitCode() === 1 ? $procA->getOutput() : $procB->getOutput();
        $this->assertStringContainsString('CONFLICT exceeds_total', $loser, 'the loser must fail cleanly on the 100 % rule, not on a deadlock');

        $this->assertSame(1, DB::table('task_assignments')->where('task_id', $taskId)->count());
        $this->assertSame($usersBefore + 3, DB::table('users')->count(), 'only the 3 tracked users were created (nothing leaks past tearDown)');
        $this->assertSame($tenantsBefore + 1, DB::table('tenants')->count(), 'only the tracked tenant was created');
        $this->assertEqualsWithDelta(60.0, (float) DB::table('task_assignments')->where('task_id', $taskId)->sum('split_percent'), 0.001);
    }

    /** @return array<string, string> DB settings of the current connection for the child processes */
    private function childEnv(): array
    {
        $c = DB::connection()->getConfig();

        return [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'ZENA_INVARIANTS_DB' => 'mysql',
            'DB_HOST' => (string) $c['host'], 'DB_PORT' => (string) $c['port'], 'DB_DATABASE' => (string) $c['database'],
            'DB_USERNAME' => (string) $c['username'], 'DB_PASSWORD' => (string) $c['password'],
            'CACHE_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
        ];
    }

    /** @param array<string, mixed> $values */
    private function insert(string $table, array $values): string
    {
        $id = (string) Str::ulid();
        DB::table($table)->insert(['id' => $id, 'created_at' => now(), 'updated_at' => now()] + $values);
        $this->created[$table][] = $id;

        return $id;
    }

    /** @param array<string, mixed> $raw */
    private function insertFactory(string $table, array $raw): string
    {
        $values = array_map(static fn ($v) => is_array($v) ? json_encode($v) : $v, $raw);
        unset($values['id']);

        return $this->insert($table, $values);
    }
}
