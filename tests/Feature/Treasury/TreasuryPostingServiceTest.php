<?php declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryLedgerEntry as Entry;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryBalanceService;
use App\Services\Treasury\TreasuryDuplicateSuspected;
use App\Services\Treasury\TreasuryPostingService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP-064 S2 — ledger engine rules (GAP-037 v17 + approved Gate 2 Option A).
 */
class TreasuryPostingServiceTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;
    private Project $project;
    private User $owner;
    private TreasuryPostingService $posting;
    private TreasuryBalanceService $balances;
    private TreasuryFinancialParty $investor;
    private TreasuryWallet $bank;
    private TreasuryWallet $siteCash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTreasuryPermissions();
        $this->tenant = Tenant::factory()->create();
        $this->project = $this->projectFor($this->tenant);
        $this->owner = $this->userWithRole($this->tenant, 'Admin');
        $this->posting = app(TreasuryPostingService::class);
        $this->balances = app(TreasuryBalanceService::class);
        $this->investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor']);
        $this->bank = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Bank']);
        $this->siteCash = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Site cash']);
    }

    public function test_scenario_a_funding_posts_immediately_and_raises_the_wallet(): void
    {
        $doc = $this->fund('100000000');

        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, $doc->status);
        $this->assertSame(Document::POSTING_PATH_DIRECT, $doc->posting_path);
        $this->assertSame((string) $this->owner->id, (string) $doc->approved_by);
        $this->assertSame('100000000.00', $this->balances->walletBalance($this->bank));
        $summary = $this->balances->projectSummary($this->project);
        $this->assertSame('100000000.00', $summary['investor_funding']);
        $this->assertSame('100000000.00', $summary['held_total']);
        $this->assertSame(1, Entry::query()->where('source_financial_document_id', $doc->id)->where('direction', 'credit')->count());
    }

    public function test_owner_contribution_needs_an_owner_party_and_is_reported_separately(): void
    {
        $ownerParty = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'owner']);

        try {
            $this->fund('5000000', type: Document::TYPE_OWNER_CONTRIBUTION);
            $this->fail('Owner contribution from an investor party must be refused.');
        } catch (TreasuryRuleViolation $e) {
            $this->assertSame('source_party_id', $e->field);
        }

        $this->fund('5000000', type: Document::TYPE_OWNER_CONTRIBUTION, partyId: (string) $ownerParty->id);
        $summary = $this->balances->projectSummary($this->project);
        $this->assertSame('5000000.00', $summary['owner_contribution']);
        $this->assertSame('0.00', $summary['investor_funding']);
    }

    public function test_scenario_c_transfer_moves_money_without_changing_project_totals(): void
    {
        $this->fund('100000000');

        $transfer = $this->posting->transfer($this->project, $this->owner, [
            'source_wallet_id' => (string) $this->bank->id,
            'destination_wallet_id' => (string) $this->siteCash->id,
            'amount' => '50000000',
            'transaction_date' => '2026-10-02',
        ]);

        $this->assertSame('50000000.00', $this->balances->walletBalance($this->bank));
        $this->assertSame('50000000.00', $this->balances->walletBalance($this->siteCash));
        $summary = $this->balances->projectSummary($this->project);
        $this->assertSame('100000000.00', $summary['held_total']);
        $this->assertSame('100000000.00', $summary['investor_funding']);
        $entries = Entry::query()->where('source_financial_document_id', $transfer->id)->get();
        $this->assertSame(['credit', 'debit'], $entries->pluck('direction')->sort()->values()->all());
    }

    public function test_transfer_beyond_the_balance_is_refused_and_writes_nothing(): void
    {
        $this->fund('10000000');

        $this->expectRule(fn () => $this->posting->transfer($this->project, $this->owner, [
            'source_wallet_id' => (string) $this->bank->id,
            'destination_wallet_id' => (string) $this->siteCash->id,
            'amount' => '10000000.01',
            'transaction_date' => '2026-10-02',
        ]), 'source_wallet_id');

        $this->assertSame(1, Document::query()->count());
        $this->assertSame('10000000.00', $this->balances->walletBalance($this->bank));
    }

    public function test_transfer_to_the_same_wallet_or_another_project_wallet_is_refused(): void
    {
        $this->fund('1000000');
        $foreignWallet = TreasuryWallet::factory()->create(['project_id' => $this->projectFor($this->tenant)->id]);

        $this->expectRule(fn () => $this->posting->transfer($this->project, $this->owner, [
            'source_wallet_id' => (string) $this->bank->id, 'destination_wallet_id' => (string) $this->bank->id,
            'amount' => '1', 'transaction_date' => '2026-10-02',
        ]), 'destination_wallet_id');
        $this->expectRule(fn () => $this->posting->transfer($this->project, $this->owner, [
            'source_wallet_id' => (string) $this->bank->id, 'destination_wallet_id' => (string) $foreignWallet->id,
            'amount' => '1', 'transaction_date' => '2026-10-02',
        ]), 'destination_wallet_id');
    }

    public function test_engineer_transfers_only_from_a_wallet_they_hold(): void
    {
        $engineer = $this->userWithRole($this->tenant, 'site_engineer');
        $this->addMember($this->project, $engineer);
        $custodian = TreasuryFinancialParty::factory()->create([
            'tenant_id' => $this->tenant->id, 'party_type' => 'employee', 'linked_user_id' => $engineer->id,
        ]);
        $held = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'custodian_party_id' => $custodian->id]);
        $this->fund('3000000');
        $this->posting->transfer($this->project, $this->owner, [
            'source_wallet_id' => (string) $this->bank->id, 'destination_wallet_id' => (string) $held->id,
            'amount' => '2000000', 'transaction_date' => '2026-10-02',
        ]);

        $this->expectRule(fn () => $this->posting->transfer($this->project, $engineer, [
            'source_wallet_id' => (string) $this->bank->id, 'destination_wallet_id' => (string) $this->siteCash->id,
            'amount' => '100', 'transaction_date' => '2026-10-03',
        ]), 'source_wallet_id');

        $this->posting->transfer($this->project, $engineer, [
            'source_wallet_id' => (string) $held->id, 'destination_wallet_id' => (string) $this->siteCash->id,
            'amount' => '500000', 'transaction_date' => '2026-10-03',
        ]);
        $this->assertSame('1500000.00', $this->balances->walletBalance($held));
    }

    public function test_adjustments_need_a_reason_and_decrease_cannot_overdraw(): void
    {
        $this->expectRule(fn () => $this->posting->adjust($this->project, $this->owner, [
            'wallet_id' => (string) $this->siteCash->id, 'direction' => 'increase', 'amount' => '200000',
            'transaction_date' => '2026-10-01', 'description' => '  ',
        ]), 'description');

        $this->posting->adjust($this->project, $this->owner, [
            'wallet_id' => (string) $this->siteCash->id, 'direction' => 'increase', 'amount' => '200000',
            'transaction_date' => '2026-10-01', 'description' => 'Số dư đầu kỳ',
        ]);
        $this->expectRule(fn () => $this->posting->adjust($this->project, $this->owner, [
            'wallet_id' => (string) $this->siteCash->id, 'direction' => 'decrease', 'amount' => '200001',
            'transaction_date' => '2026-10-01', 'description' => 'Kiểm quỹ thiếu',
        ]), 'amount');
        $this->posting->adjust($this->project, $this->owner, [
            'wallet_id' => (string) $this->siteCash->id, 'direction' => 'decrease', 'amount' => '50000',
            'transaction_date' => '2026-10-01', 'description' => 'Kiểm quỹ thiếu',
        ]);

        $this->assertSame('150000.00', $this->balances->walletBalance($this->siteCash));
        $this->assertSame('0.00', $this->balances->projectSummary($this->project)['investor_funding']);
    }

    public function test_scenario_f_wrong_amount_is_reversed_and_replaced_traceably(): void
    {
        $wrong = $this->fund('1000000000');

        $reversal = $this->posting->reverse($this->project, $this->owner, $wrong, [
            'transaction_date' => '2026-10-02', 'description' => 'Nhập sai số tiền',
        ]);
        $replacement = $this->fund('100000000', reference: 'UNC-2');
        $this->posting->linkReplacement($this->project, $this->owner, $reversal, $replacement);

        $wrong->refresh();
        $reversal->refresh();
        $this->assertSame(Document::STATUS_REVERSED, $wrong->status);
        $this->assertSame((string) $wrong->id, (string) $reversal->reversed_document_id);
        $this->assertSame((string) $replacement->id, (string) $reversal->replacement_document_id);
        $this->assertSame((string) $this->investor->id, (string) $reversal->destination_party_id);
        $this->assertSame((string) $this->bank->id, (string) $reversal->source_wallet_id);
        $this->assertSame('100000000.00', $this->balances->walletBalance($this->bank));
        $this->assertSame('100000000.00', $this->balances->projectSummary($this->project)['investor_funding']);
        $this->assertSame(3, Document::query()->count());
    }

    public function test_reversal_rules_once_only_never_of_a_reversal_and_exempt_from_the_balance_guard(): void
    {
        $funding = $this->fund('10000000');
        $this->posting->transfer($this->project, $this->owner, [
            'source_wallet_id' => (string) $this->bank->id, 'destination_wallet_id' => (string) $this->siteCash->id,
            'amount' => '10000000', 'transaction_date' => '2026-10-02',
        ]);

        $reversal = $this->posting->reverse($this->project, $this->owner, $funding, ['transaction_date' => '2026-10-03', 'description' => 'Sai']);
        $this->assertSame('-10000000.00', $this->balances->walletBalance($this->bank));

        $this->expectRule(fn () => $this->posting->reverse($this->project, $this->owner, $funding->refresh(), ['transaction_date' => '2026-10-03', 'description' => 'Lần 2']));
        $this->expectRule(fn () => $this->posting->reverse($this->project, $this->owner, $reversal, ['transaction_date' => '2026-10-03', 'description' => 'Đảo của đảo']));
        $this->expectRule(fn () => $this->posting->reverse($this->project, $this->owner, $funding, ['transaction_date' => '2026-10-03', 'description' => '']), 'description');
    }

    public function test_transfer_reversal_swaps_both_wallets(): void
    {
        $this->fund('8000000');
        $transfer = $this->posting->transfer($this->project, $this->owner, [
            'source_wallet_id' => (string) $this->bank->id, 'destination_wallet_id' => (string) $this->siteCash->id,
            'amount' => '3000000', 'transaction_date' => '2026-10-02',
        ]);

        $reversal = $this->posting->reverse($this->project, $this->owner, $transfer, ['transaction_date' => '2026-10-03', 'description' => 'Chuyển nhầm']);

        $this->assertSame((string) $this->siteCash->id, (string) $reversal->source_wallet_id);
        $this->assertSame((string) $this->bank->id, (string) $reversal->destination_wallet_id);
        $this->assertSame('8000000.00', $this->balances->walletBalance($this->bank));
        $this->assertSame('0.00', $this->balances->walletBalance($this->siteCash));
    }

    public function test_reversal_from_another_project_is_refused(): void
    {
        $doc = $this->fund('1000');

        $this->expectRule(fn () => $this->posting->reverse($this->projectFor($this->tenant), $this->owner, $doc, ['transaction_date' => '2026-10-03', 'description' => 'x']));
    }

    public function test_duplicate_declaration_warns_until_confirmed(): void
    {
        $this->fund('7000000', reference: 'UNC-1');

        try {
            $this->fund('7000000', reference: 'UNC-1');
            $this->fail('A duplicate must raise a warning.');
        } catch (TreasuryDuplicateSuspected $e) {
            $this->assertSame(1, Document::query()->count());
        }

        $this->fund('7000000', reference: 'UNC-1', confirm: true);
        $this->fund('7000000', reference: 'UNC-9');
        $this->assertSame(3, Document::query()->count());
    }

    public function test_posting_key_makes_a_replayed_entry_impossible(): void
    {
        $doc = $this->fund('1000');
        $entry = Entry::query()->where('source_financial_document_id', $doc->id)->firstOrFail();

        $this->expectException(QueryException::class);
        Entry::query()->create($entry->only(['tenant_id', 'source_financial_document_id', 'wallet_id', 'direction', 'amount', 'entry_type', 'posted_at', 'original_posting_key']));
    }

    public function test_every_action_writes_an_audit_row(): void
    {
        $doc = $this->fund('1000');
        $this->posting->reverse($this->project, $this->owner, $doc, ['transaction_date' => '2026-10-03', 'description' => 'Sai']);

        $actions = AuditLog::query()->where('entity_type', 'treasury_financial_document')->pluck('action')->sort()->values()->all();
        $this->assertSame(['treasury.document.reversed', 'treasury.funding.posted', 'treasury.reversal.posted'], $actions);
        $posted = AuditLog::query()->where('action', 'treasury.funding.posted')->firstOrFail();
        $this->assertSame(['draft', 'submitted', 'approved', 'posted_unreconciled'], $posted->new_data['status_path']);
    }

    private function fund(string $amount, string $type = Document::TYPE_FUNDING, ?string $partyId = null, ?string $reference = 'UNC-1', bool $confirm = false): Document
    {
        return $this->posting->declareFunding($this->project, $this->owner, [
            'document_type' => $type,
            'source_party_id' => $partyId ?? (string) $this->investor->id,
            'destination_wallet_id' => (string) $this->bank->id,
            'amount' => $amount,
            'transaction_date' => '2026-10-01',
            'reference' => $reference,
        ], $confirm);
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
