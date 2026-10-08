<?php declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryLedgerEntry as Entry;
use App\Models\Treasury\TreasuryPaymentRoute;
use App\Models\Treasury\TreasuryPaymentRouteLeg;
use App\Models\Treasury\TreasuryReconciliationEntry as RecEntry;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryBalanceService;
use App\Services\Treasury\TreasuryPostingService;
use App\Services\Treasury\TreasuryReconciliationService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP-067 S4a — reconciliation rules (GAP-037 v17 §12 + approved Gate 2 Option A).
 */
class TreasuryReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;
    private Project $project;
    private User $owner;
    private TreasuryPostingService $posting;
    private TreasuryReconciliationService $reconciliation;
    private TreasuryBalanceService $balances;
    private TreasuryFinancialParty $investor;
    private TreasuryWallet $bank;
    private TreasuryWallet $cash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTreasuryPermissions();
        $this->tenant = Tenant::factory()->create();
        $this->project = $this->projectFor($this->tenant);
        $this->owner = $this->userWithRole($this->tenant, 'Admin');
        $this->posting = app(TreasuryPostingService::class);
        $this->reconciliation = app(TreasuryReconciliationService::class);
        $this->balances = app(TreasuryBalanceService::class);
        $this->investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor']);
        $this->bank = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Bank']);
        $this->cash = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Cash']);
    }

    public function test_single_wallet_document_becomes_reconciled_and_balances_do_not_change(): void
    {
        $doc = $this->fund('1000000');
        $before = $this->balances->projectSummary($this->project);

        $rec = $this->reconcile($this->bank, [$this->entryOf($doc, $this->bank)]);

        $this->assertSame('bank_statement', $rec->reconciliation_type);
        $this->assertSame('SK-01', $rec->external_reference);
        $this->assertSame(Document::STATUS_POSTED_RECONCILED, $doc->fresh()?->status);
        $this->assertSame(1, RecEntry::query()->where('reconciliation_id', $rec->id)->where('direction', 'apply')->count());
        $this->assertEquals($before, $this->balances->projectSummary($this->project));
        $this->assertSame(['balance' => '1000000.00', 'reconciled' => '1000000.00', 'unreconciled' => '0.00'], $this->reconciliation->walletSummary($this->bank));
        $this->assertSame(1, AuditLog::query()->where('action', 'treasury.reconciliation.applied')->count());
    }

    public function test_transfer_needs_both_wallets_reconciled(): void
    {
        $this->fund('1000000');
        $transfer = $this->posting->transfer($this->project, $this->owner, [
            'source_wallet_id' => (string) $this->bank->id, 'destination_wallet_id' => (string) $this->cash->id,
            'amount' => '400000', 'transaction_date' => '2026-10-02',
        ]);

        $this->reconcile($this->bank, [$this->entryOf($transfer, $this->bank)]);
        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, $transfer->fresh()?->status);

        $this->reconcile($this->cash, [$this->entryOf($transfer, $this->cash)], type: 'cash_count', reference: null);
        $this->assertSame(Document::STATUS_POSTED_RECONCILED, $transfer->fresh()?->status);
        $this->assertSame(['balance' => '600000.00', 'reconciled' => '-400000.00', 'unreconciled' => '1000000.00'], $this->reconciliation->walletSummary($this->bank));
    }

    public function test_partial_selection_reconciles_only_the_chosen_documents(): void
    {
        $first = $this->fund('100');
        $second = $this->fund('200', reference: 'B');

        $this->reconcile($this->bank, [$this->entryOf($first, $this->bank)]);

        $this->assertSame(Document::STATUS_POSTED_RECONCILED, $first->fresh()?->status);
        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, $second->fresh()?->status);
        $unreconciled = $this->reconciliation->unreconciledEntries($this->bank);
        $this->assertCount(1, $unreconciled);
        $this->assertSame((string) $second->id, (string) data_get($unreconciled[0], 'document_id'));
    }

    public function test_an_entry_cannot_be_reconciled_twice_and_a_bad_line_rejects_the_whole_request(): void
    {
        $first = $this->fund('100');
        $second = $this->fund('200', reference: 'B');
        $this->reconcile($this->bank, [$this->entryOf($first, $this->bank)]);

        $this->expectRule(fn () => $this->reconcile($this->bank, [$this->entryOf($second, $this->bank), $this->entryOf($first, $this->bank)]), 'ledger_entry_ids');

        $this->assertSame(1, RecEntry::query()->count());
        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, $second->fresh()?->status);
    }

    public function test_entries_of_another_wallet_project_or_tenant_are_rejected(): void
    {
        $doc = $this->fund('100');
        $otherProject = $this->projectFor($this->tenant);
        $otherWallet = TreasuryWallet::factory()->create(['project_id' => $otherProject->id]);
        $this->expectRule(fn () => $this->reconcile($this->cash, [$this->entryOf($doc, $this->bank)]), 'ledger_entry_ids');
        $this->expectRule(fn () => $this->reconciliation->reconcile($this->project, $this->owner, $otherWallet, $this->input([$this->entryOf($doc, $this->bank)])), 'wallet');

        $foreignTenant = Tenant::factory()->create();
        $foreignProject = $this->projectFor($foreignTenant);
        $foreignWallet = TreasuryWallet::factory()->create(['project_id' => $foreignProject->id]);
        $this->expectRule(fn () => $this->reconciliation->reconcile($this->project, $this->owner, $foreignWallet, $this->input([$this->entryOf($doc, $this->bank)])), 'wallet');
        $this->expectRule(fn () => $this->reconcile($this->bank, ['01JZZZZZZZZZZZZZZZZZZZZZZZ']), 'ledger_entry_ids');

        $this->assertSame(0, RecEntry::query()->count());
    }

    public function test_route_leg_entries_are_rejected(): void
    {
        // Leg-sourced entries (S4b) cannot be created through the services yet;
        // insert the v17 rows directly.
        $doc = $this->fund('100');
        $route = TreasuryPaymentRoute::query()->create([
            'tenant_id' => (string) $this->tenant->id, 'project_id' => (string) $this->project->id,
            'total_allocated_amount' => '100', 'status' => TreasuryPaymentRoute::STATUS_PLANNED,
            'linked_financial_document_id' => (string) $doc->id,
        ]);
        $leg = TreasuryPaymentRouteLeg::query()->create([
            'tenant_id' => (string) $this->tenant->id, 'payment_route_id' => (string) $route->id, 'sequence_no' => 1,
            'from_wallet_id' => (string) $this->bank->id, 'to_wallet_id' => (string) $this->cash->id,
            'amount' => '100', 'status' => TreasuryPaymentRouteLeg::STATUS_IN_TRANSIT,
        ]);
        $legEntry = Entry::query()->create([
            'tenant_id' => (string) $this->tenant->id, 'source_payment_route_leg_id' => (string) $leg->id,
            'wallet_id' => (string) $this->bank->id, 'direction' => 'debit', 'amount' => '100',
            'entry_type' => 'route_leg', 'posted_at' => now(), 'original_posting_key' => 'leg:' . $leg->id,
        ]);

        $this->expectRule(fn () => $this->reconcile($this->bank, [(string) $legEntry->id]), 'ledger_entry_ids');
        $this->assertCount(1, $this->reconciliation->unreconciledEntries($this->bank));
    }

    public function test_type_reference_and_date_rules(): void
    {
        $entry = $this->entryOf($this->fund('100'), $this->bank);

        $this->expectRule(fn () => $this->reconcile($this->bank, [$entry], type: 'phone_call'), 'reconciliation_type');
        $this->expectRule(fn () => $this->reconcile($this->bank, [$entry], type: 'bank_statement', reference: '  '), 'external_reference');
        $this->expectRule(fn () => $this->reconcile($this->bank, [$entry], type: 'voucher', reference: null), 'external_reference');
        $this->expectRule(fn () => $this->reconcile($this->bank, [$entry], date: now()->addDay()->toDateString()), 'reconciled_at');
        $this->expectRule(fn () => $this->reconcile($this->bank, []), 'ledger_entry_ids');

        $rec = $this->reconcile($this->bank, [$entry], type: 'cash_count', reference: null, date: now()->toDateString());
        $this->assertNull($rec->external_reference);
    }

    public function test_undo_one_line_needs_a_reason_and_regresses_the_document(): void
    {
        $doc = $this->fund('100');
        $rec = $this->reconcile($this->bank, [$this->entryOf($doc, $this->bank)]);
        $apply = RecEntry::query()->where('reconciliation_id', $rec->id)->sole();

        $this->expectRule(fn () => $this->reconciliation->undoEntry($this->project, $this->owner, $apply, '  '), 'reason');

        $reverse = $this->reconciliation->undoEntry($this->project, $this->owner, $apply, 'Sai sao kê');

        $this->assertSame('reverse', $reverse->direction);
        $this->assertSame((string) $apply->id, (string) $reverse->reverses_reconciliation_entry_id);
        $this->assertSame((string) $rec->id, (string) $reverse->reconciliation_id);
        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, $doc->fresh()?->status);
        $log = AuditLog::query()->where('action', 'treasury.reconciliation.undone')->sole();
        $this->assertSame('Sai sao kê', data_get($log->new_data, 'reason'));
        $this->assertSame(['balance' => '100.00', 'reconciled' => '0.00', 'unreconciled' => '100.00'], $this->reconciliation->walletSummary($this->bank));

        $this->expectRule(fn () => $this->reconciliation->undoEntry($this->project, $this->owner, $apply, 'Lần hai'), 'reconciliation_entry');
        $this->expectRule(fn () => $this->reconciliation->undoEntry($this->project, $this->owner, $reverse, 'Gỡ dòng gỡ'), 'reconciliation_entry');

        // The entry can be reconciled again after the undo.
        $this->reconcile($this->bank, [$this->entryOf($doc, $this->bank)], reference: 'SK-02');
        $this->assertSame(Document::STATUS_POSTED_RECONCILED, $doc->fresh()?->status);
    }

    public function test_undo_a_whole_reconciliation(): void
    {
        $first = $this->fund('100');
        $second = $this->fund('200', reference: 'B');
        $rec = $this->reconcile($this->bank, [$this->entryOf($first, $this->bank), $this->entryOf($second, $this->bank)]);
        $firstApply = RecEntry::query()->where('ledger_entry_id', $this->entryOf($first, $this->bank))->sole();
        $this->reconciliation->undoEntry($this->project, $this->owner, $firstApply, 'Dòng sai');

        $this->expectRule(fn () => $this->reconciliation->undoReconciliation($this->project, $this->owner, $rec, ''), 'reason');
        $reverses = $this->reconciliation->undoReconciliation($this->project, $this->owner, $rec, 'Cả sao kê sai');

        $this->assertCount(1, $reverses);
        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, $first->fresh()?->status);
        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, $second->fresh()?->status);
        $this->assertSame(4, RecEntry::query()->where('reconciliation_id', $rec->id)->count());
        $this->expectRule(fn () => $this->reconciliation->undoReconciliation($this->project, $this->owner, $rec, 'Lại'), 'reconciliation');

        $history = $this->reconciliation->history($this->project, $this->bank);
        $this->assertCount(1, $history);
        $lines = collect(data_get($history[0], 'lines'));
        $this->assertSame(['Cả sao kê sai', 'Dòng sai'], $lines->pluck('undo_reason')->sort()->values()->all());
        $this->assertFalse((bool) data_get($history[0], 'active'));
    }

    public function test_undo_on_a_reversed_document_records_the_row_but_keeps_reversed(): void
    {
        $doc = $this->fund('100');
        $rec = $this->reconcile($this->bank, [$this->entryOf($doc, $this->bank)]);
        $reversal = $this->posting->reverse($this->project, $this->owner, $doc->fresh() ?? $doc, ['transaction_date' => '2026-10-03', 'description' => 'Nhập nhầm']);
        $this->assertSame(Document::STATUS_REVERSED, $doc->fresh()?->status);
        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, $reversal->status);
        $this->assertCount(1, $this->reconciliation->unreconciledEntries($this->bank));

        $apply = RecEntry::query()->where('reconciliation_id', $rec->id)->sole();
        $this->reconciliation->undoEntry($this->project, $this->owner, $apply, 'Gỡ');

        $this->assertSame(Document::STATUS_REVERSED, $doc->fresh()?->status);
        $this->assertSame(1, RecEntry::query()->where('direction', 'reverse')->count());
    }

    public function test_entries_of_a_reversed_document_can_still_be_reconciled_without_status_change(): void
    {
        $doc = $this->fund('100');
        $reversal = $this->posting->reverse($this->project, $this->owner, $doc, ['transaction_date' => '2026-10-03', 'description' => 'Nhập nhầm']);

        $this->reconcile($this->bank, [$this->entryOf($doc, $this->bank), $this->entryOf($reversal, $this->bank)]);

        $this->assertSame(Document::STATUS_REVERSED, $doc->fresh()?->status);
        $this->assertSame(Document::STATUS_POSTED_RECONCILED, $reversal->fresh()?->status);
        $this->assertSame(['balance' => '0.00', 'reconciled' => '0.00', 'unreconciled' => '0.00'], $this->reconciliation->walletSummary($this->bank));
    }

    public function test_undo_from_another_project_is_refused(): void
    {
        $doc = $this->fund('100');
        $rec = $this->reconcile($this->bank, [$this->entryOf($doc, $this->bank)]);
        $apply = RecEntry::query()->where('reconciliation_id', $rec->id)->sole();
        $otherProject = $this->projectFor($this->tenant);

        $this->expectRule(fn () => $this->reconciliation->undoEntry($otherProject, $this->owner, $apply, 'x'), 'reconciliation_entry');
        $this->expectRule(fn () => $this->reconciliation->undoReconciliation($otherProject, $this->owner, $rec, 'x'), 'reconciliation');
    }

    private function fund(string $amount, ?string $reference = 'A'): Document
    {
        return $this->posting->declareFunding($this->project, $this->owner, [
            'document_type' => 'funding', 'source_party_id' => (string) $this->investor->id,
            'destination_wallet_id' => (string) $this->bank->id, 'amount' => $amount,
            'transaction_date' => '2026-10-01', 'reference' => $reference,
        ], true);
    }

    private function entryOf(Document $doc, TreasuryWallet $wallet): string
    {
        return (string) Entry::query()
            ->where('source_financial_document_id', (string) $doc->id)
            ->where('wallet_id', (string) $wallet->id)
            ->value('id');
    }

    /**
     * @param list<string> $entryIds
     * @return array{reconciliation_type: string, external_reference: ?string, reconciled_at: string, ledger_entry_ids: list<string>}
     */
    private function input(array $entryIds, string $type = 'bank_statement', ?string $reference = 'SK-01', ?string $date = null): array
    {
        return [
            'reconciliation_type' => $type,
            'external_reference' => $reference,
            'reconciled_at' => $date ?? now()->subDay()->toDateString(),
            'ledger_entry_ids' => $entryIds,
        ];
    }

    /**
     * @param list<string> $entryIds
     */
    private function reconcile(TreasuryWallet $wallet, array $entryIds, string $type = 'bank_statement', ?string $reference = 'SK-01', ?string $date = null): \App\Models\Treasury\TreasuryReconciliation
    {
        return $this->reconciliation->reconcile($this->project, $this->owner, $wallet, $this->input($entryIds, $type, $reference, $date));
    }

    private function expectRule(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail('Expected a TreasuryRuleViolation on ' . $field);
        } catch (TreasuryRuleViolation $e) {
            $this->assertSame($field, $e->field, $e->getMessage());
        }
    }
}
