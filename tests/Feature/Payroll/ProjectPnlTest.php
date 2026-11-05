<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\Payroll\OverheadPeriod;
use App\Models\Payroll\PayrollLine;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\StaffMember;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\TimeTracking\TimeEntry;
use App\Models\User;
use App\Services\Payroll\OverheadService;
use App\Services\Payroll\PayrollService;
use App\Services\Payroll\ProjectPnlService;
use App\Services\Payroll\StaffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-114 (Gate 2 R-1, D-1, V-1, C-1) — project P&L.
 *
 * - R-1: revenue = paid contract payments by paid date.
 * - D-1: direct cost = posted Treasury expenses by document date (reversed ones excluded); contract expenses are
 *   reference only.
 * - Cost also includes labour (GAP-113) and overhead (A-1).
 * - Profit is shown per month and cumulative over closed overhead months, with projected P&L on contract value.
 * - Preview until the overhead month is closed.
 * - V-1: page for payroll.manage; OPPM closed P&L line for payroll.manage and the project's PM.
 * - CSV; fixed query count.
 */
class ProjectPnlTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;

    private User $owner;

    private User $accountant;

    private User $pm;

    private User $viewer;

    private User $worker;

    private User $office;

    private Project $p1;

    private Project $p2;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 09:00:00');
        $this->tenant = Tenant::factory()->create();
        $this->owner = $this->createTenantUser($this->tenant, ['name' => 'Chủ'], ['pnl_owner'], ['payroll.manage', 'payroll.close', 'project.view', 'project.update', 'task.view']);
        $this->accountant = $this->createTenantUser($this->tenant, ['name' => 'Kế toán'], ['pnl_accountant'], ['payroll.manage', 'project.view', 'task.view']);
        $this->pm = $this->createTenantUser($this->tenant, ['name' => 'PM'], ['pnl_pm'], ['project.view', 'task.view']);
        $this->viewer = $this->createTenantUser($this->tenant, ['name' => 'Người xem'], ['pnl_viewer'], ['project.view', 'task.view']);
        $this->worker = $this->createTenantUser($this->tenant, ['name' => 'Kỹ sư A'], ['pnl_worker'], ['task.view']);
        $this->office = $this->createTenantUser($this->tenant, ['name' => 'Văn phòng C'], ['pnl_office'], ['task.view']);
        $this->p1 = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id, 'name' => 'Nhà phố Q7', 'code' => 'P-PNL', 'status' => 'active',
            'budget_total' => 0, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'pm_id' => (string) $this->pm->id,
        ]);
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

    public function test_month_and_cumulative_pnl_preview_then_closed(): void
    {
        $this->scenario();
        $pnl = app(ProjectPnlService::class);

        $preview = $pnl->month((string) $this->tenant->id, '2026-10');
        $this->assertFalse($preview['closed']);
        $this->assertSame('21333333.00', $this->row($preview, $this->p1)['overhead']);
        $this->assertSame([], $pnl->cumulative((string) $this->tenant->id)['rows']);
        $this->assertSame(0, $pnl->projectSummary($this->p1)['months']);

        $this->closeOverhead();
        $month = $pnl->month((string) $this->tenant->id, '2026-10');
        $this->assertTrue($month['closed']);
        $p1 = $this->row($month, $this->p1);
        $this->assertSame('40000000.00', $p1['revenue']);
        $this->assertSame('5000000.00', $p1['direct_cost']);
        $this->assertSame('2500000.00', $p1['contract_expenses']);
        $this->assertSame('5000000.00', $p1['labour']);
        $this->assertSame('21333333.00', $p1['overhead']);
        $this->assertSame('31333333.00', $p1['total_cost']);
        $this->assertSame('8666667.00', $p1['profit']);
        $p2 = $this->row($month, $this->p2);
        $this->assertSame('0.00', $p2['revenue']);
        $this->assertSame('-13166667.00', $p2['profit']);
        $this->assertNull($p2['margin']);
        $this->assertSame('40000000.00', $month['totals']['revenue']);
        $this->assertSame('44500000.00', $month['totals']['total_cost']);
        $this->assertSame('0.00', $month['totals']['unallocated_overhead']);
        $this->assertSame('-4500000.00', $month['totals']['profit']);

        $cumulative = $pnl->cumulative((string) $this->tenant->id);
        $this->assertSame(['2026-10'], $cumulative['months']);
        $c1 = collect($cumulative['rows'])->firstWhere('project_id', (string) $this->p1->id);
        $this->assertSame('100000000.00', $c1['contract_value']);
        $this->assertSame('68666667.00', $c1['projected_profit']);
        $this->assertSame('8666667.00', $c1['profit']);
        $summary = $pnl->projectSummary($this->p1);
        $this->assertSame(1, $summary['months']);
        $this->assertSame('8666667.00', $summary['profit']);
    }

    public function test_pages_access_csv_and_oppm_line(): void
    {
        $this->scenario();
        $page = route('operator.payroll.pnl', ['month' => '2026-10']);
        $this->actingAs($this->accountant)->get($page)->assertOk()->assertSee('Tạm tính')->assertSee('Nhà phố Q7');
        $this->closeOverhead();
        $this->actingAs($this->accountant)->get($page)->assertOk()->assertSee('Đã chốt')->assertDontSee('Tạm tính')
            ->assertSee('8.666.667')->assertSee('68.666.667');
        $this->actingAs($this->pm)->get($page)->assertRedirect();
        $this->actingAs($this->accountant)->get(route('operator.payroll.index'))->assertSee(route('operator.payroll.pnl'), false);

        $csv = $this->actingAs($this->accountant)->get(route('operator.payroll.pnl.export', ['month' => '2026-10']));
        $csv->assertOk();
        $body = $csv->streamedContent();
        $this->assertStringContainsString('Lời / lỗ', $body);
        $this->assertStringContainsString('Nhà phố Q7', $body);
        $this->assertStringContainsString('8666667.00', $body);

        $oppm = route('app.projects.oppm', $this->p1->id);
        $this->actingAs($this->pm)->get($oppm)->assertOk()->assertSee('Lời lỗ (đã chốt)')->assertSee('8.666.667');
        $this->actingAs($this->accountant)->get($oppm)->assertOk()->assertSee('Lời lỗ (đã chốt)');
        $this->actingAs($this->viewer)->get($oppm)->assertOk()->assertDontSee('Lời lỗ (đã chốt)');

        $other = Tenant::factory()->create();
        $stranger = $this->createTenantUser($other, [], ['pnl_stranger'], ['payroll.manage']);
        $this->actingAs($stranger)->get($page)->assertOk()->assertDontSee('Nhà phố Q7');
    }

    /** Codex P2: a contract-only project appears in the cumulative P&L; company totals show without project rows. */
    public function test_contract_only_project_and_company_totals_without_project_rows(): void
    {
        $this->scenario();
        $p3 = Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'name' => 'Kho xưởng Bình Dương']);
        $this->contract($p3, '30000000');
        $this->closeOverhead();
        $pnl = app(ProjectPnlService::class);
        $row = collect($pnl->cumulative((string) $this->tenant->id)['rows'])->firstWhere('project_id', (string) $p3->id);
        $this->assertIsArray($row);
        $this->assertSame('30000000.00', $row['contract_value']);
        $this->assertSame('30000000.00', $row['projected_profit']);
        $this->assertSame('30000000.00', $pnl->projectSummary($p3)['contract_value']);

        // A month with only unallocated overhead: no project rows, but the company loss is shown.
        app(OverheadService::class)->addLine($this->accountant, '2026-09', ['category' => 'rent', 'description' => 'Thuê T9', 'amount' => '7000000']);
        $month = $pnl->month((string) $this->tenant->id, '2026-09');
        $this->assertSame([], $month['rows']);
        $this->assertSame('-7000000.00', $month['totals']['profit']);
        $this->actingAs($this->accountant)->get(route('operator.payroll.pnl', ['month' => '2026-09']))->assertOk()
            ->assertSee('pnl-total-row', false)->assertSee('-7.000.000');
    }

    public function test_query_count_does_not_grow_with_projects(): void
    {
        $this->scenario();
        $this->closeOverhead();
        $pnl = app(ProjectPnlService::class);
        $tenantId = (string) $this->tenant->id;
        $small = $this->countQueries(fn () => $pnl->month($tenantId, '2026-10'));
        $smallCumulative = $this->countQueries(fn () => $pnl->cumulative($tenantId));
        for ($i = 0; $i < 4; $i++) {
            $project = Project::factory()->create(['tenant_id' => $tenantId]);
            $contract = $this->contract($project, '10000000');
            $this->payment($contract, '1000000', 'paid', '2026-10-0' . ($i + 1));
            $this->treasuryExpense($project, '100000', 'posted_unreconciled', '2026-10-0' . ($i + 1));
        }
        $this->assertSame($small, $this->countQueries(fn () => $pnl->month($tenantId, '2026-10')));
        $this->assertSame($smallCumulative, $this->countQueries(fn () => $pnl->cumulative($tenantId)));
    }

    private function scenario(): void
    {
        $payroll = app(PayrollService::class);
        $period = $payroll->createPeriod($this->owner, '2026-10', 26);
        foreach (['NV01' => '10400000', 'VP01' => '8000000'] as $code => $salary) {
            /** @var StaffMember $staff */
            $staff = StaffMember::query()->withoutGlobalScopes()->where('tenant_id', (string) $this->tenant->id)->where('code', $code)->firstOrFail();
            /** @var PayrollLine $line */
            $line = PayrollLine::query()->withoutGlobalScopes()->where('payroll_period_id', $period->id)->where('staff_member_id', $staff->id)->firstOrFail();
            $payroll->updateLine($this->owner, $line, ['base_salary' => $salary]);
        }
        $this->entry($this->p1, '2026-10-05', 100 * 60);
        $this->entry($this->p2, '2026-10-06', 50 * 60);
        $this->entry(null, '2026-10-07', 20 * 60);
        $payroll->close($this->owner, PayrollPeriod::query()->withoutGlobalScopes()->findOrFail($period->id));

        $overhead = app(OverheadService::class);
        $overhead->addLine($this->accountant, '2026-10', ['category' => 'rent', 'description' => 'Thuê văn phòng', 'amount' => '20000000']);
        $overhead->addLine($this->accountant, '2026-10', ['category' => 'utilities', 'description' => 'Điện nước', 'amount' => '1100000']);

        $contract = $this->contract($this->p1, '100000000');
        $this->payment($contract, '40000000', 'paid', '2026-10-15');
        $this->payment($contract, '30000000', 'planned', null);
        $this->payment($contract, '10000000', 'paid', '2026-11-02');
        $cancelled = $this->contract($this->p2, '50000000', 'cancelled');
        $this->expense($contract, '2500000', '2026-10-09');
        $this->treasuryExpense($this->p1, '3000000', 'posted_reconciled', '2026-10-10');
        $this->treasuryExpense($this->p1, '2000000', 'posted_unreconciled', null, '2026-10-12 08:00:00');
        $this->treasuryExpense($this->p1, '1000000', 'reversed', '2026-10-11');
        $this->treasuryExpense($this->p1, '4000000', 'posted_reconciled', '2026-11-01');
        unset($cancelled);
    }

    private function closeOverhead(): void
    {
        app(OverheadService::class)->close($this->owner, OverheadPeriod::query()->withoutGlobalScopes()->where('tenant_id', (string) $this->tenant->id)->sole());
    }

    /**
     * @param array{rows: list<array<string, mixed>>} $month
     * @return array<string, mixed>
     */
    private function row(array $month, Project $project): array
    {
        $row = collect($month['rows'])->firstWhere('project_id', (string) $project->id);
        $this->assertIsArray($row);

        return $row;
    }

    private function entry(?Project $project, string $date, int $minutes): void
    {
        TimeEntry::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $this->tenant->id, 'user_id' => (string) $this->worker->id, 'project_id' => $project?->id,
            'category' => $project === null ? TimeEntry::CATEGORY_MEETING : null, 'work_date' => $date, 'minutes' => $minutes,
            'source' => TimeEntry::SOURCE_MANUAL, 'note' => 'test', 'status' => TimeEntry::STATUS_APPROVED,
        ]);
    }

    private function contract(Project $project, string $value, string $status = 'active'): string
    {
        $id = (string) Str::ulid();
        DB::table('contracts')->insert([
            'id' => $id, 'tenant_id' => (string) $project->tenant_id, 'project_id' => (string) $project->id, 'code' => 'HD-' . substr($id, -6),
            'title' => 'Hợp đồng', 'status' => $status, 'currency' => 'VND', 'total_value' => $value, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function payment(string $contractId, string $amount, string $status, ?string $paidAt): void
    {
        DB::table('contract_payments')->insert([
            'id' => (string) Str::ulid(), 'tenant_id' => (string) $this->tenant->id, 'contract_id' => $contractId, 'name' => 'Đợt',
            'amount' => $amount, 'status' => $status, 'paid_at' => $paidAt, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function expense(string $contractId, string $amount, string $date): void
    {
        DB::table('contract_expenses')->insert([
            'id' => (string) Str::ulid(), 'tenant_id' => (string) $this->tenant->id, 'contract_id' => $contractId, 'expense_date' => $date,
            'amount' => $amount, 'category' => 'material', 'description' => 'Vật tư', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function treasuryExpense(Project $project, string $amount, string $status, ?string $date, ?string $postedAt = null): void
    {
        DB::table('treasury_financial_documents')->insert([
            'id' => (string) Str::ulid(), 'tenant_id' => (string) $project->tenant_id, 'project_id' => (string) $project->id,
            'document_type' => 'expense', 'status' => $status, 'amount' => $amount, 'transaction_date' => $date,
            'created_by' => (string) $this->owner->id, 'posted_at' => $postedAt ?? ($date === null ? null : $date . ' 09:00:00'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function countQueries(callable $action): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $action();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
