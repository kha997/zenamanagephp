<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryLedgerEntry as Entry;
use App\Models\Treasury\TreasuryPaymentRoute as Route;
use App\Models\Treasury\TreasuryPaymentRouteLeg as Leg;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryBalanceService;
use App\Services\Treasury\TreasuryDuplicateSuspected;
use App\Services\Treasury\TreasuryPostingService;
use App\Services\Treasury\TreasuryReconciliationService;
use App\Services\Treasury\TreasuryRouteService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP-095 S4b-2 — tracing paid contract payments (GAP-037 v17 §4.1 Case A,
 * §4.2a, §4.3; architecture B2-T; approved Gate 2 Option A).
 */
class TreasuryContractPaymentRouteTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;

    private Project $project;

    private User $owner;

    private TreasuryRouteService $routes;

    private TreasuryBalanceService $balances;

    private Contract $contract;

    private ContractPayment $payment;

    private TreasuryWallet $companyBank;

    private TreasuryWallet $siteCash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTreasuryPermissions();
        $this->tenant = Tenant::factory()->create();
        $this->project = $this->projectFor($this->tenant);
        $this->owner = $this->userWithRole($this->tenant, 'Admin');
        $this->routes = app(TreasuryRouteService::class);
        $this->balances = app(TreasuryBalanceService::class);
        $this->contract = Contract::factory()->create(['tenant_id' => $this->tenant->id, 'project_id' => $this->project->id, 'code' => 'HD-01']);
        $this->payment = $this->paidPayment('1000', '2026-10-05');
        $this->companyBank = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'TK công ty – trung chuyển', 'wallet_type' => 'intermediary_control']);
        $this->siteCash = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Quỹ công trường']);
    }

    public function test_a_paid_payment_is_traced_to_its_destination_without_any_document(): void
    {
        $route = $this->trace('1000');
        $this->assertSame((string) $this->payment->id, (string) $route->linked_contract_payment_id);
        $this->assertSame((string) $this->siteCash->id, (string) $route->expected_destination_wallet_id);
        $this->assertNull($route->linked_financial_document_id);

        $this->leg($route, null, $this->companyBank, '1000');
        $this->leg($route, $this->companyBank, $this->siteCash, '1000');

        $this->assertSame(Route::STATUS_COMPLETED, $route->fresh()?->status);
        $this->assertSame(0, Document::query()->count(), 'B2-T: no second funding economic event.');
        $this->assertSame('1000.00', $this->balances->walletBalance($this->siteCash));
        $summary = $this->balances->projectSummary($this->project);
        $this->assertSame('0.00', $summary['investor_funding']);
        $this->assertSame('1000.00', $summary['contract_payments_arrived']);
        $this->assertSame(ContractPayment::STATUS_PAID, $this->payment->fresh()?->status, 'Treasury never writes the payment.');
    }

    public function test_only_paid_payments_of_the_project_are_eligible(): void
    {
        $planned = ContractPayment::factory()->create([
            'tenant_id' => $this->tenant->id, 'contract_id' => $this->contract->id, 'amount' => '500', 'status' => 'planned', 'paid_at' => null,
        ]);
        $this->expectRule(fn () => $this->trace('100', $planned), 'contract_payment');

        $otherContract = Contract::factory()->create(['tenant_id' => $this->tenant->id, 'project_id' => $this->projectFor($this->tenant)->id]);
        $foreign = ContractPayment::factory()->create([
            'tenant_id' => $this->tenant->id, 'contract_id' => $otherContract->id, 'amount' => '500', 'status' => 'paid', 'paid_at' => '2026-10-01',
        ]);
        $this->expectRule(fn () => $this->trace('100', $foreign), 'contract_payment');
        $this->assertSame(0, Route::query()->count());
    }

    public function test_routes_of_one_payment_never_exceed_its_amount_and_cancelled_ones_free_their_share(): void
    {
        $first = $this->trace('600');
        $this->trace('400');
        $this->expectRule(fn () => $this->trace('0.01'), 'amount');

        $this->routes->cancelRoute($this->project, $this->owner, $first, 'Chia lại');
        $this->assertSame(Route::STATUS_CANCELLED, $first->fresh()?->status);
        $this->trace('600');
        $this->assertSame(3, Route::query()->count());
    }

    public function test_a_leg_needs_the_payment_to_still_be_paid(): void
    {
        $route = $this->trace('1000');
        $this->leg($route, null, $this->companyBank, '400');

        $this->payment->update(['status' => 'planned', 'paid_at' => null]);
        $this->expectRule(fn () => $this->leg($route, null, $this->companyBank, '600'), 'contract_payment');

        // A reversal is a correction: still allowed.
        $leg = Leg::query()->firstOrFail();
        $this->routes->reverseLeg($this->project, $this->owner, $leg, 'nhầm');
        $this->assertSame(Leg::STATUS_REVERSED, $leg->fresh()?->status);

        $flags = $this->routes->contractPayments($this->project)[0]['flags'];
        $this->assertSame(['no_longer_paid'], $flags);
    }

    public function test_leg_reversal_reopens_a_completed_route_and_cancel_refuses_a_completed_one(): void
    {
        $route = $this->trace('1000');
        $leg = $this->leg($route, null, $this->siteCash, '1000');
        $this->assertSame(Route::STATUS_COMPLETED, $route->fresh()?->status);
        $this->expectRule(fn () => $this->routes->cancelRoute($this->project, $this->owner, $route->fresh() ?? $route, 'bỏ'), 'route');

        $this->routes->reverseLeg($this->project, $this->owner, $leg, 'Ghi nhầm ví');
        $this->assertSame(Route::STATUS_PLANNED, $route->fresh()?->status);
        $this->assertSame('0.00', $this->balances->projectSummary($this->project)['contract_payments_arrived']);
    }

    public function test_reminder_list_reports_traced_arrived_and_flags(): void
    {
        $this->paidPayment('300', '2026-10-06', 'Đợt 2');
        $route = $this->trace('400');
        $this->leg($route, null, $this->siteCash, '400');
        $this->trace('100');

        $rows = collect($this->routes->contractPayments($this->project))->keyBy('name');
        $first = $rows[$this->payment->name];
        $this->assertSame('1000.00', $first['amount']);
        $this->assertSame('500.00', $first['traced_amount']);
        $this->assertSame('400.00', $first['arrived_amount']);
        $this->assertSame('500.00', $first['remaining_amount']);
        $this->assertSame(['not_fully_traced'], $first['flags']);
        $this->assertSame('HD-01', $first['contract_code']);
        $this->assertSame(['not_fully_traced'], $rows['Đợt 2']['flags']);

        $this->payment->update(['amount' => '450']);
        $flags = collect($this->routes->contractPayments($this->project))->keyBy('name')[$this->payment->name]['flags'];
        $this->assertSame(['over_traced'], $flags);
    }

    public function test_funding_that_looks_like_a_paid_payment_warns_and_can_be_confirmed(): void
    {
        $posting = app(TreasuryPostingService::class);
        $investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor']);
        $data = [
            'document_type' => Document::TYPE_FUNDING, 'source_party_id' => (string) $investor->id,
            'destination_wallet_id' => (string) $this->siteCash->id, 'amount' => '1000', 'transaction_date' => '2026-10-05',
        ];

        try {
            $posting->declareFunding($this->project, $this->owner, $data);
            $this->fail('Funding matching a paid contract payment must warn.');
        } catch (TreasuryDuplicateSuspected $e) {
            $this->assertSame((string) $this->payment->id, (string) $e->existing->getKey());
        }
        $this->expectException(TreasuryDuplicateSuspected::class);

        try {
            $this->routes->createRoute($this->project, $this->owner, $data);
        } finally {
            $this->assertSame(0, Document::query()->count());
            $posting->declareFunding($this->project, $this->owner, $data, confirmDuplicate: true);
            $this->assertSame(1, Document::query()->count());
        }
    }

    public function test_tracing_a_payment_already_declared_as_funding_warns_and_can_be_confirmed(): void
    {
        $investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor']);
        app(TreasuryPostingService::class)->declareFunding($this->project, $this->owner, [
            'document_type' => Document::TYPE_FUNDING, 'source_party_id' => (string) $investor->id,
            'destination_wallet_id' => (string) $this->siteCash->id, 'amount' => '1000', 'transaction_date' => '2026-10-05',
        ], confirmDuplicate: true);

        try {
            $this->trace('1000');
            $this->fail('Tracing a payment already declared as funding must warn.');
        } catch (TreasuryDuplicateSuspected $e) {
            $this->assertInstanceOf(Document::class, $e->existing);
        }
        $this->routes->createContractPaymentRoute($this->project, $this->owner, $this->payment, [
            'expected_destination_wallet_id' => (string) $this->siteCash->id, 'amount' => '1000',
        ], confirmDuplicate: true);
        $this->assertSame(1, Route::query()->count());
    }

    public function test_contract_payment_leg_entries_reconcile_per_wallet(): void
    {
        $route = $this->trace('1000');
        $this->leg($route, null, $this->siteCash, '1000');
        $entryId = (string) Entry::query()->value('id');
        $reconciliation = app(TreasuryReconciliationService::class);

        $this->assertSame($entryId, $reconciliation->unreconciledEntries($this->siteCash)[0]['ledger_entry_id']);
        $reconciliation->reconcile($this->project, $this->owner, $this->siteCash, [
            'reconciliation_type' => 'cash_count', 'reconciled_at' => '2026-10-06', 'ledger_entry_ids' => [$entryId],
        ]);
        $this->assertSame('1000.00', $reconciliation->walletSummary($this->siteCash)['reconciled']);
    }

    public function test_detail_and_list_show_the_traced_payment(): void
    {
        $route = $this->trace('1000');
        $detail = $this->routes->detail($route);
        $this->assertSame($this->payment->name, $detail['contract_payment_name']);
        $this->assertSame((string) $this->siteCash->id, $detail['destination_wallet_id']);
        $this->assertNull($detail['document_id']);
        $this->assertSame('2026-10-05', $detail['transaction_date']);
        $this->assertSame((string) $route->id, $this->routes->routes($this->project)[0]['id']);
    }

    public function test_accountant_traces_and_engineer_cannot_create(): void
    {
        $accountant = $this->userWithRole($this->tenant, 'Finance');
        $engineer = $this->userWithRole($this->tenant, 'site_engineer');
        $this->addMember($this->project, $engineer);
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($accountant)->allows('treasury.manage-routes', $this->project));
        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($engineer)->allows('treasury.manage-routes', $this->project));

        $route = $this->routes->createContractPaymentRoute($this->project, $accountant, $this->payment, [
            'expected_destination_wallet_id' => (string) $this->siteCash->id, 'amount' => '1000',
        ]);
        $this->expectRule(fn () => $this->routes->recordLeg($this->project, $engineer, $route, [
            'to_wallet_id' => (string) $this->companyBank->id, 'amount' => '1000', 'occurred_at' => '2026-10-06',
        ]), 'from_wallet_id');
    }

    private function paidPayment(string $amount, string $paidAt, ?string $name = null): ContractPayment
    {
        return ContractPayment::factory()->create([
            'tenant_id' => $this->tenant->id, 'contract_id' => $this->contract->id,
            'name' => $name ?? 'Đợt 1', 'amount' => $amount, 'status' => 'paid', 'paid_at' => $paidAt,
        ]);
    }

    private function trace(string $amount, ?ContractPayment $payment = null): Route
    {
        return $this->routes->createContractPaymentRoute($this->project, $this->owner, $payment ?? $this->payment, [
            'expected_destination_wallet_id' => (string) $this->siteCash->id,
            'amount' => $amount,
        ]);
    }

    private function leg(Route $route, ?TreasuryWallet $from, TreasuryWallet $to, string $amount): Leg
    {
        return $this->routes->recordLeg($this->project, $this->owner, $route, [
            'from_wallet_id' => $from === null ? null : (string) $from->id,
            'to_wallet_id' => (string) $to->id,
            'amount' => $amount,
            'occurred_at' => '2026-10-06',
        ]);
    }

    private function expectRule(callable $action, ?string $field = null): void
    {
        try {
            $action();
            $this->fail('Expected a Treasury rule violation.');
        } catch (TreasuryRuleViolation $e) {
            $this->assertNotSame('', $e->getMessage());
            if ($field !== null) {
                $this->assertSame($field, $e->field);
            }
        }
    }
}
