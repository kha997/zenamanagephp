<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\AuditLog;
use App\Models\Payroll\PayrollLine;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\StaffMember;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payroll\PayrollRuleViolation;
use App\Services\Payroll\PayrollService;
use App\Services\Payroll\StaffService;
use Database\Seeders\PayrollRolePermissionSeeder;
use Database\Seeders\ZenaPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * GAP-109 (Gate 2 C-1, B-1, H-1, L-1, P-1, Q-1) — monthly payroll: lines for staff working in the month, 1/12 bonus
 * accrual, entered items with computed net pay / company cost / hourly cost on standard hours, owner close and reopen
 * with a reason, mark paid after close, audit, pages, CSV, tenant isolation.
 */
class PayrollPeriodTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $accountant;

    private User $engineer;

    private StaffMember $full;

    private StaffMember $partTime;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 09:00:00');
        $this->seedPayrollPermissions();
        $this->tenant = Tenant::factory()->create();
        $this->owner = $this->payrollUser($this->tenant, 'Admin');
        $this->accountant = $this->payrollUser($this->tenant, 'Finance');
        $this->engineer = $this->payrollUser($this->tenant, 'site_engineer');
        $staff = app(StaffService::class);
        $this->full = $staff->create($this->owner, ['code' => 'NV01', 'name' => 'Kỹ sư A', 'staff_type' => 'employee', 'annual_bonus_estimate' => '12000000', 'start_date' => '2025-01-01']);
        $this->partTime = $staff->create($this->owner, ['code' => 'CTV01', 'name' => 'CTV B', 'staff_type' => 'collaborator', 'standard_hours_override' => '80', 'start_date' => '2026-10-15']);
        // Left before October, joins after October: no line.
        $staff->create($this->owner, ['code' => 'NV02', 'name' => 'Đã nghỉ', 'staff_type' => 'employee', 'start_date' => '2025-01-01', 'end_date' => '2026-09-30']);
        $staff->create($this->owner, ['code' => 'NV03', 'name' => 'Mới vào', 'staff_type' => 'employee', 'start_date' => '2026-11-01']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_create_period_lines_bonus_accrual_and_duplicate(): void
    {
        $payroll = app(PayrollService::class);
        $this->assertRule(fn () => $payroll->createPeriod($this->accountant, '2026-13', 26), 'Tháng');
        $this->assertRule(fn () => $payroll->createPeriod($this->accountant, '2026-10', 0), 'Số ngày công');
        $this->assertRule(fn () => $payroll->createPeriod($this->engineer, '2026-10', 26), 'quyền');

        $period = $payroll->createPeriod($this->accountant, '2026-10', 26);
        $this->assertSame(PayrollPeriod::STATUS_DRAFT, $period->status);
        $lines = PayrollLine::query()->where('payroll_period_id', $period->id)->get()->keyBy('staff_member_id');
        $this->assertEqualsCanonicalizing([(string) $this->full->id, (string) $this->partTime->id], $lines->keys()->all());
        $this->assertSame('1000000.00', $lines[(string) $this->full->id]->bonus_accrual);
        $this->assertSame('208.00', $lines[(string) $this->full->id]->standard_hours);
        $this->assertSame('80.00', $lines[(string) $this->partTime->id]->standard_hours);
        $this->assertSame('1000000.00', $lines[(string) $this->full->id]->company_cost);

        $this->assertRule(fn () => $payroll->createPeriod($this->accountant, '2026-10', 26), 'đã có');
        $this->assertSame(1, AuditLog::query()->where('action', 'payroll.period.created')->count());
    }

    public function test_line_math_and_working_days(): void
    {
        $payroll = app(PayrollService::class);
        $period = $payroll->createPeriod($this->accountant, '2026-10', 26);
        $line = $this->lineOf($period, $this->full);
        $payroll->updateLine($this->accountant, $line, [
            'base_salary' => '10000000', 'allowance' => '1000000', 'overtime' => '500000', 'bonus_paid' => '2000000',
            'employer_insurance' => '2150000', 'other_company_cost' => '300000', 'employee_insurance' => '1050000',
            'personal_income_tax' => '400000', 'advance_deduction' => '1000000', 'other_deduction' => '0', 'bonus_accrual' => '1000000',
        ]);
        $line = $line->fresh();
        // Net = 10 + 1 + 0.5 + 2 − 1.05 − 0.4 − 1 = 11.05 M; cost = 10 + 1 + 0.5 + 2.15 + 0.3 + 1 (accrual) = 14.95 M.
        $this->assertSame('11050000.00', $line->net_pay);
        $this->assertSame('14950000.00', $line->company_cost);
        $this->assertSame('71875.00', $line->hourly_cost);

        $part = $this->lineOf($period, $this->partTime);
        $payroll->updateLine($this->accountant, $part, ['base_salary' => '4000000']);
        $this->assertSame('50000.00', $part->fresh()->hourly_cost);

        $this->assertRule(fn () => $payroll->updateLine($this->accountant, $line, ['base_salary' => '-1']), 'không được âm');
        $this->assertRule(fn () => $payroll->updateLine($this->accountant, $line, ['base_salary' => 'abc']), 'không hợp lệ');

        // 25 working days: 200 standard hours for the full-time person, the part-timer keeps 80.
        $payroll->updatePeriod($this->accountant, $period, 25, '8');
        $this->assertSame('200.00', $line->fresh()->standard_hours);
        $this->assertSame('74750.00', $line->fresh()->hourly_cost);
        $this->assertSame('80.00', $part->fresh()->standard_hours);

        $totals = $payroll->totals($period->fresh());
        $this->assertSame('18950000.00', $totals['company_cost']);
        $this->assertSame('15050000.00', $totals['net_pay']);
        $this->assertGreaterThanOrEqual(2, AuditLog::query()->where('action', 'payroll.line.updated')->count());
    }

    public function test_close_reopen_and_mark_paid(): void
    {
        $payroll = app(PayrollService::class);
        $period = $payroll->createPeriod($this->accountant, '2026-10', 26);
        $line = $this->lineOf($period, $this->full);
        // 9.4 M + 1 M bonus accrual = 10.4 M over 208 h = 50,000 / h.
        $payroll->updateLine($this->accountant, $line, ['base_salary' => '9400000']);

        $this->assertRule(fn () => $payroll->markPaid($this->accountant, $line->fresh(), '2026-11-05', 'UNC-01'), 'chốt');
        $this->assertRule(fn () => $payroll->close($this->accountant, $period), 'quyền');
        $payroll->close($this->owner, $period);
        $period = $period->fresh();
        $this->assertTrue($period->isClosed());
        $this->assertSame((string) $this->owner->id, $period->closed_by);

        $this->assertRule(fn () => $payroll->updateLine($this->accountant, $line->fresh(), ['base_salary' => '1']), 'đã chốt');
        $this->assertRule(fn () => $payroll->updatePeriod($this->accountant, $period, 20, '8'), 'đã chốt');
        $this->assertSame('50000.00', $line->fresh()->hourly_cost);

        $payroll->markPaid($this->accountant, $line->fresh(), '2026-11-05', 'UNC-01');
        $this->assertSame('2026-11-05', $line->fresh()->paid_at?->toDateString());
        $this->assertSame('UNC-01', $line->fresh()->payment_reference);

        $this->assertRule(fn () => $payroll->reopen($this->owner, $period, ' '), 'lý do');
        $this->assertRule(fn () => $payroll->reopen($this->accountant, $period, 'Sửa sai'), 'quyền');
        $payroll->reopen($this->owner, $period, 'Sửa phụ cấp');
        $this->assertFalse($period->fresh()->isClosed());
        $payroll->updateLine($this->accountant, $line->fresh(), ['base_salary' => '9400000', 'allowance' => '1040000']);
        $this->assertSame('55000.00', $line->fresh()->hourly_cost);

        $reopened = AuditLog::query()->where('action', 'payroll.period.reopened')->sole();
        $this->assertSame('Sửa phụ cấp', $reopened->new_data['reason']);
        $this->assertSame(1, AuditLog::query()->where('action', 'payroll.period.closed')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'payroll.line.paid')->count());
    }

    public function test_pages_csv_permissions_and_tenant_isolation(): void
    {
        $this->actingAs($this->accountant)->get(route('operator.payroll.index'))->assertOk()->assertSee('payroll-period-create-form', false);
        $this->actingAs($this->accountant)->post(route('operator.payroll.periods.store'), ['period' => '2026-10', 'working_days' => '26'])
            ->assertRedirect();
        $period = PayrollPeriod::query()->sole();
        $page = route('operator.payroll.periods.show', $period->id);
        $line = $this->lineOf($period, $this->full);

        $this->actingAs($this->accountant)->post(route('operator.payroll.lines.update', $line->id), ['base_salary' => '9400000', 'allowance' => ''])
            ->assertRedirect($page);
        $this->actingAs($this->accountant)->get($page)->assertOk()
            ->assertSee('Kỹ sư A')->assertSee('9.400.000')->assertSee('50.000')
            ->assertSee('payroll-line-form', false)->assertDontSee('payroll-close-form', false)
            // Two-decimal amounts (e.g. a 1/12 bonus accrual of 833,333.33) must pass browser validation.
            ->assertSee('step="0.01" name="bonus_accrual"', false);
        $this->actingAs($this->owner)->get($page)->assertOk()->assertSee('payroll-close-form', false);

        $this->actingAs($this->accountant)->post(route('operator.payroll.periods.close', $period->id))->assertRedirect();
        $this->assertFalse($period->fresh()->isClosed());
        $this->actingAs($this->owner)->post(route('operator.payroll.periods.close', $period->id))->assertRedirect($page);
        $this->assertTrue($period->fresh()->isClosed());
        $this->actingAs($this->accountant)->post(route('operator.payroll.lines.paid', $line->id), ['paid_at' => '2026-11-05', 'payment_reference' => 'UNC-9'])
            ->assertRedirect($page);
        $this->assertSame('UNC-9', $line->fresh()->payment_reference);
        $this->actingAs($this->owner)->post(route('operator.payroll.periods.reopen', $period->id), ['reason' => ''])->assertSessionHasErrors('reason');

        $csv = $this->actingAs($this->accountant)->get(route('operator.payroll.periods.export', $period->id));
        $csv->assertOk();
        $body = $csv->streamedContent();
        $this->assertStringContainsString('Đơn giá giờ', $body);
        $this->assertStringContainsString('NV01', $body);

        $this->actingAs($this->engineer)->get($page)->assertRedirect();
        $this->actingAs($this->engineer)->get(route('operator.payroll.periods.export', $period->id))->assertRedirect();

        $other = Tenant::factory()->create();
        $stranger = $this->payrollUser($other, 'Admin');
        $this->actingAs($stranger)->get($page)->assertNotFound();
        $this->actingAs($stranger)->post(route('operator.payroll.lines.update', $line->id), ['base_salary' => '1'])->assertNotFound();
        $this->assertSame('9400000.00', PayrollLine::query()->withoutGlobalScopes()->whereKey($line->id)->value('base_salary'));
    }

    private function lineOf(PayrollPeriod $period, StaffMember $staff): PayrollLine
    {
        /** @var PayrollLine $line */
        $line = PayrollLine::query()->withoutGlobalScopes()->where('payroll_period_id', $period->id)->where('staff_member_id', $staff->id)->firstOrFail();

        return $line;
    }

    private function seedPayrollPermissions(): void
    {
        $this->seed(ZenaPermissionsSeeder::class);
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
    }

    /** Admin holds payroll.manage and payroll.close, Finance payroll.manage only, any other role neither. */
    private function payrollUser(Tenant $tenant, string $roleName): User
    {
        $role = Role::query()->where('name', $roleName)->first() ?? Role::factory()->create(['name' => $roleName]);
        $this->seed(PayrollRolePermissionSeeder::class);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
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
