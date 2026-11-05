<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\Payroll\PayrollLine;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\StaffMember;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\TimeTracking\TimeEntry;
use App\Models\User;
use App\Services\Payroll\LabourCostReportService;
use App\Services\Payroll\PayrollService;
use App\Services\Payroll\StaffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-113 (Gate 2 S-1, D-1, V-1) — labour cost report and project line.
 *
 * - Report: draft months shown as preview and kept out of the cumulative total; closed months from stored allocations.
 * - Output: CSV; fixed query count.
 * - V-1: the report needs payroll.manage; the OPPM line shows the closed total to payroll.manage and the project's PM
 *   only, without per-person detail.
 * - Tenant isolation.
 */
class LabourCostReportTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;

    private User $owner;

    private User $accountant;

    private User $pm;

    private User $viewer;

    private User $worker;

    private User $temp;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 09:00:00');
        $this->tenant = Tenant::factory()->create();
        $this->owner = $this->createTenantUser($this->tenant, ['name' => 'Chủ'], ['lr_owner'], ['payroll.manage', 'payroll.close', 'project.view', 'project.update', 'task.view']);
        $this->accountant = $this->createTenantUser($this->tenant, ['name' => 'Kế toán'], ['lr_accountant'], ['payroll.manage', 'project.view', 'task.view']);
        $this->pm = $this->createTenantUser($this->tenant, ['name' => 'PM Dự án'], ['lr_pm'], ['project.view', 'task.view']);
        $this->viewer = $this->createTenantUser($this->tenant, ['name' => 'Người xem'], ['lr_viewer'], ['project.view', 'task.view']);
        $this->worker = $this->createTenantUser($this->tenant, ['name' => 'Kỹ sư A'], ['lr_worker'], ['task.view']);
        $this->temp = $this->createTenantUser($this->tenant, ['name' => 'Thời vụ D'], ['lr_temp'], ['task.view']);
        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id, 'name' => 'Nhà phố Q7', 'code' => 'P-LC', 'status' => 'active',
            'budget_total' => 0, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'pm_id' => (string) $this->pm->id,
        ]);
        app(StaffService::class)->create($this->owner, ['code' => 'NV01', 'name' => 'Kỹ sư A', 'staff_type' => 'employee', 'user_id' => (string) $this->worker->id, 'start_date' => '2025-01-01']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_preview_then_closed_month_and_cumulative(): void
    {
        $period = $this->october('10400000');
        $this->entry($this->worker, $this->project, '2026-10-05', 100 * 60);
        $this->entry($this->temp, $this->project, '2026-10-06', 10 * 60);
        $reports = app(LabourCostReportService::class);

        $page = route('operator.payroll.labour-cost', ['period' => $period->id]);
        $this->actingAs($this->accountant)->get($page)->assertOk()
            ->assertSee('Tạm tính')->assertSee('Nhà phố Q7')->assertSee('Kỹ sư A')->assertSee('5.000.000')
            ->assertSee('Giờ chưa có đơn giá')->assertSee('Thời vụ D');
        $this->assertSame([], $reports->cumulative($this->tenant->id));
        $this->assertSame('0.00', $reports->projectTotal($this->project)['amount']);

        app(PayrollService::class)->close($this->owner, $period);

        $this->actingAs($this->accountant)->get($page)->assertOk()->assertSee('Đã chốt')->assertDontSee('Tạm tính')->assertSee('5.000.000');
        $total = $reports->projectTotal($this->project);
        $this->assertSame('5000000.00', $total['amount']);
        $this->assertSame(6000, $total['minutes']);
        $this->assertSame(1, $total['months']);
        $cumulative = $reports->cumulative($this->tenant->id);
        $this->assertCount(1, $cumulative);
        $this->assertSame('Nhà phố Q7', $cumulative[0]['project']);
        $this->assertSame('5000000.00', $cumulative[0]['amount']);

        // The payroll pages link to the report.
        $this->actingAs($this->accountant)->get(route('operator.payroll.index'))->assertSee($page, false);
        $this->actingAs($this->accountant)->get(route('operator.payroll.periods.show', $period->id))->assertSee($page, false);
    }

    public function test_payroll_page_lists_pending_hours_and_hides_close(): void
    {
        $period = $this->october('10400000');
        $this->entry($this->worker, $this->project, '2026-10-05', 120, TimeEntry::STATUS_SUBMITTED);

        $this->actingAs($this->owner)->get(route('operator.payroll.periods.show', $period->id))->assertOk()
            ->assertSee('payroll-pending-hours', false)->assertSee('Kỹ sư A')->assertDontSee('payroll-close-form', false);
    }

    public function test_v1_access_and_oppm_line(): void
    {
        $period = $this->october('10400000');
        $this->entry($this->worker, $this->project, '2026-10-05', 100 * 60);
        app(PayrollService::class)->close($this->owner, $period);

        $page = route('operator.payroll.labour-cost');
        $this->actingAs($this->pm)->get($page)->assertRedirect();
        $this->actingAs($this->worker)->get($page)->assertRedirect();
        $this->actingAs($this->accountant)->get($page)->assertOk()->assertSee('Đã chốt');

        $oppm = route('app.projects.oppm', $this->project->id);
        $this->actingAs($this->pm)->get($oppm)->assertOk()
            ->assertSee('Chi phí nhân công (đã chốt)')->assertSee('5.000.000')->assertDontSee('NV01');
        $this->actingAs($this->accountant)->get($oppm)->assertOk()->assertSee('Chi phí nhân công (đã chốt)');
        $this->actingAs($this->viewer)->get($oppm)->assertOk()->assertDontSee('Chi phí nhân công');
    }

    public function test_csv_and_tenant_isolation(): void
    {
        $period = $this->october('10400000');
        $this->entry($this->worker, $this->project, '2026-10-05', 100 * 60);
        app(PayrollService::class)->close($this->owner, $period);

        $csv = $this->actingAs($this->accountant)->get(route('operator.payroll.labour-cost.export', $period->id));
        $csv->assertOk();
        $body = $csv->streamedContent();
        $this->assertStringContainsString('Công trình', $body);
        $this->assertStringContainsString('Nhà phố Q7', $body);
        $this->assertStringContainsString('NV01', $body);
        $this->assertStringContainsString('5000000.00', $body);
        $this->assertStringContainsString('Đã chốt', $body);

        $other = Tenant::factory()->create();
        $stranger = $this->createTenantUser($other, [], ['lr_stranger'], ['payroll.manage', 'project.view']);
        $this->actingAs($stranger)->get(route('operator.payroll.labour-cost', ['period' => $period->id]))->assertNotFound();
        $this->actingAs($stranger)->get(route('operator.payroll.labour-cost.export', $period->id))->assertNotFound();
        $this->assertSame([], app(LabourCostReportService::class)->cumulative($other->id));
    }

    public function test_query_count_does_not_grow_with_rows(): void
    {
        $period = $this->october('10400000');
        $this->entry($this->worker, $this->project, '2026-10-05', 600);
        $reports = app(LabourCostReportService::class);
        $small = $this->countQueries(fn () => $reports->month($period));

        for ($i = 2; $i <= 6; $i++) {
            $user = $this->createTenantUser($this->tenant, ['name' => 'Người ' . $i], ['lr_extra_' . $i], ['task.view']);
            app(StaffService::class)->create($this->owner, ['code' => 'NX0' . $i, 'name' => 'Người ' . $i, 'staff_type' => 'employee', 'user_id' => (string) $user->id, 'start_date' => '2025-01-01']);
            $project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);
            $this->entry($user, $project, '2026-10-05', 600);
        }
        PayrollPeriod::query()->withoutGlobalScopes()->whereKey($period->id)->delete();
        $period = $this->october('10400000');
        $large = $this->countQueries(fn () => $reports->month($period));
        $this->assertSame($small, $large);

        app(PayrollService::class)->close($this->owner, $period);
        $period = $period->fresh() ?? $period;
        $closed = $this->countQueries(fn () => $reports->month($period));
        $this->assertLessThanOrEqual($small + 1, $closed);
    }

    private function october(string $salary): PayrollPeriod
    {
        $payroll = app(PayrollService::class);
        $period = $payroll->createPeriod($this->owner, '2026-10', 26);
        /** @var StaffMember $staff */
        $staff = StaffMember::query()->withoutGlobalScopes()->where('tenant_id', (string) $this->tenant->id)->where('code', 'NV01')->firstOrFail();
        /** @var PayrollLine $line */
        $line = PayrollLine::query()->withoutGlobalScopes()->where('payroll_period_id', $period->id)->where('staff_member_id', $staff->id)->firstOrFail();
        $payroll->updateLine($this->owner, $line, ['base_salary' => $salary]);

        return $period->fresh() ?? $period;
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
