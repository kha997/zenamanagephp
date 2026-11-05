<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\AuditLog;
use App\Models\Payroll\OverheadAllocation;
use App\Models\Payroll\OverheadCostLine;
use App\Models\Payroll\OverheadPeriod;
use App\Models\Payroll\PayrollLine;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\StaffMember;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\TimeTracking\TimeEntry;
use App\Models\User;
use App\Services\Payroll\OverheadService;
use App\Services\Payroll\PayrollRuleViolation;
use App\Services\Payroll\PayrollService;
use App\Services\Payroll\StaffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-114 (Gate 2 N-1, C-1, P-1, A-1) — monthly overhead.
 *
 * - N-1: cost lines entered per month, editable until the month is closed.
 * - C-1: overhead close needs the payroll month closed and stores the split; reopen clears it; payroll reopen is refused
 *   while overhead is closed.
 * - P-1: pool = lines + GAP-113 labour outside projects.
 * - A-1: split by approved project minutes, whole đồng, totals matching; no project hours leaves it unallocated.
 * - Also covered: audit, permissions, tenant isolation, pages.
 */
class OverheadTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;

    private User $owner;

    private User $accountant;

    private User $engineer;

    private User $worker;

    private User $office;

    private Project $p1;

    private Project $p2;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 09:00:00');
        $this->tenant = Tenant::factory()->create();
        $this->owner = $this->createTenantUser($this->tenant, ['name' => 'Chủ'], ['oh_owner'], ['payroll.manage', 'payroll.close', 'project.update', 'task.view']);
        $this->accountant = $this->createTenantUser($this->tenant, ['name' => 'Kế toán'], ['oh_accountant'], ['payroll.manage', 'task.view']);
        $this->engineer = $this->createTenantUser($this->tenant, ['name' => 'Kỹ sư'], ['oh_engineer'], ['task.view']);
        $this->worker = $this->createTenantUser($this->tenant, ['name' => 'Kỹ sư A'], ['oh_worker'], ['task.view']);
        $this->office = $this->createTenantUser($this->tenant, ['name' => 'Văn phòng C'], ['oh_office'], ['task.view']);
        $this->p1 = Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'name' => 'Nhà phố Q7']);
        $this->p2 = Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'name' => 'Biệt thự Thảo Điền']);
        $staff = app(StaffService::class);
        $staff->create($this->owner, ['code' => 'NV01', 'name' => 'Kỹ sư A', 'staff_type' => 'employee', 'user_id' => (string) $this->worker->id, 'start_date' => '2025-01-01']);
        $staff->create($this->owner, ['code' => 'VP01', 'name' => 'Văn phòng C', 'staff_type' => 'employee', 'is_overhead' => '1', 'user_id' => (string) $this->office->id, 'start_date' => '2025-01-01']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_lines_close_split_lock_and_reopen(): void
    {
        $payroll = $this->october(['NV01' => '10400000', 'VP01' => '8000000']);
        $this->entry($this->worker, $this->p1, '2026-10-05', 100 * 60);
        $this->entry($this->worker, $this->p2, '2026-10-06', 50 * 60);
        $this->entry($this->worker, null, '2026-10-07', 20 * 60);
        $overhead = app(OverheadService::class);

        $rent = $overhead->addLine($this->accountant, '2026-10', ['category' => 'rent', 'description' => 'Thuê văn phòng T10', 'amount' => '20000000']);
        $power = $overhead->addLine($this->accountant, '2026-10', ['category' => 'utilities', 'description' => 'Điện', 'amount' => '1000000']);
        $overhead->updateLine($this->accountant, $power, ['category' => 'utilities', 'description' => 'Điện + nước', 'amount' => '1100000']);
        $temp = $overhead->addLine($this->accountant, '2026-10', ['category' => 'other', 'description' => 'Nhập nhầm', 'amount' => '5']);
        $overhead->deleteLine($this->accountant, $temp);
        $period = OverheadPeriod::query()->withoutGlobalScopes()->sole();
        $this->assertSame(2, OverheadCostLine::query()->withoutGlobalScopes()->count());

        // C-1: the payroll month must be closed first.
        $this->assertRule(fn () => $overhead->close($this->owner, $period), 'chốt lương');
        app(PayrollService::class)->close($this->owner, $payroll);
        $this->assertRule(fn () => $overhead->close($this->accountant, $period), 'quyền');

        $overhead->close($this->owner, $period);
        $this->assertTrue($period->fresh()?->isClosed());
        // P-1: 21.1 M lines + labour outside projects (A: 2.9 M idle / meeting share, office staff 8 M) = 32 M.
        // A-1: 6,000 vs 3,000 project minutes; whole đồng with the spare đồng on the larger remainder.
        $rows = $this->allocations($period);
        $this->assertSame(['21333333.00', 6000], $rows[(string) $this->p1->id]);
        $this->assertSame(['10666667.00', 3000], $rows[(string) $this->p2->id]);
        $this->assertArrayNotHasKey('-', $rows);
        $audit = AuditLog::query()->where('action', 'overhead.period.closed')->sole();
        $this->assertSame('32000000.00', $audit->new_data['pool']);
        $this->assertSame('21100000.00', $audit->new_data['lines_total']);
        $this->assertSame('10900000.00', $audit->new_data['labour_outside']);

        $this->assertRule(fn () => $overhead->addLine($this->accountant, '2026-10', ['category' => 'rent', 'description' => 'x', 'amount' => '1']), 'đã chốt');
        $this->assertRule(fn () => $overhead->updateLine($this->accountant, $rent, ['category' => 'rent', 'description' => 'x', 'amount' => '1']), 'đã chốt');
        $this->assertRule(fn () => $overhead->deleteLine($this->accountant, $rent), 'đã chốt');
        $this->assertRule(fn () => app(PayrollService::class)->reopen($this->owner, $payroll, 'Sửa'), 'chi phí chung');

        $this->assertRule(fn () => $overhead->reopen($this->owner, $period, ' '), 'lý do');
        $overhead->reopen($this->owner, $period, 'Bổ sung hoá đơn');
        $this->assertFalse($period->fresh()?->isClosed());
        $this->assertSame(0, OverheadAllocation::query()->withoutGlobalScopes()->count());
        $overhead->addLine($this->accountant, '2026-10', ['category' => 'internet', 'description' => 'Internet', 'amount' => '1000000']);
        $overhead->close($this->owner, $period->fresh() ?? $period);
        $rows = $this->allocations($period);
        $this->assertSame('22000000.00', $rows[(string) $this->p1->id][0]);
        $this->assertSame('11000000.00', $rows[(string) $this->p2->id][0]);
        $this->assertSame(1, AuditLog::query()->where('action', 'overhead.period.reopened')->count());
        // Lines: add rent, add power, update power, add + delete the mistaken one, add internet.
        $this->assertSame(6, AuditLog::query()->where('action', 'like', 'overhead.line.%')->count());
    }

    public function test_no_project_hours_leaves_the_pool_unallocated_and_cents_stay_outside(): void
    {
        $payroll = $this->october(['NV01' => '1000000', 'VP01' => '0']);
        $this->entry($this->worker, null, '2026-10-05', 600);
        app(PayrollService::class)->close($this->owner, $payroll);
        $overhead = app(OverheadService::class);
        $overhead->addLine($this->accountant, '2026-10', ['category' => 'rent', 'description' => 'Thuê', 'amount' => '500000.50']);
        $period = OverheadPeriod::query()->withoutGlobalScopes()->sole();
        $overhead->close($this->owner, $period);
        $this->assertSame(['-' => ['1500000.50', 0]], $this->allocations($period));
    }

    public function test_validation_permissions_pages_and_tenant_isolation(): void
    {
        $overhead = app(OverheadService::class);
        $this->assertRule(fn () => $overhead->addLine($this->engineer, '2026-10', ['category' => 'rent', 'description' => 'x', 'amount' => '1']), 'quyền');
        $this->assertRule(fn () => $overhead->addLine($this->accountant, '2026-13', ['category' => 'rent', 'description' => 'x', 'amount' => '1']), 'Tháng');
        $this->assertRule(fn () => $overhead->addLine($this->accountant, '2026-10', ['category' => 'party', 'description' => 'x', 'amount' => '1']), 'Loại');
        $this->assertRule(fn () => $overhead->addLine($this->accountant, '2026-10', ['category' => 'rent', 'description' => 'x', 'amount' => '0']), 'Số tiền');
        $this->assertRule(fn () => $overhead->addLine($this->accountant, '2026-10', ['category' => 'rent', 'description' => 'x', 'amount' => 'abc']), 'Số tiền');
        $this->assertRule(fn () => $overhead->addLine($this->accountant, '2026-10', ['category' => 'rent', 'description' => ' ', 'amount' => '1']), 'Mô tả');

        $page = route('operator.payroll.overhead', ['month' => '2026-10']);
        $this->actingAs($this->accountant)->get($page)->assertOk()->assertSee('overhead-line-form', false)->assertDontSee('overhead-close-form', false);
        $this->actingAs($this->accountant)->post(route('operator.payroll.overhead.lines.store'), ['month' => '2026-10', 'category' => 'rent', 'description' => 'Thuê nhà số 12 Lê Lợi', 'amount' => '20000000'])
            ->assertRedirect($page);
        $line = OverheadCostLine::query()->withoutGlobalScopes()->sole();
        $this->actingAs($this->accountant)->get($page)->assertOk()->assertSee('Thuê nhà số 12 Lê Lợi')->assertSee('20.000.000');
        $this->actingAs($this->owner)->get($page)->assertOk()->assertSee('overhead-close-form', false);
        $this->actingAs($this->engineer)->get($page)->assertRedirect();
        $this->actingAs($this->accountant)->get(route('operator.payroll.index'))->assertSee(route('operator.payroll.overhead'), false);

        $other = Tenant::factory()->create();
        $stranger = $this->createTenantUser($other, [], ['oh_stranger'], ['payroll.manage', 'payroll.close']);
        $this->actingAs($stranger)->post(route('operator.payroll.overhead.lines.update', $line->id), ['category' => 'rent', 'description' => 'x', 'amount' => '1'])->assertNotFound();
        $this->actingAs($stranger)->post(route('operator.payroll.overhead.lines.delete', $line->id))->assertNotFound();
        $period = OverheadPeriod::query()->withoutGlobalScopes()->sole();
        $this->actingAs($stranger)->post(route('operator.payroll.overhead.close', $period->id))->assertNotFound();
        $this->assertSame('20000000.00', (string) $line->fresh()?->amount);
        $this->actingAs($stranger)->get(route('operator.payroll.overhead', ['month' => '2026-10']))->assertOk()->assertDontSee('Thuê nhà số 12 Lê Lợi');
    }

    /**
     * @param array<string, string> $salaries
     */
    private function october(array $salaries): PayrollPeriod
    {
        $payroll = app(PayrollService::class);
        $period = $payroll->createPeriod($this->owner, '2026-10', 26);
        foreach ($salaries as $code => $salary) {
            /** @var StaffMember $staff */
            $staff = StaffMember::query()->withoutGlobalScopes()->where('tenant_id', (string) $this->tenant->id)->where('code', $code)->firstOrFail();
            /** @var PayrollLine $line */
            $line = PayrollLine::query()->withoutGlobalScopes()->where('payroll_period_id', $period->id)->where('staff_member_id', $staff->id)->firstOrFail();
            $payroll->updateLine($this->owner, $line, ['base_salary' => $salary]);
        }

        return $period->fresh() ?? $period;
    }

    private function entry(User $user, ?Project $project, string $date, int $minutes): TimeEntry
    {
        return TimeEntry::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $user->tenant_id, 'user_id' => (string) $user->id, 'project_id' => $project?->id,
            'category' => $project === null ? TimeEntry::CATEGORY_MEETING : null, 'work_date' => $date, 'minutes' => $minutes,
            'source' => TimeEntry::SOURCE_MANUAL, 'note' => 'test', 'status' => TimeEntry::STATUS_APPROVED,
        ]);
    }

    /**
     * @return array<string, array{0: string, 1: int}> project id or "-" => [amount, minutes]
     */
    private function allocations(OverheadPeriod $period): array
    {
        $out = [];
        foreach (OverheadAllocation::query()->withoutGlobalScopes()->where('overhead_period_id', $period->id)->get() as $row) {
            $out[$row->project_id ?? '-'] = [(string) $row->amount, (int) $row->minutes];
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
