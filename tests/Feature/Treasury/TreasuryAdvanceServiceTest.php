<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryAdvance as Advance;
use App\Models\Treasury\TreasuryAdvanceSettlement as Settlement;
use App\Models\Treasury\TreasuryCostSettlementAllocation as Allocation;
use App\Models\Treasury\TreasuryExpenseApproval as Approval;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryLedgerEntry as Entry;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryAdvanceService as Advances;
use App\Services\Treasury\TreasuryBalanceService;
use App\Services\Treasury\TreasuryExpenseService as Expenses;
use App\Services\Treasury\TreasuryPostingService;
use App\Services\Treasury\TreasuryReconciliationService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * GAP-096 S5 — advances and settlements (GAP-037 v17 + approved Gate 2 Option A).
 */
class TreasuryAdvanceServiceTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;

    private Project $project;

    private User $owner;

    private User $accountant;

    private User $engineer;

    private Advances $advances;

    private Expenses $expenses;

    private TreasuryPostingService $posting;

    private TreasuryBalanceService $balances;

    private TreasuryWallet $bank;

    private TreasuryWallet $engineerCash;

    private TreasuryFinancialParty $holder;

    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTreasuryPermissions();
        $this->tenant = Tenant::factory()->create();
        $this->project = $this->projectFor($this->tenant);
        $this->owner = $this->userWithRole($this->tenant, 'Admin');
        $this->accountant = $this->userWithRole($this->tenant, 'Finance');
        $this->engineer = $this->userWithRole($this->tenant, 'site_engineer');
        $this->addMember($this->project, $this->engineer);
        $this->advances = app(Advances::class);
        $this->expenses = app(Expenses::class);
        $this->posting = app(TreasuryPostingService::class);
        $this->balances = app(TreasuryBalanceService::class);

        $investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor']);
        $this->holder = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'employee', 'linked_user_id' => $this->engineer->id, 'name' => 'Kỹ sư Z']);
        $this->bank = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Bank']);
        $this->engineerCash = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Z cash', 'custodian_party_id' => $this->holder->id]);
        $this->posting->declareFunding($this->project, $this->owner, [
            'document_type' => 'funding', 'source_party_id' => (string) $investor->id,
            'destination_wallet_id' => (string) $this->bank->id, 'amount' => '100000000', 'transaction_date' => '2026-10-01',
        ]);
        $this->contract = Contract::factory()->create(['tenant_id' => $this->tenant->id, 'project_id' => $this->project->id, 'created_by' => $this->owner->id]);
    }

    public function test_requested_advance_pays_out_on_approval_and_opens_the_advance(): void
    {
        $this->posting->transfer($this->project, $this->owner, [
            'source_wallet_id' => (string) $this->bank->id, 'destination_wallet_id' => (string) $this->engineerCash->id,
            'amount' => '20000000', 'transaction_date' => '2026-10-02',
        ]);
        $draft = $this->advances->createDraft($this->project, $this->engineer, $this->draftData('10000000'));
        $this->advances->submit($this->project, $this->engineer, $draft);
        $this->assertSame('20000000.00', $this->balances->walletBalance($this->engineerCash));
        $this->assertSame(0, Advance::query()->count());

        $posted = $this->advances->approve($this->project, $this->accountant, $draft->refresh());

        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, $posted->status);
        $this->assertSame(Document::POSTING_PATH_DIRECT, $posted->posting_path);
        $this->assertSame('10000000.00', $this->balances->walletBalance($this->engineerCash));
        $advance = Advance::query()->firstOrFail();
        $this->assertSame((string) $this->holder->id, (string) $advance->financial_party_id);
        $this->assertSame(1000000000, TreasuryBalanceService::toCents((string) $advance->amount));
        $this->assertSame(1000000000, $this->advances->outstandingCents($advance));
        $this->assertSame(['submitted', Advances::EVENT_APPROVED, 'posted'], Approval::query()->where('financial_document_id', $draft->id)->orderBy('created_at')->orderBy('id')->pluck('event')->all());
        $this->assertSame('advance', Entry::query()->where('source_financial_document_id', $draft->id)->value('entry_type'));
    }

    public function test_reject_copy_and_self_approval_follow_s3(): void
    {
        $draft = $this->advances->createDraft($this->project, $this->engineer, $this->draftData('1000000'));
        $this->expectRule(fn () => $this->advances->submit($this->project, $this->accountant, $draft));
        $this->advances->submit($this->project, $this->engineer, $draft);
        $this->expectRule(fn () => $this->advances->reject($this->project, $this->accountant, $draft->refresh(), ' '), 'note');
        $rejected = $this->advances->reject($this->project, $this->accountant, $draft->refresh(), 'Sai số tiền');
        $this->assertSame(Document::STATUS_REJECTED, $rejected->status);
        $this->expectRule(fn () => $this->advances->approve($this->project, $this->accountant, $rejected));

        $copy = $this->advances->copyToDraft($this->project, $this->engineer, $rejected);
        $this->assertSame(Document::STATUS_DRAFT, $copy->status);
        $this->assertSame('1000000.00', (string) $copy->amount);

        // The accountant may not approve their own request; the owner may, and it is recorded.
        $own = $this->advances->createDraft($this->project, $this->accountant, $this->draftData('500000', $this->bank));
        $this->advances->submit($this->project, $this->accountant, $own);
        $this->expectRule(fn () => $this->advances->approve($this->project, $this->accountant, $own->refresh()));

        $ownerDraft = $this->advances->createDraft($this->project, $this->owner, $this->draftData('300000', $this->bank));
        $this->advances->submit($this->project, $this->owner, $ownerDraft);
        $this->advances->approve($this->project, $this->owner, $ownerDraft->refresh());
        $this->assertSame('self_approval', Approval::query()->where('financial_document_id', $ownerDraft->id)->where('event', Advances::EVENT_APPROVED)->firstOrFail()->context['approval_mode']);
        // An advance is not an expense: S3's self-approved expense total is unchanged.
        $this->assertSame('0.00', $this->balances->projectSummary($this->project)['self_approved_expenses']);
    }

    public function test_wallet_holder_rule_and_balance_guard(): void
    {
        $this->expectRule(fn () => $this->advances->createDraft($this->project, $this->engineer, $this->draftData('1', $this->bank)), 'source_wallet_id');

        $draft = $this->advances->createDraft($this->project, $this->engineer, $this->draftData('1'));
        $this->advances->submit($this->project, $this->engineer, $draft);
        $this->expectRule(fn () => $this->advances->approve($this->project, $this->accountant, $draft->refresh()), 'source_wallet_id');
        $this->assertSame(Document::STATUS_SUBMITTED, $draft->fresh()?->status);
    }

    public function test_settlement_request_is_approved_against_costs_and_reduces_outstanding(): void
    {
        $doc = $this->postedAdvance('10000000');
        $cost = $this->contractExpense('6000000');
        $line = $this->contractExpense('9000000');

        $this->expectRule(fn () => $this->advances->requestSettlement($this->project, $this->engineer, $doc, $this->settlement('5000000', [[$cost, '4000000']])), 'allocations');
        $this->expectRule(fn () => $this->advances->requestSettlement($this->project, $this->engineer, $doc, $this->settlement('11000000', [[$cost, '6000000'], [$line, '5000000']])), 'amount');
        $other = $this->projectFor($this->tenant);
        $otherContract = Contract::factory()->create(['tenant_id' => $this->tenant->id, 'project_id' => $other->id, 'created_by' => $this->owner->id]);
        $foreign = ContractExpense::query()->create(['tenant_id' => $this->tenant->id, 'contract_id' => $otherContract->id, 'expense_date' => '2026-10-01', 'amount' => '100', 'category' => 'labor', 'description' => 'x']);
        $this->expectRule(fn () => $this->advances->requestSettlement($this->project, $this->engineer, $doc, $this->settlement('100', [[$foreign, '100']])), 'allocations');

        $request = $this->advances->requestSettlement($this->project, $this->engineer, $doc, $this->settlement('8000000', [[$cost, '6000000'], [$line, '2000000']], 'Hoá đơn tháng 10'));
        $this->assertSame(Advances::EVENT_REQUESTED, $request->event);
        $this->assertSame(0, Settlement::query()->count());
        $this->expectRule(fn () => $this->advances->requestSettlement($this->project, $this->engineer, $doc, $this->settlement('1000', [[$line, '1000']])));

        $settlement = $this->advances->approveSettlementRequest($this->project, $this->accountant, $doc);

        $this->assertSame(Settlement::SETTLEMENT_TYPE_APPROVED_EXPENSE, $settlement->settlement_type);
        $this->assertSame('8000000.00', (string) $settlement->amount);
        $this->assertSame(200000000, $this->advances->outstandingCents($this->advanceOf($doc)));
        $this->assertSame(2, Allocation::query()->where('advance_settlement_id', $settlement->id)->count());
        $this->assertSame(600000000, $this->expenses->netAllocationCents(Expenses::COST_CONTRACT_EXPENSE, (string) $cost->id));
        $this->assertNull($this->advances->openRequest($doc));
        $this->assertSame(0, Entry::query()->where('source_financial_document_id', $doc->id)->where('direction', 'credit')->count());
        $this->assertSame('90000000.00', $this->balances->walletBalance($this->bank));
    }

    public function test_withdraw_and_reject_close_a_request(): void
    {
        $doc = $this->postedAdvance('1000000');
        $cost = $this->contractExpense('1000000');
        $this->advances->requestSettlement($this->project, $this->engineer, $doc, $this->settlement('400000', [[$cost, '400000']]));
        $this->expectRule(fn () => $this->advances->withdrawSettlementRequest($this->project, $this->accountant, $doc));
        $this->advances->withdrawSettlementRequest($this->project, $this->engineer, $doc);
        $this->assertNull($this->advances->openRequest($doc));
        $this->expectRule(fn () => $this->advances->approveSettlementRequest($this->project, $this->accountant, $doc));

        $this->advances->requestSettlement($this->project, $this->engineer, $doc, $this->settlement('400000', [[$cost, '400000']]));
        $this->expectRule(fn () => $this->advances->rejectSettlementRequest($this->project, $this->accountant, $doc, ''), 'note');
        $this->advances->rejectSettlementRequest($this->project, $this->accountant, $doc, 'Thiếu hoá đơn');
        $this->assertNull($this->advances->openRequest($doc));
        $this->assertSame(0, Settlement::query()->count());
    }

    public function test_self_approval_of_a_settlement_follows_s3(): void
    {
        $doc = $this->postedAdvance('1000000');
        $cost = $this->contractExpense('1000000');
        $this->advances->requestSettlement($this->project, $this->accountant, $doc, $this->settlement('100000', [[$cost, '100000']]));
        $this->expectRule(fn () => $this->advances->approveSettlementRequest($this->project, $this->accountant, $doc));
        $this->advances->withdrawSettlementRequest($this->project, $this->accountant, $doc);

        $this->advances->requestSettlement($this->project, $this->owner, $doc, $this->settlement('100000', [[$cost, '100000']]));
        $this->advances->approveSettlementRequest($this->project, $this->owner, $doc);
        $this->assertSame('self_approval', Approval::query()->where('event', Advances::EVENT_SETTLED)->firstOrFail()->context['approval_mode']);
    }

    public function test_approval_rechecks_outstanding_and_the_cost_cap_shared_with_s3(): void
    {
        $doc = $this->postedAdvance('1000000');
        $cost = $this->contractExpense('1000000');

        // A return recorded after filing leaves less outstanding than requested.
        $this->advances->requestSettlement($this->project, $this->engineer, $doc, $this->settlement('800000', [[$cost, '800000']]));
        $this->advances->recordReturn($this->project, $this->accountant, $doc, $this->returnData('300000'));
        $this->expectRule(fn () => $this->advances->approveSettlementRequest($this->project, $this->accountant, $doc), 'amount');
        $this->advances->rejectSettlementRequest($this->project, $this->accountant, $doc, 'Đã nộp lại một phần');

        // An S3 expense paying the same cost after filing leaves less of the cost.
        $this->advances->requestSettlement($this->project, $this->engineer, $doc, $this->settlement('700000', [[$cost, '700000']]));
        $expense = $this->expenses->createDraft($this->project, $this->owner, [
            'source_wallet_id' => (string) $this->bank->id, 'destination_party_id' => (string) $this->holder->id, 'amount' => '500000',
            'transaction_date' => '2026-10-04', 'allocations' => [['cost_source_type' => Expenses::COST_CONTRACT_EXPENSE, 'cost_source_id' => (string) $cost->id, 'amount' => '500000']],
        ]);
        $this->expenses->submit($this->project, $this->owner, $expense);
        $this->expenses->approve($this->project, $this->accountant, $expense->refresh());
        $this->expectRule(fn () => $this->advances->approveSettlementRequest($this->project, $this->accountant, $doc), 'allocations');
        $this->assertSame(0, Settlement::query()->where('settlement_type', Settlement::SETTLEMENT_TYPE_APPROVED_EXPENSE)->count());
    }

    public function test_removing_a_settlement_compensates_every_allocation_and_frees_the_cost(): void
    {
        $doc = $this->postedAdvance('1000000');
        $cost = $this->contractExpense('600000');
        $this->advances->requestSettlement($this->project, $this->engineer, $doc, $this->settlement('600000', [[$cost, '600000']]));
        $settlement = $this->advances->approveSettlementRequest($this->project, $this->accountant, $doc);

        $this->expectRule(fn () => $this->advances->removeSettlement($this->project, $this->accountant, $settlement, ''), 'reason');
        $reverse = $this->advances->removeSettlement($this->project, $this->accountant, $settlement, 'Gắn nhầm chi phí');

        $this->assertSame(Settlement::DIRECTION_REVERSE, $reverse->direction);
        $this->assertSame((string) $settlement->id, (string) $reverse->reverses_settlement_id);
        $this->assertSame(100000000, $this->advances->outstandingCents($this->advanceOf($doc)));
        $this->assertSame(0, $this->expenses->netAllocationCents(Expenses::COST_CONTRACT_EXPENSE, (string) $cost->id));
        $this->assertSame(1, Allocation::query()->where('advance_settlement_id', $reverse->id)->whereNotNull('reverses_allocation_id')->count());
        $this->expectRule(fn () => $this->advances->removeSettlement($this->project, $this->accountant, $settlement, 'lần hai'));
    }

    public function test_return_credits_the_wallet_and_its_reversal_restores_outstanding(): void
    {
        $doc = $this->postedAdvance('1000000');
        $this->expectRule(fn () => $this->advances->recordReturn($this->project, $this->accountant, $doc, $this->returnData('1000001')), 'amount');

        $return = $this->advances->recordReturn($this->project, $this->accountant, $doc, $this->returnData('400000'));

        $this->assertSame(Document::TYPE_ADVANCE_RETURN, $return->document_type);
        $this->assertSame((string) $this->holder->id, (string) $return->source_party_id);
        $this->assertSame('99400000.00', $this->balances->walletBalance($this->bank));
        $apply = Settlement::query()->where('financial_document_id', $return->id)->firstOrFail();
        $this->assertSame(Settlement::SETTLEMENT_TYPE_CASH_RETURN, $apply->settlement_type);
        $this->assertSame(60000000, $this->advances->outstandingCents($this->advanceOf($doc)));
        $this->expectRule(fn () => $this->advances->removeSettlement($this->project, $this->accountant, $apply, 'x'));

        $reversal = $this->posting->reverse($this->project, $this->accountant, $return, ['transaction_date' => '2026-10-05', 'description' => 'Ghi nhầm']);

        $reverse = Settlement::query()->where('reverses_settlement_id', $apply->id)->firstOrFail();
        $this->assertSame(Settlement::SETTLEMENT_TYPE_CASH_RETURN, $reverse->settlement_type);
        $this->assertSame((string) $reversal->id, (string) $reverse->financial_document_id);
        $this->assertSame(100000000, $this->advances->outstandingCents($this->advanceOf($doc)));
        $this->assertSame('99000000.00', $this->balances->walletBalance($this->bank));
    }

    public function test_an_advance_is_reversed_only_once_its_settlements_are_undone(): void
    {
        $doc = $this->postedAdvance('1000000');
        $cost = $this->contractExpense('1000000');
        $this->advances->requestSettlement($this->project, $this->engineer, $doc, $this->settlement('200000', [[$cost, '200000']]));
        $settlement = $this->advances->approveSettlementRequest($this->project, $this->accountant, $doc);
        $return = $this->advances->recordReturn($this->project, $this->accountant, $doc, $this->returnData('100000'));
        $reverse = fn () => $this->posting->reverse($this->project, $this->accountant, $doc->refresh(), ['transaction_date' => '2026-10-06', 'description' => 'Chi nhầm']);

        $this->expectRule($reverse);
        $this->advances->removeSettlement($this->project, $this->accountant, $settlement, 'Gỡ');
        $this->expectRule($reverse);
        $this->posting->reverse($this->project, $this->accountant, $return, ['transaction_date' => '2026-10-06', 'description' => 'Gỡ']);

        $reverse();

        $this->assertSame(Document::STATUS_REVERSED, $doc->fresh()?->status);
        $this->assertSame(0, $this->advances->outstandingCents($this->advanceOf($doc)));
        $this->assertSame('100000000.00', $this->balances->walletBalance($this->bank));
        $this->expectRule(fn () => $this->advances->requestSettlement($this->project, $this->engineer, $doc->refresh(), $this->settlement('1', [[$cost, '1']])));
        $this->assertSame([], $this->advances->ageing($this->project)['rows']);
    }

    public function test_ageing_groups_outstanding_advances_by_days_since_payment(): void
    {
        $today = Carbon::parse('2026-12-31');
        $ages = [29 => '100', 30 => '200', 59 => '300', 60 => '400', 89 => '500', 90 => '600'];
        foreach ($ages as $days => $amount) {
            $doc = $this->postedAdvance($amount);
            $doc->forceFill(['posted_at' => $today->copy()->subDays($days)->setTime(15, 0)])->save();
        }
        $settled = $this->postedAdvance('700');
        $this->advances->recordReturn($this->project, $this->accountant, $settled, $this->returnData('700'));

        $ageing = $this->advances->ageing($this->project, $today);

        $this->assertCount(6, $ageing['rows']);
        $byAmount = collect($ageing['rows'])->mapWithKeys(static fn (array $r): array => [(string) $r['amount'] => [$r['days'], $r['bucket']]])->all();
        $this->assertSame([29, Advances::BUCKET_UNDER_30], $byAmount['100.00']);
        $this->assertSame([30, Advances::BUCKET_30_60], $byAmount['200.00']);
        $this->assertSame([59, Advances::BUCKET_30_60], $byAmount['300.00']);
        $this->assertSame([60, Advances::BUCKET_60_90], $byAmount['400.00']);
        $this->assertSame([89, Advances::BUCKET_60_90], $byAmount['500.00']);
        $this->assertSame([90, Advances::BUCKET_OVER_90], $byAmount['600.00']);
        $this->assertSame(['under_30' => '100.00', '30_60' => '500.00', '60_90' => '900.00', 'over_90' => '600.00'], $ageing['totals']);
        $this->assertSame('Kỹ sư Z', $ageing['rows'][0]['party_name']);
    }

    public function test_advance_and_return_documents_reconcile_per_wallet(): void
    {
        $doc = $this->postedAdvance('1000000');
        $return = $this->advances->recordReturn($this->project, $this->accountant, $doc, $this->returnData('100000'));
        $entries = Entry::query()->whereIn('source_financial_document_id', [$doc->id, $return->id])->pluck('id')->map(static fn ($id): string => (string) $id)->all();

        app(TreasuryReconciliationService::class)->reconcile($this->project, $this->owner, $this->bank, [
            'reconciliation_type' => 'bank_statement', 'external_reference' => 'SK-9', 'reconciled_at' => now()->subDay()->toDateString(), 'ledger_entry_ids' => $entries,
        ]);

        $this->assertSame(Document::STATUS_POSTED_RECONCILED, $doc->fresh()?->status);
        $this->assertSame(Document::STATUS_POSTED_RECONCILED, $return->fresh()?->status);
        // A reconciled advance still takes settlements.
        $cost = $this->contractExpense('100000');
        $this->advances->requestSettlement($this->project, $this->engineer, $doc->refresh(), $this->settlement('100000', [[$cost, '100000']]));
        $this->advances->approveSettlementRequest($this->project, $this->accountant, $doc->refresh());
        $this->assertSame(80000000, $this->advances->outstandingCents($this->advanceOf($doc)));
    }

    private function postedAdvance(string $amount): Document
    {
        $draft = $this->advances->createDraft($this->project, $this->accountant, $this->draftData($amount, $this->bank));
        $this->advances->submit($this->project, $this->accountant, $draft);

        return $this->advances->approve($this->project, $this->owner, $draft->refresh());
    }

    private function advanceOf(Document $doc): Advance
    {
        return Advance::query()->where('originating_financial_document_id', $doc->id)->firstOrFail();
    }

    /**
     * @return array<string, string>
     */
    private function draftData(string $amount, ?TreasuryWallet $wallet = null): array
    {
        return [
            'source_wallet_id' => (string) ($wallet ?? $this->engineerCash)->id,
            'destination_party_id' => (string) $this->holder->id,
            'amount' => $amount,
            'transaction_date' => '2026-10-03',
            'description' => 'Tạm ứng mua vật tư',
        ];
    }

    /**
     * @param list<array{0: ContractExpense, 1: string}> $lines
     * @return array<string, mixed>
     */
    private function settlement(string $amount, array $lines, ?string $note = null): array
    {
        return [
            'amount' => $amount,
            'note' => $note,
            'allocations' => array_map(static fn (array $l): array => ['cost_source_type' => Expenses::COST_CONTRACT_EXPENSE, 'cost_source_id' => (string) $l[0]->id, 'amount' => $l[1]], $lines),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function returnData(string $amount): array
    {
        return ['destination_wallet_id' => (string) $this->bank->id, 'amount' => $amount, 'transaction_date' => '2026-10-05'];
    }

    private function contractExpense(string $amount): ContractExpense
    {
        return ContractExpense::query()->create([
            'tenant_id' => $this->tenant->id, 'contract_id' => $this->contract->id, 'expense_date' => '2026-10-01',
            'amount' => $amount, 'category' => 'labor', 'description' => 'Nhân công',
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
                $this->assertSame($field, $e->field, $e->getMessage());
            }
        }
    }
}
