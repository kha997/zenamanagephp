<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Models\Payroll\OverheadPeriod;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\TimeTracking\TimeEntry;
use App\Models\User;
use App\Services\Payroll\OverheadService;
use App\Services\Payroll\PayrollService;
use App\Services\Payroll\StaffService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-121 (Gate 2 S-1) — the month close holds under real concurrency. Two independent OS processes / MySQL
 * connections race on the same month; process A holds its transaction (and locks) open for a few seconds, B starts
 * once A reports it holds them, and the test checks B reached its call before A released. Sequential sqlite calls cannot prove this, so the test skips without MySQL (the CI job
 * scripts/ci/month-close-concurrency-mysql fails on a skip).
 *
 * - 1a payroll close vs a time entry added into the month: the entry waits, then is refused (month closed).
 * - 1b a time entry added first vs payroll close: the close waits, then is refused (hours still waiting).
 * - 2  two payroll closes: exactly one; labour cost allocated once.
 * - 3a overhead close vs payroll reopen: reopen refused; never "overhead closed, payroll open".
 * - 3b payroll reopen vs overhead close: overhead close refused (payroll no longer closed).
 * - 4  two overhead closes: exactly one; one set of overhead allocations.
 */
#[Group('stress')]
class MonthCloseConcurrencyTest extends TestCase
{
    use TenantUserFactoryTrait;

    private const MONTH = '2026-10';

    private ?string $originalDefaultConnection = null;

    private Tenant $tenant;

    private User $owner;

    private User $worker;

    private PayrollPeriod $payroll;

    protected function tearDown(): void
    {
        try {
            if (DB::connection('mysql')->getPdo()) {
                foreach (['audit_logs', 'overhead_allocations', 'overhead_cost_lines', 'overhead_periods', 'labour_cost_allocations', 'payroll_lines',
                    'payroll_periods', 'staff_members', 'time_entries', 'projects', 'user_roles', 'users', 'tenants'] as $table) {
                    DB::connection('mysql')->table($table)->delete();
                }
            }
        } catch (\Throwable) {
            // MySQL not reachable — nothing to clean up.
        }
        if ($this->originalDefaultConnection !== null) {
            DB::setDefaultConnection($this->originalDefaultConnection);
        }
        parent::tearDown();
    }

    public function test_1a_payroll_close_first_then_a_time_entry_into_the_month_is_refused(): void
    {
        $this->fixture();
        [$a, $b] = $this->race(
            ['payroll:concurrency-test-close', (string) $this->payroll->id, (string) $this->owner->id],
            ['time:concurrency-test-add', (string) $this->worker->id, self::MONTH . '-20', '60'],
        );

        $this->assertProcess($a, 0, 'OK', [$a, $b]);
        $this->assertProcess($b, 1, 'đã chốt', [$a, $b]);
        $this->assertSame(PayrollPeriod::STATUS_CLOSED, $this->payrollStatus());
        $this->assertSame(0, $this->entriesIn([TimeEntry::STATUS_SUBMITTED, TimeEntry::STATUS_RUNNING]));
        // Labour cost allocated from exactly the approved hours (the one 600-minute entry on the project).
        $this->assertSame(600, (int) DB::table('labour_cost_allocations')->where('payroll_period_id', (string) $this->payroll->id)->sum('minutes'));
    }

    public function test_1b_a_time_entry_first_then_payroll_close_is_refused(): void
    {
        $this->fixture();
        [$a, $b] = $this->race(
            ['time:concurrency-test-add', (string) $this->worker->id, self::MONTH . '-20', '60'],
            ['payroll:concurrency-test-close', (string) $this->payroll->id, (string) $this->owner->id],
        );

        $this->assertProcess($a, 0, 'OK', [$a, $b]);
        $this->assertProcess($b, 1, 'chờ duyệt', [$a, $b]);
        $this->assertSame(PayrollPeriod::STATUS_DRAFT, $this->payrollStatus());
        $this->assertSame(1, $this->entriesIn([TimeEntry::STATUS_SUBMITTED]));
        $this->assertSame(0, DB::table('labour_cost_allocations')->count());
    }

