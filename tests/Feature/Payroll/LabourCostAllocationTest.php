<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\AuditLog;
use App\Models\Payroll\LabourCostAllocation;
use App\Models\Payroll\PayrollLine;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\StaffMember;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\TimeTracking\TimeEntry;
use App\Models\User;
use App\Services\Payroll\LabourCostService;
use App\Services\Payroll\PayrollRuleViolation;
use App\Services\Payroll\PayrollService;
use App\Services\Payroll\StaffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-113 (Gate 2 S-1, H-1, R-1, O-1) — labour cost allocation at payroll close.
 *
 * - S-1: allocations are stored at close, cleared on reopen, recomputed on re-close.
 * - H-1: close is blocked while the month has running / submitted time entries.
 * - R-1: project amount = company cost × approved project hours ÷ max(total approved hours, standard hours).
 * - O-1: office (is_overhead) staff cost stays outside projects.
 * - Also covered: no-login staff, unpriced hours, whole-đồng rounding with exact totals, audit, tenant isolation.
 */
class LabourCostAllocationTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;

    private User $owner;

    private User $workerA;

    private User $workerB;

    private User $office;

    private User $noStaff;

    private Project $p1;

    private Project $p2;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 09:00:00');
        $this->tenant = Tenant::factory()->create();
        $this->owner = $this->createTenantUser($this->tenant, ['name' => 'Chủ'], ['lc_owner'], ['payroll.manage', 'payroll.close', 'project.update', 'task.view']);
        $this->workerA = $this->createTenantUser($this->tenant, ['name' => 'Kỹ sư A'], ['lc_worker_a'], ['task.view']);
        $this->workerB = $this->createTenantUser($this->tenant, ['name' => 'Kỹ sư B'], ['lc_worker_b'], ['task.view']);
        $this->office = $this->createTenantUser($this->tenant, ['name' => 'Văn phòng C'], ['lc_office'], ['task.view']);
        $this->noStaff = $this->createTenantUser($this->tenant, ['name' => 'Thời vụ D'], ['lc_temp'], ['task.view']);
        $this->p1 = Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'name' => 'Nhà phố Q7']);
        $this->p2 = Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'name' => 'Biệt thự Thảo Điền']);

        $staff = app(StaffService::class);
        $staff->create($this->owner, ['code' => 'NV01', 'name' => 'Kỹ sư A', 'staff_type' => 'employee', 'user_id' => (string) $this->workerA->id, 'start_date' => '2025-01-01']);
        $staff->create($this->owner, ['code' => 'NV02', 'name' => 'Kỹ sư B', 'staff_type' => 'employee', 'user_id' => (string) $this->workerB->id, 'start_date' => '2025-01-01']);
        $staff->create($this->owner, ['code' => 'VP01', 'name' => 'Văn phòng C', 'staff_type' => 'employee', 'is_overhead' => '1', 'user_id' => (string) $this->office->id, 'start_date' => '2025-01-01']);
        $staff->create($this->owner, ['code' => 'NV03', 'name' => 'Không tài khoản', 'staff_type' => 'employee', 'start_date' => '2025-01-01']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_close_allocates_by_r1_with_o1_no_login_and_unpriced_hours(): void
    {
        $period = $this->october([
            'NV01' => '10400000', // 208 h standard → 50,000 / h
            'NV02' => '6000000',
            'VP01' => '8000000',
            'NV03' => '5000000',
        ]);
        // A: under standard hours (170 h approved) — projects get hours × rate, the rest stays outside projects.
        $this->entry($this->workerA, $this->p1, '2026-10-05', 100 * 60);
        $this->entry($this->workerA, $this->p2, '2026-10-06', 50 * 60);
        $this->entry($this->workerA, null, '2026-10-07', 20 * 60);
        $this->entry($this->workerA, $this->p1, '2026-10-08', 30 * 60, TimeEntry::STATUS_REJECTED);
        $this->entry($this->workerA, $this->p1, '2026-11-02', 10 * 60); // another month
        // B: over standard hours (250 h approved) — shares scale so the total stays at the real cost.
        $this->entry($this->workerB, $this->p1, '2026-10-05', 150 * 60);
        $this->entry($this->workerB, $this->p2, '2026-10-06', 90 * 60);
        $this->entry($this->workerB, null, '2026-10-07', 10 * 60);
        // Office staff (O-1): project hours do not become project money.
        $this->entry($this->office, $this->p1, '2026-10-05', 40 * 60);
        // A login without a staff record: hours without a price.
        $this->entry($this->noStaff, $this->p1, '2026-10-05', 10 * 60);

        app(PayrollService::class)->close($this->owner, $period);

        $rows = $this->allocations($period);
        $this->assertSame(['5000000.00', 6000], $rows['NV01|' . $this->p1->id]);
        $this->assertSame(['2500000.00', 3000], $rows['NV01|' . $this->p2->id]);
        $this->assertSame(['2900000.00', 1200], $rows['NV01|-']);
        $this->assertSame(['3600000.00', 9000], $rows['NV02|' . $this->p1->id]);
        $this->assertSame(['2160000.00', 5400], $rows['NV02|' . $this->p2->id]);
        $this->assertSame(['240000.00', 600], $rows['NV02|-']);
        $this->assertSame(['8000000.00', 2400], $rows['VP01|-']);
        $this->assertSame(['5000000.00', 0], $rows['NV03|-']);
        $this->assertCount(8, $rows);

        $preview = app(LabourCostService::class)->compute($period->fresh());
        $this->assertSame([['user_id' => (string) $this->noStaff->id, 'project_id' => (string) $this->p1->id, 'minutes' => 600]], $preview['unpriced']);

        $audit = AuditLog::query()->where('action', 'labour_cost.allocated')->sole();
        $this->assertSame('13260000.00', $audit->new_data['project_total']);
        $this->assertSame('16140000.00', $audit->new_data['outside_total']);
    }

    public function test_h1_blocks_close_while_hours_are_running_or_waiting(): void
    {
        $period = $this->october(['NV01' => '10400000']);
        $waiting = $this->entry($this->workerA, $this->p1, '2026-10-05', 120, TimeEntry::STATUS_SUBMITTED);
        $running = $this->entry($this->workerB, $this->p1, '2026-10-31', 0, TimeEntry::STATUS_RUNNING);
        $this->entry($this->workerA, $this->p1, '2026-11-01', 60, TimeEntry::STATUS_SUBMITTED); // November: not counted

        $pending = app(LabourCostService::class)->pendingEntries($period);
        $expected = [(string) $this->workerA->id => 1, (string) $this->workerB->id => 1];
        ksort($expected);
        $this->assertSame($expected, $pending);

        $this->assertRule(fn () => app(PayrollService::class)->close($this->owner, $period), 'chờ duyệt');
        $this->assertFalse($period->fresh()->isClosed());
        $this->assertSame(0, LabourCostAllocation::query()->withoutGlobalScopes()->count());

        $waiting->forceFill(['status' => TimeEntry::STATUS_APPROVED])->save();
        $running->forceFill(['status' => TimeEntry::STATUS_REJECTED])->save();
        app(PayrollService::class)->close($this->owner, $period);
        $this->assertTrue($period->fresh()->isClosed());
    }

    public function test_reopen_clears_and_reclose_recomputes(): void
    {
        $period = $this->october(['NV01' => '10400000']);
        $this->entry($this->workerA, $this->p1, '2026-10-05', 104 * 60);
        $payroll = app(PayrollService::class);
        $payroll->close($this->owner, $period);
        $this->assertSame(['5200000.00', 6240], $this->allocations($period)['NV01|' . $this->p1->id]);

        $payroll->reopen($this->owner, $period, 'Sửa lương');
        $this->assertSame(0, LabourCostAllocation::query()->withoutGlobalScopes()->where('payroll_period_id', $period->id)->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'labour_cost.cleared')->count());

        $payroll->updateLine($this->owner, $this->lineOf($period, 'NV01'), ['base_salary' => '20800000']);
        $payroll->close($this->owner, $period->fresh());
        $this->assertSame(['10400000.00', 6240], $this->allocations($period)['NV01|' . $this->p1->id]);
        $this->assertSame(2, AuditLog::query()->where('action', 'labour_cost.allocated')->count());
    }

    public function test_rounding_is_whole_dong_and_sums_to_company_cost(): void
    {
        $staff = app(StaffService::class)->create($this->owner, ['code' => 'NV09', 'name' => 'Ba việc', 'staff_type' => 'collaborator', 'user_id' => (string) $this->noStaff->id, 'standard_hours_override' => '3', 'start_date' => '2025-01-01']);
        $p3 = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        $period = $this->october(['NV09' => '1000000.50']);
        foreach ([$this->p1, $this->p2, $p3] as $project) {
            $this->entry($this->noStaff, $project, '2026-10-05', 60);
        }

        app(PayrollService::class)->close($this->owner, $period);

        $rows = LabourCostAllocation::query()->withoutGlobalScopes()->where('staff_member_id', $staff->id)->get();
        $projectRows = $rows->whereNotNull('project_id');
        $this->assertCount(3, $projectRows);
        foreach ($projectRows as $row) {
            $this->assertStringEndsWith('.00', (string) $row->amount);
        }
        $this->assertEqualsCanonicalizing(['333334.00', '333333.00', '333333.00'], $projectRows->pluck('amount')->map(fn ($v) => (string) $v)->all());
        $this->assertSame('0.50', (string) $rows->whereNull('project_id')->sole()->amount);
    }

    public function test_tenant_isolation(): void
    {
        $period = $this->october(['NV01' => '10400000']);
        $this->entry($this->workerA, $this->p1, '2026-10-05', 60);

        $other = Tenant::factory()->create();
        $otherOwner = $this->createTenantUser($other, [], ['lc_other_owner'], ['payroll.manage', 'payroll.close']);
        $otherPeriod = app(PayrollService::class)->createPeriod($otherOwner, '2026-10', 26);
        $this->assertRule(fn () => app(PayrollService::class)->close($otherOwner, $period), 'không thuộc công ty');
        app(PayrollService::class)->close($otherOwner, $otherPeriod);
        app(PayrollService::class)->close($this->owner, $period);

        $this->assertSame(0, LabourCostAllocation::query()->withoutGlobalScopes()->where('payroll_period_id', $otherPeriod->id)->count());
        $ours = LabourCostAllocation::query()->withoutGlobalScopes()->where('payroll_period_id', $period->id)->get();
        $this->assertNotEmpty($ours);
        $this->assertSame([(string) $this->tenant->id], $ours->pluck('tenant_id')->unique()->values()->all());
    }

    /**
     * @param array<string, string> $salaries staff code => base salary
     */
    private function october(array $salaries): PayrollPeriod
    {
        $payroll = app(PayrollService::class);
        $period = $payroll->createPeriod($this->owner, '2026-10', 26);
        foreach ($salaries as $code => $salary) {
            $payroll->updateLine($this->owner, $this->lineOf($period, $code), ['base_salary' => $salary]);
        }

        return $period->fresh() ?? $period;
    }

    private function lineOf(PayrollPeriod $period, string $code): PayrollLine
    {
        /** @var StaffMember $staff */
        $staff = StaffMember::query()->withoutGlobalScopes()->where('tenant_id', (string) $period->tenant_id)->where('code', $code)->firstOrFail();
        /** @var PayrollLine $line */
        $line = PayrollLine::query()->withoutGlobalScopes()->where('payroll_period_id', $period->id)->where('staff_member_id', $staff->id)->firstOrFail();

        return $line;
    }

    private function entry(User $user, ?Project $project, string $date, int $minutes, string $status = TimeEntry::STATUS_APPROVED): TimeEntry
    {
        return TimeEntry::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $user->tenant_id,
            'user_id' => (string) $user->id,
            'project_id' => $project?->id,
            'category' => $project === null ? TimeEntry::CATEGORY_MEETING : null,
            'work_date' => $date,
            'minutes' => $minutes,
            'source' => TimeEntry::SOURCE_MANUAL,
            'note' => 'test',
            'status' => $status,
        ]);
    }

    /**
     * @return array<string, array{0: string, 1: int}> "code|project id or -" => [amount, minutes]
     */
    private function allocations(PayrollPeriod $period): array
    {
        $out = [];
        $rows = LabourCostAllocation::query()->withoutGlobalScopes()->with(['staffMember' => static fn ($q) => $q->withoutGlobalScopes()])
            ->where('payroll_period_id', $period->id)->get();
        foreach ($rows as $row) {
            $out[$row->staffMember->code . '|' . ($row->project_id ?? '-')] = [(string) $row->amount, (int) $row->minutes];
        }

        return $out;
    }

    private function assertRule(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail('Expected a PayrollRuleViolation.');
        } catch (PayrollRuleViolation $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }
}
