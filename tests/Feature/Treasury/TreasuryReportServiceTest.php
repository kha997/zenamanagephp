<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryPaymentRoute as Route;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryAdvanceService;
use App\Services\Treasury\TreasuryBalanceService;
use App\Services\Treasury\TreasuryDashboardService as Dashboard;
use App\Services\Treasury\TreasuryExpenseService;
use App\Services\Treasury\TreasuryPostingService;
use App\Services\Treasury\TreasuryReportService as Reports;
use App\Services\Treasury\TreasuryRouteService;
use App\Services\Treasury\TreasuryTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * GAP-097 S6 — dashboard, register, reports and timeline restate the ledger
 * (approved Gate 2 Option A).
 */
class TreasuryReportServiceTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;

    private Project $project;

    private User $owner;

    private User $accountant;

    private User $engineer;

    private TreasuryWallet $bank;

    private TreasuryWallet $site;

    private TreasuryWallet $transit;

    private TreasuryFinancialParty $investor;

    private TreasuryFinancialParty $holder;

    private TreasuryFinancialParty $labour;

    private Document $reversedFunding;

    private Document $advance;

    private Carbon $today;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTreasuryPermissions();
        $this->today = Carbon::parse('2026-10-27');
        Carbon::setTestNow($this->today->copy()->setTime(9, 0));
        $this->tenant = Tenant::factory()->create();
        $this->project = $this->projectFor($this->tenant);
        $this->owner = $this->userWithRole($this->tenant, 'Admin');
        $this->accountant = $this->userWithRole($this->tenant, 'Finance');
        $this->engineer = $this->userWithRole($this->tenant, 'site_engineer');
        $this->addMember($this->project, $this->engineer);
        $this->investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor', 'name' => 'Nhà đầu tư A']);
        $this->holder = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'employee', 'name' => 'Kỹ sư Z', 'linked_user_id' => $this->engineer->id]);
        $this->labour = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'labour', 'name' => 'Tổ đội D']);
        $this->bank = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Bank']);
        $this->site = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Quỹ Z', 'custodian_party_id' => $this->holder->id]);
        $this->transit = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Trung gian C']);

        $posting = app(TreasuryPostingService::class);
        $posting->declareFunding($this->project, $this->owner, $this->funding('100000000', '2026-09-01'));
        $this->reversedFunding = $posting->declareFunding($this->project, $this->owner, $this->funding('7000000', '2026-09-15', 'R-1'));
        $posting->reverse($this->project, $this->accountant, $this->reversedFunding, ['transaction_date' => '2026-09-16', 'description' => 'Khai nhầm']);
        // Funding unreconciled for exactly 30 and 31 days.
        $posting->declareFunding($this->project, $this->owner, $this->funding('300', '2026-09-27', 'U-30'));
        $posting->declareFunding($this->project, $this->owner, $this->funding('310', '2026-09-26', 'U-31'));
        $posting->transfer($this->project, $this->owner, [
            'source_wallet_id' => (string) $this->bank->id, 'destination_wallet_id' => (string) $this->site->id, 'amount' => '30000000', 'transaction_date' => '2026-10-02',
        ]);
        $posting->adjust($this->project, $this->accountant, [
            'wallet_id' => (string) $this->bank->id, 'direction' => 'decrease', 'amount' => '1000', 'transaction_date' => '2026-10-03', 'description' => 'Phí ngân hàng',
        ]);

        $contract = Contract::factory()->create(['tenant_id' => $this->tenant->id, 'project_id' => $this->project->id, 'created_by' => $this->owner->id]);
        $cost = ContractExpense::query()->create(['tenant_id' => $this->tenant->id, 'contract_id' => $contract->id, 'expense_date' => '2026-10-01', 'amount' => '20000000', 'category' => 'labor', 'description' => 'Nhân công']);
        $expenses = app(TreasuryExpenseService::class);
        $expense = $expenses->createDraft($this->project, $this->engineer, [
            'source_wallet_id' => (string) $this->site->id, 'destination_party_id' => (string) $this->labour->id, 'amount' => '15000000', 'transaction_date' => '2026-10-04',
            'allocations' => [['cost_source_type' => 'contract_expense', 'cost_source_id' => (string) $cost->id, 'amount' => '15000000']],
        ]);
        $expenses->submit($this->project, $this->engineer, $expense);
        $expenses->approve($this->project, $this->accountant, $expense->refresh());
        $selfExpense = $expenses->createDraft($this->project, $this->owner, [
            'source_wallet_id' => (string) $this->bank->id, 'destination_party_id' => (string) $this->labour->id, 'amount' => '1000000', 'transaction_date' => '2026-10-05',
            'allocations' => [['cost_source_type' => 'contract_expense', 'cost_source_id' => (string) $cost->id, 'amount' => '1000000']],
        ]);
        $expenses->submit($this->project, $this->owner, $selfExpense);
        $expenses->approve($this->project, $this->owner, $selfExpense->refresh());
        $pending = $expenses->createDraft($this->project, $this->engineer, [
            'source_wallet_id' => (string) $this->site->id, 'destination_party_id' => (string) $this->labour->id, 'amount' => '500', 'transaction_date' => '2026-10-06',
            'allocations' => [['cost_source_type' => 'contract_expense', 'cost_source_id' => (string) $cost->id, 'amount' => '500']],
        ]);
        $expenses->submit($this->project, $this->engineer, $pending);

        $advances = app(TreasuryAdvanceService::class);
        $draft = $advances->createDraft($this->project, $this->owner, [
            'source_wallet_id' => (string) $this->bank->id, 'destination_party_id' => (string) $this->holder->id, 'amount' => '5000000', 'transaction_date' => '2026-10-07',
        ]);
        $advances->submit($this->project, $this->owner, $draft);
        $this->advance = $advances->approve($this->project, $this->accountant, $draft->refresh());
        $advances->requestSettlement($this->project, $this->engineer, $this->advance, [
            'amount' => '2000000', 'allocations' => [['cost_source_type' => 'contract_expense', 'cost_source_id' => (string) $cost->id, 'amount' => '2000000']],
        ]);
        $advances->approveSettlementRequest($this->project, $this->accountant, $this->advance);
        $advances->recordReturn($this->project, $this->accountant, $this->advance, ['destination_wallet_id' => (string) $this->bank->id, 'amount' => '1000000', 'transaction_date' => '2026-10-09']);

        // A via-route funding of 8,000,000 through C: 5,000,000 arrived in C, 3,000,000 still to come.
        $routes = app(TreasuryRouteService::class);
        $route = $routes->createRoute($this->project, $this->owner, $this->funding('8000000', '2026-10-10', 'VR-1', $this->site), true);
        $routes->recordLeg($this->project, $this->owner, $route, ['from_wallet_id' => null, 'to_wallet_id' => (string) $this->transit->id, 'amount' => '5000000', 'occurred_at' => '2026-10-10']);

        // Another tenant's project must never appear.
        $otherTenant = Tenant::factory()->create();
        $otherProject = $this->projectFor($otherTenant);
        $otherOwner = $this->userWithRole($otherTenant, 'Admin');
        $otherInvestor = TreasuryFinancialParty::factory()->create(['tenant_id' => $otherTenant->id, 'party_type' => 'investor']);
        $otherWallet = TreasuryWallet::factory()->create(['project_id' => $otherProject->id]);
        $posting->declareFunding($otherProject, $otherOwner, [
            'document_type' => 'funding', 'source_party_id' => (string) $otherInvestor->id, 'destination_wallet_id' => (string) $otherWallet->id,
            'amount' => '999', 'transaction_date' => '2026-09-01',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_cards_restate_the_ledger(): void
    {
        $dashboard = app(Dashboard::class)->dashboard($this->project, $this->today);
        $summary = app(TreasuryBalanceService::class)->projectSummary($this->project);
        $cards = $dashboard['cards'];

        $this->assertSame($summary['held_total'], $cards['held_total']);
        $this->assertSame('100000610.00', $cards['investor_funding']);
        $this->assertSame('0.00', $cards['via_route_funding']);
        $this->assertSame('16000000.00', $cards['expenses']);
        $this->assertSame('1000000.00', $cards['self_approved_expenses']);
        $this->assertSame('100000610.00', $cards['unreconciled_funding']);
        $this->assertSame(1, $cards['pending_approvals_count']);
        $this->assertSame('500.00', $cards['pending_approvals_amount']);
        $this->assertSame('2000000.00', $cards['outstanding_advances']);

        $wallets = collect($dashboard['wallets'])->keyBy('name');
        $this->assertSame('Kỹ sư Z', $wallets['Quỹ Z']['custodian_name']);
        $this->assertSame('15000000.00', $wallets['Quỹ Z']['balance']);
        $this->assertSame('2000000.00', $wallets['Bank']['outstanding_advances']);
        $this->assertSame('5000000.00', $wallets['Trung gian C']['balance']);
        $this->assertNotNull($wallets['Bank']['last_activity_at']);
    }

    public function test_alerts_use_the_fixed_thresholds(): void
    {
        $alerts = collect(app(Dashboard::class)->dashboard($this->project, $this->today)['alerts'])->keyBy('code');

        // 2026-09-01 (56 days) and the 31-day one are stale; the 30-day one is not; the reversed one is gone.
        $this->assertSame(2, $alerts[Dashboard::ALERT_UNRECONCILED_FUNDING]['count']);
        $this->assertSame('100000310.00', $alerts[Dashboard::ALERT_UNRECONCILED_FUNDING]['amount']);
        $this->assertSame(1, $alerts[Dashboard::ALERT_PENDING_APPROVALS]['count']);
        $this->assertSame(1, $alerts[Dashboard::ALERT_OPEN_ROUTES]['count']);
        $this->assertFalse($alerts->has(Dashboard::ALERT_OVERDUE_ADVANCES));
        $this->assertFalse($alerts->has(Dashboard::ALERT_NEGATIVE_WALLETS));

        // Advance paid 2026-10-27 09:00 is 89 days old on 2027-01-24 and 90 on 2027-01-25.
        $this->assertFalse(collect(app(Dashboard::class)->dashboard($this->project, Carbon::parse('2027-01-24'))['alerts'])->contains('code', Dashboard::ALERT_OVERDUE_ADVANCES));
        $overdue = collect(app(Dashboard::class)->dashboard($this->project, Carbon::parse('2027-01-25'))['alerts'])->keyBy('code');
        $this->assertSame(1, $overdue[Dashboard::ALERT_OVERDUE_ADVANCES]['count']);
        $this->assertSame('2000000.00', $overdue[Dashboard::ALERT_OVERDUE_ADVANCES]['amount']);
    }

    public function test_cashflow_adds_up_to_the_money_held_and_ignores_internal_moves(): void
    {
        $report = app(Reports::class)->report($this->project, 'cashflow', $this->today);
        $net = 0;
        foreach ($report['rows'] as $row) {
            $net += TreasuryBalanceService::toCents((string) end($row));
        }

        $this->assertSame(TreasuryBalanceService::toCents(app(TreasuryBalanceService::class)->projectSummary($this->project)['held_total']), $net);
        $october = collect($report['rows'])->firstWhere(0, '2026-10');
        $this->assertNotNull($october);
        // funding (via the route leg from outside), expenses, advance out / back, adjustment.
        $this->assertSame(['2026-10', '5000000.00', '0.00', '0.00', '-16000000.00', '-5000000.00', '1000000.00', '-1000.00', '-15001000.00'], $october);
        $september = collect($report['rows'])->firstWhere(0, '2026-09');
        $this->assertSame('100000610.00', $september[1]);
    }

    public function test_reports_restate_costs_parties_self_approval_and_corrections(): void
    {
        $reports = app(Reports::class);
        $this->assertSame([['Nhân công', '18000000.00']], $reports->report($this->project, 'expense_categories')['rows']);

        $parties = $reports->report($this->project, 'expense_parties')['rows'];
        $this->assertContains(['Tổ đội D', 'Tổ đội / nhân công', 'Chi trực tiếp', '16000000.00'], $parties);
        $this->assertContains(['Kỹ sư Z', 'Nhân viên', 'Qua tạm ứng', '2000000.00'], $parties);

        $self = $reports->report($this->project, 'self_approved')['rows'];
        $this->assertCount(1, $self);
        $this->assertSame(['Chi phí', '1000000.00'], [$self[0][1], $self[0][3]]);

        $corrections = $reports->report($this->project, 'corrections')['rows'];
        $this->assertSame(['2026-09-16', 'Đảo tiền nhận', '7000000.00'], array_slice($corrections[0], 0, 3));
        $this->assertSame(['2026-10-03', 'Điều chỉnh giảm', '1000.00'], array_slice($corrections[1], 0, 3));
        $this->assertSame('Phí ngân hàng', $corrections[1][4]);

        $custodians = collect($reports->report($this->project, 'custodians')['rows'])->keyBy(0);
        $this->assertSame(['15000000.00', '2000000.00', '17000000.00'], array_slice($custodians['Kỹ sư Z'], 2, 3));

        $routes = $reports->report($this->project, 'routes')['rows'];
        $this->assertCount(1, $routes);
        $this->assertSame(['8000000.00', '0.00', '8000000.00', 'Trung gian C: 5000000.00'], array_slice($routes[0], 3, 4));

        $ageing = $reports->report($this->project, 'advance_ageing', $this->today)['rows'];
        $this->assertSame(['5000000.00', '2000000.00', '1000000.00', '2000000.00'], array_slice($ageing[0], 3, 4));

        $unreconciled = $reports->report($this->project, 'unreconciled', $this->today)['rows'];
        $this->assertNotEmpty($unreconciled);
        $this->assertTrue(collect($unreconciled)->contains(static fn (array $r): bool => $r[1] === '2026-09-26' && $r[4] === 31));

        $wallets = collect($reports->report($this->project, 'wallets', $this->today)['rows'])->keyBy(0);
        $this->assertSame('15000000.00', $wallets['Quỹ Z'][3]);
        $this->assertSame('Kỹ sư Z', $wallets['Quỹ Z'][2]);
    }

    public function test_register_filters_narrow_and_combine(): void
    {
        $reports = app(Reports::class);
        $count = fn (array $filters): int => $reports->registerQuery($this->project, $filters)->count();

        $all = $count([]);
        $this->assertGreaterThan(5, $all);
        $this->assertSame(0, Document::query()->where('project_id', '!=', $this->project->id)->whereIn('id', $reports->registerQuery($this->project, [])->pluck('id'))->count());
        $this->assertSame(1, $count(['type' => Document::TYPE_ADJUSTMENT]));
        $this->assertSame(1, $count(['status' => Document::STATUS_REVERSED]));
        $this->assertSame(1, $count(['self_approved' => '1']));
        $this->assertSame(2, $count(['type' => Document::TYPE_EXPENSE]));
        $this->assertSame(1, $count(['type' => Document::TYPE_EXPENSE, 'wallet_id' => (string) $this->site->id]));
        $this->assertSame(2, $count(['party_id' => (string) $this->labour->id]));
        $this->assertSame(2, $count(['date_from' => '2026-10-04', 'date_to' => '2026-10-05']));
        $this->assertSame(1, $count(['approved_by' => (string) $this->accountant->id, 'type' => Document::TYPE_EXPENSE]));
        $this->assertSame(0, $count(['reconciled' => 'yes']));
        // Every ledger document except the reversed one is still unreconciled.
        $this->assertSame($all - 1, $count(['reconciled' => 'no']));

        $csv = $reports->registerReport($this->project, ['type' => Document::TYPE_ADJUSTMENT]);
        $this->assertSame([['2026-10-03', 'Điều chỉnh', 'Bank', '', '1000.00', 'posted_unreconciled', (string) $this->accountant->name, (string) $this->accountant->name, '', 'Phí ngân hàng']], $csv['rows']);
        $this->assertSame("'=1+1", Reports::csvValue('=1+1'));
        $this->assertSame('ok', Reports::csvValue('ok'));
    }

    public function test_timeline_tells_the_month_in_order(): void
    {
        $events = app(TreasuryTimelineService::class)->month($this->project, '2026-10');
        $sentences = array_column($events, 'sentence');

        $this->assertSame('Ví Bank chuyển 30.000.000 ₫ sang ví Quỹ Z', $sentences[0]);
        $this->assertContains('Ví Quỹ Z trả 15.000.000 ₫ cho Tổ đội D (đã duyệt)', $sentences);
        $this->assertContains('Ví Bank trả 1.000.000 ₫ cho Tổ đội D (đã duyệt, tự duyệt)', $sentences);
        $this->assertContains('Ví Bank tạm ứng 5.000.000 ₫ cho Kỹ sư Z (đã duyệt)', $sentences);
        $this->assertContains('Kỹ sư Z nộp lại 1.000.000 ₫ tạm ứng vào ví Bank', $sentences);
        $this->assertContains('Quyết toán 2.000.000 ₫ tạm ứng của Kỹ sư Z bằng chi phí', $sentences);
        $this->assertContains('Chặng 1: Bên ngoài → ví Trung gian C 5.000.000 ₫', $sentences);
        $this->assertSame(array_column($events, 'at'), collect($events)->pluck('at')->sort()->values()->all());
        $advanceEvent = collect($events)->firstWhere('sentence', 'Ví Bank tạm ứng 5.000.000 ₫ cho Kỹ sư Z (đã duyệt)');
        $this->assertSame(['type' => 'advance', 'id' => (string) $this->advance->id], $advanceEvent['link']);
        $this->assertSame(['type' => 'route', 'id' => (string) Route::query()->value('id')], collect($events)->firstWhere('kind', 'route_leg')['link']);

        $september = array_column(app(TreasuryTimelineService::class)->month($this->project, '2026-09'), 'sentence');
        $this->assertContains('Nhà đầu tư A trả 7.000.000 ₫ vào ví Bank — đã đảo', $september);
        $this->assertContains('Đảo tiền nhận 7.000.000 ₫: Khai nhầm', $september);
        $this->assertStringNotContainsString('999 ₫', implode(' ', $september));
    }

    public function test_overview_has_one_row_per_project(): void
    {
        $rows = app(Dashboard::class)->overview([$this->project], $this->today);

        $this->assertCount(1, $rows);
        $this->assertSame(app(TreasuryBalanceService::class)->projectSummary($this->project)['held_total'], $rows[0]['held_total']);
        $this->assertSame(3, $rows[0]['alert_count']);
    }

    /**
     * @return array<string, string>
     */
    private function funding(string $amount, string $date, ?string $reference = null, ?TreasuryWallet $wallet = null): array
    {
        return [
            'document_type' => 'funding', 'source_party_id' => (string) $this->investor->id,
            'destination_wallet_id' => (string) ($wallet ?? $this->bank)->id, 'amount' => $amount, 'transaction_date' => $date,
            'reference' => $reference ?? 'F-' . $amount,
        ];
    }
}
