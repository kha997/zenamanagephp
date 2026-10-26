<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Models\AuditLog;
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
 * GAP-094 S4b-1 — via-route payments (GAP-037 v17 §4, §5a, §12.1 + approved Gate 2 Option A).
 */
class TreasuryRouteServiceTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;

    private Project $project;

    private User $owner;

    private TreasuryRouteService $routes;

    private TreasuryPostingService $posting;

    private TreasuryBalanceService $balances;

    private TreasuryFinancialParty $investor;

    private TreasuryWallet $bank;

    private TreasuryWallet $transit;

    private TreasuryWallet $siteCash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTreasuryPermissions();
        $this->tenant = Tenant::factory()->create();
        $this->project = $this->projectFor($this->tenant);
        $this->owner = $this->userWithRole($this->tenant, 'Admin');
        $this->routes = app(TreasuryRouteService::class);
        $this->posting = app(TreasuryPostingService::class);
        $this->balances = app(TreasuryBalanceService::class);
        $this->investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor']);
        $this->bank = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Bank']);
        $this->transit = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'TK công ty – trung chuyển', 'wallet_type' => 'intermediary_control']);
        $this->siteCash = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Site cash', 'wallet_type' => 'employee_cash']);
    }

    public function test_funding_route_moves_through_a_transit_wallet_and_posts_at_completion(): void
    {
        $route = $this->fundingRoute('100000000');
        $document = $this->documentOf($route);
        $this->assertSame(Document::STATUS_APPROVED, $document->status);
        $this->assertSame(Document::POSTING_PATH_VIA_ROUTE, $document->posting_path);
        $this->assertNull($document->posted_at);
        $this->assertSame(Route::STATUS_PLANNED, $route->status);
        $this->assertSame('100000000.00', (string) $route->total_allocated_amount);

        $this->leg($route, null, $this->transit, '100000000');
        $this->assertSame(Route::STATUS_PARTIAL, $route->fresh()?->status);
        $this->assertSame('100000000.00', $this->balances->walletBalance($this->transit));
        $summary = $this->balances->projectSummary($this->project);
        $this->assertSame('0.00', $summary['investor_funding'], 'Money on its way is not yet received.');
        $this->assertSame('100000000.00', $summary['held_total']);

        $this->leg($route, $this->transit, $this->siteCash, '60000000');
        $this->leg($route, $this->transit, $this->siteCash, '40000000');

        $this->assertSame(Route::STATUS_COMPLETED, $route->fresh()?->status);
        $document = $this->documentOf($route);
        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, $document->status);
        $this->assertNotNull($document->posted_at);
        $this->assertSame('0.00', $this->balances->walletBalance($this->transit));
        $this->assertSame('100000000.00', $this->balances->walletBalance($this->siteCash));
        $this->assertSame('100000000.00', $this->balances->projectSummary($this->project)['investor_funding']);
        $this->assertSame(5, Entry::query()->whereNotNull('source_payment_route_leg_id')->count());
        $this->assertSame(0, Entry::query()->where('source_financial_document_id', $document->id)->count());
        $this->assertSame([1, 2, 3], Leg::query()->orderBy('sequence_no')->pluck('sequence_no')->all());
        $this->assertTrue(AuditLog::query()->where('action', 'treasury.route.leg_recorded')->where('entity_id', $route->id)->exists());
    }

    public function test_transfer_route_completes_when_the_source_has_given_the_full_amount(): void
    {
        $this->posting->declareFunding($this->project, $this->owner, [
            'document_type' => Document::TYPE_FUNDING, 'source_party_id' => (string) $this->investor->id,
            'destination_wallet_id' => (string) $this->bank->id, 'amount' => '30000000', 'transaction_date' => '2026-10-01',
        ]);
        $route = $this->routes->createRoute($this->project, $this->owner, [
            'document_type' => Document::TYPE_INTERNAL_TRANSFER,
            'source_wallet_id' => (string) $this->bank->id,
            'destination_wallet_id' => (string) $this->siteCash->id,
            'amount' => '20000000',
            'transaction_date' => '2026-10-02',
        ]);

        $this->expectRule(fn () => $this->leg($route, null, $this->transit, '1'), 'from_wallet_id');
        $this->leg($route, $this->bank, $this->transit, '20000000');
        $this->expectRule(fn () => $this->leg($route, $this->bank, $this->transit, '1'), 'amount');
        $this->leg($route, $this->transit, $this->siteCash, '20000000');

        $this->assertSame(Route::STATUS_COMPLETED, $route->fresh()?->status);
        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, $this->documentOf($route)->status);
        $this->assertSame('10000000.00', $this->balances->walletBalance($this->bank));
        $this->assertSame('20000000.00', $this->balances->walletBalance($this->siteCash));
        $this->assertSame('30000000.00', $this->balances->projectSummary($this->project)['held_total']);
    }

    public function test_only_funding_contribution_and_transfer_go_via_route(): void
    {
        foreach ([Document::TYPE_EXPENSE, Document::TYPE_ADJUSTMENT, Document::TYPE_ADVANCE, Document::TYPE_ADVANCE_RETURN] as $type) {
            $this->expectRule(fn () => $this->routes->createRoute($this->project, $this->owner, [
                'document_type' => $type, 'source_party_id' => (string) $this->investor->id,
                'destination_wallet_id' => (string) $this->siteCash->id, 'amount' => '100', 'transaction_date' => '2026-10-01',
            ]), 'document_type');
        }
        $this->expectRule(fn () => $this->routes->createRoute($this->project, $this->owner, [
            'document_type' => Document::TYPE_OWNER_CONTRIBUTION, 'source_party_id' => (string) $this->investor->id,
            'destination_wallet_id' => (string) $this->siteCash->id, 'amount' => '100', 'transaction_date' => '2026-10-01',
        ]), 'source_party_id');
        $this->assertSame(0, Route::query()->count());
        $this->assertSame(0, Document::query()->count());
    }

    public function test_money_from_outside_never_exceeds_the_route_amount(): void
    {
        $route = $this->fundingRoute('1000');
        $this->leg($route, null, $this->transit, '600');
        $this->expectRule(fn () => $this->leg($route, null, $this->transit, '400.01'), 'amount');
        $this->leg($route, null, $this->transit, '400');
        $this->assertSame('1000.00', $this->balances->walletBalance($this->transit));
    }

    public function test_a_leg_cannot_overdraw_its_from_wallet(): void
    {
        $route = $this->fundingRoute('1000');
        $this->leg($route, null, $this->transit, '500');
        $this->expectRule(fn () => $this->leg($route, $this->transit, $this->siteCash, '500.01'), 'amount');
        $this->assertSame(1, Leg::query()->count());
    }

    public function test_no_leg_on_a_completed_or_cancelled_route_and_no_foreign_wallets(): void
    {
        $route = $this->fundingRoute('1000');
        $this->leg($route, null, $this->siteCash, '1000');
        $this->assertSame(Route::STATUS_COMPLETED, $route->fresh()?->status);
        $this->expectRule(fn () => $this->leg($route, $this->siteCash, $this->transit, '1'), 'route');

        $other = TreasuryWallet::factory()->create(['project_id' => $this->projectFor($this->tenant)->id]);
        $second = $this->fundingRoute('500', reference: 'UNC-2');
        $this->expectRule(fn () => $this->leg($second, null, $other, '1'), 'to_wallet_id');
        $this->expectRule(fn () => $this->leg($second, $this->transit, $this->transit, '1'), 'to_wallet_id');
        $this->expectRule(fn () => $this->routes->recordLeg($this->project, $this->owner, $second, [
            'to_wallet_id' => (string) $this->transit->id, 'amount' => '1', 'occurred_at' => now()->addDay()->toDateString(),
        ]), 'occurred_at');
    }

    public function test_reversing_a_leg_couples_its_entries_and_reopens_a_completed_route(): void
    {
        $route = $this->fundingRoute('1000');
        $this->leg($route, null, $this->transit, '1000');
        $wrong = $this->leg($route, $this->transit, $this->bank, '1000');
        $this->assertSame(Route::STATUS_PARTIAL, $route->fresh()?->status, 'Bank is not the destination.');

        $this->expectRule(fn () => $this->routes->reverseLeg($this->project, $this->owner, $wrong, '  '), 'reason');
        $this->routes->reverseLeg($this->project, $this->owner, $wrong, 'Ghi nhầm ví');

        $this->assertSame(Leg::STATUS_REVERSED, $wrong->fresh()?->status);
        $this->assertSame(2, Entry::query()->where('source_payment_route_leg_id', $wrong->id)->whereNotNull('reversal_of_entry_id')->count());
        $this->assertSame('1000.00', $this->balances->walletBalance($this->transit));
        $this->assertSame('0.00', $this->balances->walletBalance($this->bank));
        $this->expectRule(fn () => $this->routes->reverseLeg($this->project, $this->owner, $wrong, 'again'), 'leg');

        $last = $this->leg($route, $this->transit, $this->siteCash, '1000');
        $this->assertSame(Route::STATUS_COMPLETED, $route->fresh()?->status);
        $this->routes->reverseLeg($this->project, $this->owner, $last, 'Người mang chưa giao');
        $this->assertSame(Route::STATUS_PARTIAL, $route->fresh()?->status);
        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, $this->documentOf($route)->status, 'A posted document stays posted.');
    }

    public function test_leg_reversal_needs_the_to_wallet_to_still_hold_the_money(): void
    {
        $route = $this->fundingRoute('1000');
        $first = $this->leg($route, null, $this->transit, '1000');
        $this->leg($route, $this->transit, $this->siteCash, '700');

        $this->expectRule(fn () => $this->routes->reverseLeg($this->project, $this->owner, $first, 'sai'), 'leg');
        $this->assertSame(Leg::STATUS_SETTLED, $first->fresh()?->status);
    }

    public function test_cancel_only_without_legs_in_force_and_before_posting(): void
    {
        $route = $this->fundingRoute('1000');
        $leg = $this->leg($route, null, $this->transit, '300');
        $this->expectRule(fn () => $this->routes->cancelRoute($this->project, $this->owner, $route, 'bỏ'), 'route');
        $this->routes->reverseLeg($this->project, $this->owner, $leg, 'nhầm');
        $this->assertSame(Route::STATUS_PLANNED, $route->fresh()?->status);

        $this->routes->cancelRoute($this->project, $this->owner, $route, 'Nhà đầu tư không chuyển nữa');
        $this->assertSame(Route::STATUS_CANCELLED, $route->fresh()?->status);
        $this->assertSame(Document::STATUS_REJECTED, $this->documentOf($route)->status);
        $this->assertSame('0.00', $this->balances->walletBalance($this->transit));
        $this->expectRule(fn () => $this->leg($route, null, $this->transit, '1'), 'route');

        $completed = $this->fundingRoute('200', reference: 'UNC-9');
        $this->leg($completed, null, $this->siteCash, '200');
        $this->expectRule(fn () => $this->routes->cancelRoute($this->project, $this->owner, $completed, 'bỏ'), 'route');
    }

    public function test_duplicate_warning_also_sees_a_route_on_its_way(): void
    {
        $this->fundingRoute('5000');

        $this->expectException(TreasuryDuplicateSuspected::class);
        $this->posting->declareFunding($this->project, $this->owner, [
            'document_type' => Document::TYPE_FUNDING, 'source_party_id' => (string) $this->investor->id,
            'destination_wallet_id' => (string) $this->siteCash->id, 'amount' => '5000', 'transaction_date' => '2026-10-01',
            'reference' => 'UNC-1',
        ]);
    }

    public function test_accountant_creates_routes_and_engineer_records_only_legs_on_wallets_they_hold(): void
    {
        $accountant = $this->userWithRole($this->tenant, 'Finance');
        $route = $this->routes->createRoute($this->project, $accountant, [
            'document_type' => Document::TYPE_FUNDING, 'source_party_id' => (string) $this->investor->id,
            'destination_wallet_id' => (string) $this->siteCash->id, 'amount' => '1000', 'transaction_date' => '2026-10-01',
        ]);
        $this->routes->recordLeg($this->project, $accountant, $route, [
            'to_wallet_id' => (string) $this->transit->id, 'amount' => '1000', 'occurred_at' => '2026-10-01',
        ]);

        $engineer = $this->userWithRole($this->tenant, 'site_engineer');
        $this->addMember($this->project, $engineer);
        $this->assertFalse($this->gate($engineer, 'treasury.manage-routes'));
        $this->expectRule(fn () => $this->routes->recordLeg($this->project, $engineer, $route, [
            'from_wallet_id' => (string) $this->transit->id, 'to_wallet_id' => (string) $this->siteCash->id,
            'amount' => '1000', 'occurred_at' => '2026-10-02',
        ]), 'from_wallet_id');

        $custodian = TreasuryFinancialParty::factory()->create([
            'tenant_id' => $this->tenant->id, 'party_type' => 'employee', 'linked_user_id' => $engineer->id,
        ]);
        $this->siteCash->update(['custodian_party_id' => $custodian->id]);
        $this->routes->recordLeg($this->project, $engineer, $route, [
            'from_wallet_id' => (string) $this->transit->id, 'to_wallet_id' => (string) $this->siteCash->id,
            'amount' => '1000', 'occurred_at' => '2026-10-02',
        ]);
        $this->assertSame(Route::STATUS_COMPLETED, $route->fresh()?->status);
    }

    public function test_reversing_a_completed_via_route_document_posts_directly(): void
    {
        $route = $this->fundingRoute('1000');
        $this->leg($route, null, $this->transit, '1000');
        $this->expectRule(fn () => $this->posting->reverse($this->project, $this->owner, $this->documentOf($route), [
            'transaction_date' => '2026-10-03', 'description' => 'sai',
        ]));

        $this->leg($route, $this->transit, $this->siteCash, '1000');
        $reversal = $this->posting->reverse($this->project, $this->owner, $this->documentOf($route), [
            'transaction_date' => '2026-10-03', 'description' => 'Nhà đầu tư rút lại',
        ]);

        $this->assertSame(Document::POSTING_PATH_DIRECT, $reversal->posting_path);
        $this->assertSame(Document::STATUS_REVERSED, $this->documentOf($route)->status);
        $this->assertSame('0.00', $this->balances->walletBalance($this->siteCash));
        $this->assertSame((string) $this->investor->id, (string) $reversal->destination_party_id);
        $this->expectRule(fn () => $this->routes->reverseLeg($this->project, $this->owner, Leg::query()->orderByDesc('sequence_no')->firstOrFail(), 'sau khi đảo'), 'leg');
    }

    public function test_reversed_transfer_route_credits_the_source_back(): void
    {
        $this->posting->declareFunding($this->project, $this->owner, [
            'document_type' => Document::TYPE_FUNDING, 'source_party_id' => (string) $this->investor->id,
            'destination_wallet_id' => (string) $this->bank->id, 'amount' => '500', 'transaction_date' => '2026-10-01',
        ]);
        $route = $this->routes->createRoute($this->project, $this->owner, [
            'document_type' => Document::TYPE_INTERNAL_TRANSFER, 'source_wallet_id' => (string) $this->bank->id,
            'destination_wallet_id' => (string) $this->siteCash->id, 'amount' => '500', 'transaction_date' => '2026-10-02',
        ]);
        $this->leg($route, $this->bank, $this->siteCash, '500');

        $this->posting->reverse($this->project, $this->owner, $this->documentOf($route), [
            'transaction_date' => '2026-10-03', 'description' => 'chuyển nhầm',
        ]);
        $this->assertSame('500.00', $this->balances->walletBalance($this->bank));
        $this->assertSame('0.00', $this->balances->walletBalance($this->siteCash));
    }

    public function test_via_route_document_is_reconciled_when_completed_and_every_leg_entry_applied(): void
    {
        $reconciliation = app(TreasuryReconciliationService::class);
        $route = $this->fundingRoute('1000');
        $this->leg($route, null, $this->transit, '1000');
        $this->leg($route, $this->transit, $this->siteCash, '1000');

        $transitEntries = Entry::query()->where('wallet_id', $this->transit->id)->pluck('id')->map(static fn ($id): string => (string) $id)->all();
        $siteEntries = Entry::query()->where('wallet_id', $this->siteCash->id)->pluck('id')->map(static fn ($id): string => (string) $id)->all();
        $unreconciled = $reconciliation->unreconciledEntries($this->transit);
        $this->assertCount(2, $unreconciled);
        $this->assertSame((string) $this->documentOf($route)->id, $unreconciled[0]['document_id']);

        $reconciliation->reconcile($this->project, $this->owner, $this->transit, [
            'reconciliation_type' => 'bank_statement', 'external_reference' => 'SK-1', 'reconciled_at' => '2026-10-03',
            'ledger_entry_ids' => $transitEntries,
        ]);
        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, $this->documentOf($route)->status);

        $rec = $reconciliation->reconcile($this->project, $this->owner, $this->siteCash, [
            'reconciliation_type' => 'cash_count', 'reconciled_at' => '2026-10-03', 'ledger_entry_ids' => $siteEntries,
        ]);
        $this->assertSame(Document::STATUS_POSTED_RECONCILED, $this->documentOf($route)->status);
        $this->assertSame((string) $this->documentOf($route)->id, $reconciliation->historyItem($this->project, (string) $rec->id)['lines'][0]['document_id']);

        $reconciliation->undoReconciliation($this->project, $this->owner, $rec, 'đếm lại');
        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, $this->documentOf($route)->status);
    }

    public function test_leg_reversal_regresses_a_reconciled_document(): void
    {
        $reconciliation = app(TreasuryReconciliationService::class);
        $route = $this->fundingRoute('1000');
        $leg = $this->leg($route, null, $this->siteCash, '1000');
        $reconciliation->reconcile($this->project, $this->owner, $this->siteCash, [
            'reconciliation_type' => 'cash_count', 'reconciled_at' => '2026-10-03',
            'ledger_entry_ids' => Entry::query()->pluck('id')->map(static fn ($id): string => (string) $id)->all(),
        ]);
        $this->assertSame(Document::STATUS_POSTED_RECONCILED, $this->documentOf($route)->status);

        $this->routes->reverseLeg($this->project, $this->owner, $leg, 'đếm thiếu');
        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, $this->documentOf($route)->status);
    }

    public function test_detail_reports_legs_and_net_per_wallet(): void
    {
        $route = $this->fundingRoute('1000');
        $this->leg($route, null, $this->transit, '1000');
        $this->leg($route, $this->transit, $this->siteCash, '400');

        $detail = $this->routes->detail($route->fresh() ?? $route);
        $this->assertSame('400.00', $detail['arrived_amount']);
        $this->assertSame('600.00', $detail['remaining_amount']);
        $this->assertCount(2, $detail['legs']);
        $this->assertNull($detail['legs'][0]['from_wallet_name']);
        $net = array_column($detail['net_by_wallet'], 'net', 'wallet_name');
        $this->assertSame('600.00', $net['TK công ty – trung chuyển']);
        $this->assertSame('400.00', $net['Site cash']);
        $this->assertSame(Route::STATUS_PARTIAL, $this->routes->routes($this->project)[0]['status']);
    }

    private function fundingRoute(string $amount, string $reference = 'UNC-1'): Route
    {
        return $this->routes->createRoute($this->project, $this->owner, [
            'document_type' => Document::TYPE_FUNDING,
            'source_party_id' => (string) $this->investor->id,
            'destination_wallet_id' => (string) $this->siteCash->id,
            'amount' => $amount,
            'transaction_date' => '2026-10-01',
            'reference' => $reference,
        ]);
    }

    private function leg(Route $route, ?TreasuryWallet $from, TreasuryWallet $to, string $amount): Leg
    {
        return $this->routes->recordLeg($this->project, $this->owner, $route, [
            'from_wallet_id' => $from === null ? null : (string) $from->id,
            'to_wallet_id' => (string) $to->id,
            'amount' => $amount,
            'occurred_at' => '2026-10-02',
        ]);
    }

    private function documentOf(Route $route): Document
    {
        return Document::query()->findOrFail($route->linked_financial_document_id);
    }

    private function gate(User $user, string $ability): bool
    {
        return \Illuminate\Support\Facades\Gate::forUser($user)->allows($ability, $this->project);
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