    public function test_2_two_payroll_closes_allocate_once(): void
    {
        $this->fixture();
        [$a, $b] = $this->race(
            ['payroll:concurrency-test-close', (string) $this->payroll->id, (string) $this->owner->id],
            ['payroll:concurrency-test-close', (string) $this->payroll->id, (string) $this->owner->id],
        );

        $this->assertProcess($a, 0, 'OK', [$a, $b]);
        $this->assertProcess($b, 1, 'REFUSED', [$a, $b]);
        $this->assertSame(PayrollPeriod::STATUS_CLOSED, $this->payrollStatus());
        $rows = DB::table('labour_cost_allocations')->where('payroll_period_id', (string) $this->payroll->id)->get();
        $this->assertGreaterThan(0, $rows->count());
        $keys = $rows->map(fn ($row): string => $row->staff_member_id . '|' . ($row->project_id ?? '-'))->all();
        $this->assertSame(count($keys), count(array_unique($keys)), 'labour cost must be allocated once');
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'payroll.period.closed')->count());
    }

    public function test_3a_overhead_close_first_then_payroll_reopen_is_refused(): void
    {
        $this->fixture();
        $overhead = $this->closedPayrollAndOverheadDraft();
        [$a, $b] = $this->race(
            ['overhead:concurrency-test-close', (string) $overhead->id, (string) $this->owner->id],
            ['payroll:concurrency-test-reopen', (string) $this->payroll->id, (string) $this->owner->id, 'Sửa lương'],
        );

        $this->assertProcess($a, 0, 'OK', [$a, $b]);
        $this->assertProcess($b, 1, 'chi phí chung', [$a, $b]);
        $this->assertSame(PayrollPeriod::STATUS_CLOSED, $this->payrollStatus());
        $this->assertSame(OverheadPeriod::STATUS_CLOSED, $this->overheadStatus());
    }

    public function test_3b_payroll_reopen_first_then_overhead_close_is_refused(): void
    {
        $this->fixture();
        $overhead = $this->closedPayrollAndOverheadDraft();
        [$a, $b] = $this->race(
            ['payroll:concurrency-test-reopen', (string) $this->payroll->id, (string) $this->owner->id, 'Sửa lương'],
            ['overhead:concurrency-test-close', (string) $overhead->id, (string) $this->owner->id],
        );

        $this->assertProcess($a, 0, 'OK', [$a, $b]);
        $this->assertProcess($b, 1, 'chốt lương', [$a, $b]);
        $this->assertSame(PayrollPeriod::STATUS_DRAFT, $this->payrollStatus());
        $this->assertSame(OverheadPeriod::STATUS_DRAFT, $this->overheadStatus());
        $this->assertSame(0, DB::table('overhead_allocations')->count());
    }

    public function test_4_two_overhead_closes_allocate_once(): void
    {
        $this->fixture();
        $overhead = $this->closedPayrollAndOverheadDraft();
        [$a, $b] = $this->race(
            ['overhead:concurrency-test-close', (string) $overhead->id, (string) $this->owner->id],
            ['overhead:concurrency-test-close', (string) $overhead->id, (string) $this->owner->id],
        );

        $this->assertProcess($a, 0, 'OK', [$a, $b]);
        $this->assertProcess($b, 1, 'REFUSED', [$a, $b]);
        $this->assertSame(OverheadPeriod::STATUS_CLOSED, $this->overheadStatus());
        $keys = DB::table('overhead_allocations')->where('overhead_period_id', (string) $overhead->id)->pluck('project_id')->map(fn ($id): string => (string) ($id ?? '-'))->all();
        $this->assertGreaterThan(0, count($keys));
        $this->assertSame(count($keys), count(array_unique($keys)), 'overhead must be allocated once');
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'overhead.period.closed')->count());
    }

    /**
     * A tenant with an owner (payroll.manage + payroll.close), one staff member linked to a worker, an open payroll month
     * and one approved 600-minute project entry in it.
     */
    private function fixture(): void
    {
        $this->skipUnlessMysqlAvailable();
        $this->originalDefaultConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('mysql');

        $this->tenant = Tenant::on('mysql')->create(Tenant::factory()->raw());
        $this->owner = $this->createTenantUser($this->tenant, ['name' => 'Chủ'], ['mc_owner'], ['payroll.manage', 'payroll.close', 'project.update', 'task.view']);
        $this->worker = $this->createTenantUser($this->tenant, ['name' => 'Kỹ sư A'], ['mc_worker'], ['task.view']);
        $project = Project::on('mysql')->create(Project::factory()->raw([
            'tenant_id' => $this->tenant->id, 'pm_id' => $this->owner->id, 'created_by' => $this->owner->id,
        ]));
        app(StaffService::class)->create($this->owner, [
            'code' => 'NV01', 'name' => 'Kỹ sư A', 'staff_type' => 'employee', 'user_id' => (string) $this->worker->id, 'start_date' => '2025-01-01',
        ]);
        $this->payroll = app(PayrollService::class)->createPeriod($this->owner, self::MONTH, 26);
        TimeEntry::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $this->tenant->id, 'user_id' => (string) $this->worker->id, 'project_id' => (string) $project->id,
            'work_date' => self::MONTH . '-05', 'minutes' => 600, 'source' => TimeEntry::SOURCE_MANUAL, 'note' => 'fixture',
            'status' => TimeEntry::STATUS_APPROVED,
        ]);
    }

    private function closedPayrollAndOverheadDraft(): OverheadPeriod
    {
        app(PayrollService::class)->close($this->owner, $this->payroll);
        // One cost line so the close has something to allocate to the project.
        app(OverheadService::class)->addLine($this->owner, self::MONTH, ['category' => 'rent', 'description' => 'Thuê văn phòng', 'amount' => '1000000']);
        /** @var OverheadPeriod $overhead */
        $overhead = OverheadPeriod::query()->withoutGlobalScopes()->where('tenant_id', (string) $this->tenant->id)->where('period', self::MONTH)->firstOrFail();

        return $overhead;
    }

    /**
     * Starts A with --hold, waits until A reports it holds its locks ("LOCKED"), then starts B and waits for both.
     * Asserts B reached the contested call ("START <t>") before A released ("RELEASE <t>"), so a slow runner cannot turn
     * the race into two sequential runs that pass without any lock contention.
     *
     * @param list<string> $a
     * @param list<string> $b
     *
     * @return array{0: Process, 1: Process}
     */
    private function race(array $a, array $b): array
    {
        $php = (new PhpExecutableFinder())->find();
        $procA = new Process([$php, 'artisan', ...$a, '--hold=5'], base_path(), ['DB_CONNECTION' => 'mysql']);
        $procB = new Process([$php, 'artisan', ...$b, '--hold=0'], base_path(), ['DB_CONNECTION' => 'mysql']);
        $procA->setTimeout(60);
        $procB->setTimeout(60);

        $procA->start();
        $deadline = microtime(true) + 30;
        while (!str_contains($procA->getOutput(), 'LOCKED')) {
            if (!$procA->isRunning() || microtime(true) > $deadline) {
                $procA->stop();
                $this->fail('A never reported holding its locks: ' . $procA->getOutput() . $procA->getErrorOutput());
            }
            usleep(20_000);
        }
        $procB->start();
        $procA->wait();
        $procB->wait();

        $bStart = $this->stamp($procB, 'START');
        $aRelease = $this->stamp($procA, 'RELEASE');
        $this->assertLessThan(
            $aRelease,
            $bStart,
            'B must reach the contested call while A still holds its locks. A: ' . $procA->getOutput() . ' | B: ' . $procB->getOutput()
        );

        return [$procA, $procB];
    }

    private function stamp(Process $process, string $marker): float
    {
        $this->assertSame(1, preg_match('/^' . $marker . ' (\d+\.\d+)$/m', $process->getOutput(), $m), $marker . ' missing: ' . $process->getOutput() . $process->getErrorOutput());

        return (float) $m[1];
    }

    /**
     * @param array{0: Process, 1: Process} $both
     */
    private function assertProcess(Process $process, int $exitCode, string $needle, array $both): void
    {
        $context = 'A: ' . $both[0]->getOutput() . $both[0]->getErrorOutput() . ' | B: ' . $both[1]->getOutput() . $both[1]->getErrorOutput();
        $this->assertSame($exitCode, $process->getExitCode(), $context);
        $this->assertStringContainsString($needle, $process->getOutput(), $context);
    }

    private function payrollStatus(): string
    {
        return (string) DB::table('payroll_periods')->where('id', (string) $this->payroll->id)->value('status');
    }

    private function overheadStatus(): string
    {
        return (string) DB::table('overhead_periods')->where('tenant_id', (string) $this->tenant->id)->where('period', self::MONTH)->value('status');
    }

    /**
     * @param list<string> $statuses
     */
    private function entriesIn(array $statuses): int
    {
        return DB::table('time_entries')->where('tenant_id', (string) $this->tenant->id)->whereIn('status', $statuses)
            ->whereDate('work_date', '>=', self::MONTH . '-01')->whereDate('work_date', '<=', self::MONTH . '-31')->count();
    }

    /** @group stress */
    private function skipUnlessMysqlAvailable(): void
    {
        try {
            DB::connection('mysql')->select('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                'dependency: real MySQL connection required to prove the month-close locks, not sqlite. '
                . 'The "mysql" connection is not reachable here (' . $e->getMessage() . ').'
            );
        }
    }
}
