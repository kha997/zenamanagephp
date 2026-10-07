<?php declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryCostSettlementAllocation as Allocation;
use App\Models\Treasury\TreasuryExpenseApproval as Approval;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryBalanceService;
use App\Services\Treasury\TreasuryExpenseService as Expenses;
use App\Services\Treasury\TreasuryPostingService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * GAP-066 S3 — expense workflow rules (GAP-037 v17 + approved Gate 2 Option A).
 */
class TreasuryExpenseServiceTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;
    private Project $project;
    private User $owner;
    private User $engineer;
    private User $accountant;
    private Expenses $expenses;
    private TreasuryBalanceService $balances;
    private TreasuryWallet $bank;
    private TreasuryWallet $engineerCash;
    private TreasuryFinancialParty $labour;
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
        $this->expenses = app(Expenses::class);
        $this->balances = app(TreasuryBalanceService::class);

        $investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor']);
        $holder = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'employee', 'linked_user_id' => $this->engineer->id]);
        $this->labour = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'labour', 'name' => 'Tổ đội D']);
        $this->bank = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Bank']);
        $this->engineerCash = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Z cash', 'custodian_party_id' => $holder->id]);
        $posting = app(TreasuryPostingService::class);
        $posting->declareFunding($this->project, $this->owner, [
            'document_type' => 'funding', 'source_party_id' => (string) $investor->id,
            'destination_wallet_id' => (string) $this->bank->id, 'amount' => '100000000', 'transaction_date' => '2026-10-01',
        ]);
        $posting->transfer($this->project, $this->owner, [
            'source_wallet_id' => (string) $this->bank->id, 'destination_wallet_id' => (string) $this->engineerCash->id,
            'amount' => '20000000', 'transaction_date' => '2026-10-02',
        ]);
        $this->contract = Contract::factory()->create(['tenant_id' => $this->tenant->id, 'project_id' => $this->project->id, 'created_by' => $this->owner->id]);
    }

    public function test_engineer_expense_waits_for_approval_then_posts_and_settles_the_cost(): void
    {
        $cost = $this->contractExpense('15000000');
        $draft = $this->draft($this->engineer, '15000000', [[Expenses::COST_CONTRACT_EXPENSE, $cost->id, '15000000']]);
        $this->expenses->submit($this->project, $this->engineer, $draft);

        // Scenario D: nothing moves before approval.
        $this->assertSame('20000000.00', $this->balances->walletBalance($this->engineerCash));
        $this->assertSame(0, Allocation::query()->count());

        $posted = $this->expenses->approve($this->project, $this->accountant, $draft->refresh());

        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, $posted->status);
        $this->assertSame('5000000.00', $this->balances->walletBalance($this->engineerCash));
        $this->assertSame(0, $this->expenses->incurredCents(Expenses::COST_CONTRACT_EXPENSE, (string) $cost->id) - $this->expenses->netAllocationCents(Expenses::COST_CONTRACT_EXPENSE, (string) $cost->id));
        $this->assertSame(['submitted', 'approved', 'posted'], Approval::query()->where('financial_document_id', $draft->id)->orderBy('created_at')->orderBy('id')->pluck('event')->all());
        $this->assertSame('standard', Approval::query()->where('event', 'approved')->firstOrFail()->context['approval_mode']);
        $this->assertSame('15000000.00', $this->balances->projectSummary($this->project)['expenses']);
        $this->assertSame('0.00', $this->balances->projectSummary($this->project)['self_approved_expenses']);
    }

    public function test_scenario_e_owner_self_approval_is_recorded_and_reported(): void
    {
        $cost = $this->contractExpense('3000000');
        $draft = $this->draft($this->owner, '3000000', [[Expenses::COST_CONTRACT_EXPENSE, $cost->id, '3000000']], $this->bank);
        $this->expenses->submit($this->project, $this->owner, $draft);
        $this->expenses->approve($this->project, $this->owner, $draft->refresh());

        $draft->refresh();
        $this->assertSame((string) $this->owner->id, (string) $draft->created_by);
        $this->assertSame((string) $this->owner->id, (string) $draft->approved_by);
        $this->assertSame('self_approval', Approval::query()->where('event', 'approved')->firstOrFail()->context['approval_mode']);
        $this->assertSame('3000000.00', $this->balances->projectSummary($this->project)['self_approved_expenses']);
    }

    public function test_non_owner_cannot_self_approve(): void
    {
        $cost = $this->contractExpense('1000000');
        $draft = $this->draft($this->engineer, '1000000', [[Expenses::COST_CONTRACT_EXPENSE, $cost->id, '1000000']]);
        $this->expenses->submit($this->project, $this->engineer, $draft);

        $this->expectRule(fn () => $this->expenses->approve($this->project, $this->engineer, $draft->refresh()));
        $this->assertSame(Document::STATUS_SUBMITTED, $draft->refresh()->status);
    }

    public function test_installments_and_cap_on_one_cost(): void
    {
        $cost = $this->contractExpense('20000000');
        foreach (['8000000', '7000000'] as $amount) {
            $this->approveNew($this->owner, $amount, [[Expenses::COST_CONTRACT_EXPENSE, $cost->id, $amount]], $this->bank);
        }
        $this->assertSame(15_000_000_00, $this->expenses->netAllocationCents(Expenses::COST_CONTRACT_EXPENSE, (string) $cost->id));

        $over = $this->draft($this->owner, '5000001', [[Expenses::COST_CONTRACT_EXPENSE, $cost->id, '5000001']], $this->bank);
        $this->expenses->submit($this->project, $this->owner, $over);
        $this->expectRule(fn () => $this->expenses->approve($this->project, $this->owner, $over->refresh()), 'allocations');
        $this->assertSame(Document::STATUS_SUBMITTED, $over->refresh()->status);
    }

    public function test_one_payment_settles_a_contract_cost_and_a_material_line(): void
    {
        $cost = $this->contractExpense('4000000');
        $line = $this->materialLine('10', '300000');
        $this->approveNew($this->owner, '7000000', [
            [Expenses::COST_CONTRACT_EXPENSE, $cost->id, '4000000'],
            [Expenses::COST_MATERIAL_LINE, $line, '3000000'],
        ], $this->bank);

        $this->assertSame(2, Allocation::query()->where('direction', 'apply')->count());
        $this->assertSame(0, $this->expenses->incurredCents(Expenses::COST_MATERIAL_LINE, $line) - $this->expenses->netAllocationCents(Expenses::COST_MATERIAL_LINE, $line));
    }

    public function test_new_contract_expense_is_created_atomically_only_at_approval(): void
    {
        $draft = $this->expenses->createDraft($this->project, $this->engineer, $this->payload('2500000', [], $this->engineerCash) + [
            'new_contract_expense' => ['contract_id' => (string) $this->contract->id, 'category' => 'labor', 'description' => 'Công nhật tuần 40', 'amount' => '2500000'],
        ]);
        $this->expenses->submit($this->project, $this->engineer, $draft);
        $this->assertSame(0, ContractExpense::query()->count());

        $this->expenses->approve($this->project, $this->owner, $draft->refresh());

        $created = ContractExpense::query()->sole();
        $this->assertSame('labor', $created->category);
        $this->assertSame((string) $created->id, (string) Allocation::query()->sole()->cost_source_contract_expense_id);
    }

    public function test_plan_must_cover_the_amount_and_stay_in_the_project(): void
    {
        $cost = $this->contractExpense('9000000');
        $this->expectRule(fn () => $this->draft($this->owner, '9000000', [[Expenses::COST_CONTRACT_EXPENSE, $cost->id, '8000000']], $this->bank), 'allocations');
        $this->expectRule(fn () => $this->draft($this->owner, '1000', [], $this->bank), 'allocations');

        $otherContract = Contract::factory()->create(['tenant_id' => $this->tenant->id, 'project_id' => $this->projectFor($this->tenant)->id, 'created_by' => $this->owner->id]);
        $foreignCost = ContractExpense::query()->create(['tenant_id' => $this->tenant->id, 'contract_id' => $otherContract->id, 'expense_date' => '2026-10-01', 'amount' => '1000', 'category' => 'misc', 'description' => 'x']);
        $this->expectRule(fn () => $this->draft($this->owner, '1000', [[Expenses::COST_CONTRACT_EXPENSE, $foreignCost->id, '1000']], $this->bank), 'allocations');
    }

    public function test_engineer_spends_only_from_a_held_wallet(): void
    {
        $cost = $this->contractExpense('1000');
        $this->expectRule(fn () => $this->draft($this->engineer, '1000', [[Expenses::COST_CONTRACT_EXPENSE, $cost->id, '1000']], $this->bank), 'source_wallet_id');
    }

    public function test_insufficient_balance_blocks_approval(): void
    {
        $cost = $this->contractExpense('30000000');
        $draft = $this->draft($this->engineer, '25000000', [[Expenses::COST_CONTRACT_EXPENSE, $cost->id, '25000000']]);
        $this->expenses->submit($this->project, $this->engineer, $draft);

        $this->expectRule(fn () => $this->expenses->approve($this->project, $this->owner, $draft->refresh()), 'source_wallet_id');
        $this->assertSame(0, Allocation::query()->count());
    }

    public function test_rejected_is_terminal_and_can_be_copied_into_a_new_draft(): void
    {
        $cost = $this->contractExpense('1000000');
        $draft = $this->draft($this->engineer, '1000000', [[Expenses::COST_CONTRACT_EXPENSE, $cost->id, '1000000']]);
        $this->expenses->submit($this->project, $this->engineer, $draft);
        $rejected = $this->expenses->reject($this->project, $this->accountant, $draft->refresh(), 'Thiếu hoá đơn');

        $this->expectRule(fn () => $this->expenses->submit($this->project, $this->engineer, $rejected));
        $this->expectRule(fn () => $this->expenses->approve($this->project, $this->owner, $rejected));
        $this->expectRule(fn () => $this->expenses->reject($this->project, $this->accountant, $rejected, 'again'));

        $copy = $this->expenses->copyToDraft($this->project, $this->engineer, $rejected);
        $this->assertSame(Document::STATUS_DRAFT, $copy->status);
        $this->assertSame(Document::STATUS_REJECTED, $rejected->refresh()->status);
        $this->assertSame($rejected->expense_plan, $copy->expense_plan);
    }

    public function test_reversing_a_posted_expense_restores_the_remaining_cost_and_keeps_the_cost_record(): void
    {
        $draft = $this->expenses->createDraft($this->project, $this->owner, $this->payload('6000000', [], $this->bank) + [
            'new_contract_expense' => ['contract_id' => (string) $this->contract->id, 'category' => 'subcontractor', 'amount' => '6000000'],
        ]);
        $this->expenses->submit($this->project, $this->owner, $draft);
        $posted = $this->expenses->approve($this->project, $this->owner, $draft->refresh());
        $cost = ContractExpense::query()->sole();

        $reversal = app(TreasuryPostingService::class)->reverse($this->project, $this->owner, $posted, ['transaction_date' => '2026-10-05', 'description' => 'Chi nhầm']);

        $this->assertSame(Document::STATUS_REVERSED, $posted->refresh()->status);
        $this->assertSame(0, $this->expenses->netAllocationCents(Expenses::COST_CONTRACT_EXPENSE, (string) $cost->id));
        $this->assertSame((string) $reversal->id, (string) Allocation::query()->where('direction', 'reverse')->sole()->financial_document_id);
        $this->assertTrue(ContractExpense::query()->whereKey($cost->id)->exists());
        $this->assertSame('80000000.00', $this->balances->walletBalance($this->bank));
        $this->assertSame((string) $this->labour->id, (string) $reversal->source_party_id);
    }

    public function test_only_creator_edits_and_submits_a_draft(): void
    {
        $cost = $this->contractExpense('1000');
        $draft = $this->draft($this->owner, '1000', [[Expenses::COST_CONTRACT_EXPENSE, $cost->id, '1000']], $this->bank);

        $this->expectRule(fn () => $this->expenses->submit($this->project, $this->engineer, $draft));
        $updated = $this->expenses->updateDraft($this->project, $this->owner, $draft, $this->payload('1000', [[Expenses::COST_CONTRACT_EXPENSE, $cost->id, '1000']], $this->bank) + ['description' => 'Đã sửa']);
        $this->assertSame('Đã sửa', $updated->description);
    }

    /**
     * @param list<array{0: string, 1: string, 2: string}> $lines
     */
    private function approveNew(User $actor, string $amount, array $lines, TreasuryWallet $wallet): Document
    {
        $draft = $this->draft($actor, $amount, $lines, $wallet);
        $this->expenses->submit($this->project, $actor, $draft);

        return $this->expenses->approve($this->project, $this->owner, $draft->refresh());
    }

    /**
     * @param list<array{0: string, 1: string, 2: string}> $lines
     */
    private function draft(User $actor, string $amount, array $lines, ?TreasuryWallet $wallet = null): Document
    {
        return $this->expenses->createDraft($this->project, $actor, $this->payload($amount, $lines, $wallet ?? $this->engineerCash));
    }

    /**
     * @param list<array{0: string, 1: string, 2: string}> $lines
     * @return array<string, mixed>
     */
    private function payload(string $amount, array $lines, TreasuryWallet $wallet): array
    {
        return [
            'source_wallet_id' => (string) $wallet->id,
            'destination_party_id' => (string) $this->labour->id,
            'amount' => $amount,
            'transaction_date' => '2026-10-04',
            'allocations' => array_map(static fn (array $l): array => ['cost_source_type' => $l[0], 'cost_source_id' => (string) $l[1], 'amount' => $l[2]], $lines),
        ];
    }

    private function contractExpense(string $amount): ContractExpense
    {
        return ContractExpense::query()->create([
            'tenant_id' => $this->tenant->id, 'contract_id' => $this->contract->id, 'expense_date' => '2026-10-01',
            'amount' => $amount, 'category' => 'labor', 'description' => 'Nhân công',
        ]);
    }

    private function materialLine(string $quantity, string $unitCost): string
    {
        $materialId = (string) Str::ulid();
        DB::table('materials')->insert(['id' => $materialId, 'tenant_id' => $this->tenant->id, 'code' => 'XM-' . Str::random(4), 'name' => 'Xi măng', 'created_at' => now(), 'updated_at' => now()]);
        $receiptId = (string) Str::ulid();
        DB::table('material_receipts')->insert(['id' => $receiptId, 'tenant_id' => $this->tenant->id, 'project_id' => $this->project->id, 'receipt_number' => 'PN-' . Str::random(4), 'receipt_date' => '2026-10-01', 'created_at' => now(), 'updated_at' => now()]);
        $lineId = (string) Str::ulid();
        DB::table('material_receipt_lines')->insert(['id' => $lineId, 'tenant_id' => $this->tenant->id, 'project_id' => $this->project->id, 'material_receipt_id' => $receiptId, 'material_id' => $materialId, 'quantity_received' => $quantity, 'unit_cost' => $unitCost, 'created_at' => now(), 'updated_at' => now()]);

        return $lineId;
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
