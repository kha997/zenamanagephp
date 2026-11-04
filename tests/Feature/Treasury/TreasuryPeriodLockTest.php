<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryLedgerEntry;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryAdvanceService;
use App\Services\Treasury\TreasuryExpenseService;
use App\Services\Treasury\TreasuryPeriodLockService;
use App\Services\Treasury\TreasuryPostingService;
use App\Services\Treasury\TreasuryReconciliationService;
use App\Services\Treasury\TreasuryRouteService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * GAP-107 (Gate 2 L-1, U-1, R-1) — per-project Treasury period lock: every posting dated on or before the lock is
 * refused, later-dated postings and reversals of old documents still work, reconciliation stays allowed, lock moves
 * forward only, unlock needs a reason, history and audit; only treasury.manage_period_lock holders manage it.
 */
class TreasuryPeriodLockTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;

    private Project $project;

    private User $owner;

    private User $accountant;

    private TreasuryWallet $bank;

    private TreasuryWallet $site;

    private TreasuryFinancialParty $investor;

    private TreasuryFinancialParty $holder;

    private ContractExpense $cost;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTreasuryPermissions();
        Carbon::setTestNow('2026-11-04 09:00:00');
        $this->tenant = Tenant::factory()->create();
        $this->project = $this->projectFor($this->tenant);
        $this->owner = $this->userWithRole($this->tenant, 'Admin');
        $this->accountant = $this->userWithRole($this->tenant, 'Finance');
        $this->headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $this->investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor']);
        $this->holder = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'employee', 'name' => 'Kỹ sư Z']);
        $this->bank = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Bank']);
        $this->site = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Quỹ công trường']);
        $this->posting()->declareFunding($this->project, $this->owner, $this->funding('100000', '2026-09-01', 'F-0'));
        $contract = Contract::factory()->create(['tenant_id' => $this->tenant->id, 'project_id' => $this->project->id, 'created_by' => $this->owner->id]);
        $this->cost = ContractExpense::query()->create([
            'tenant_id' => $this->tenant->id, 'contract_id' => $contract->id, 'expense_date' => '2026-09-01',
            'amount' => '100000', 'category' => 'material', 'description' => 'Vật tư',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_postings_on_or_before_the_lock_are_refused_and_later_ones_work(): void
    {
        $old = $this->posting()->declareFunding($this->project, $this->owner, $this->funding('500', '2026-10-15', 'F-OLD'));
        $this->locks()->lock($this->project, $this->owner, '2026-10-31', 'Chốt tháng 10');

        $this->assertLocked(fn () => $this->posting()->declareFunding($this->project, $this->owner, $this->funding('10', '2026-10-31', 'F-L')));
        $this->assertLocked(fn () => $this->posting()->transfer($this->project, $this->owner, [
            'source_wallet_id' => (string) $this->bank->id, 'destination_wallet_id' => (string) $this->site->id, 'amount' => '10', 'transaction_date' => '2026-10-20',
        ]));
        $this->assertLocked(fn () => $this->posting()->adjust($this->project, $this->owner, [
            'wallet_id' => (string) $this->bank->id, 'direction' => 'decrease', 'amount' => '10', 'transaction_date' => '2026-10-01', 'description' => 'Phí',
        ]));
        $this->assertLocked(fn () => $this->posting()->reverse($this->project, $this->owner, $old, ['transaction_date' => '2026-10-31', 'description' => 'Nhầm']));

        // Later-dated postings, and a reversal of an old document dated after the lock, still work.
        $this->posting()->declareFunding($this->project, $this->owner, $this->funding('20', '2026-11-01', 'F-NEW'));
        $reversal = $this->posting()->reverse($this->project, $this->owner, $old, ['transaction_date' => '2026-11-02', 'description' => 'Sửa sai tháng 10']);
        $this->assertSame(Document::TYPE_REVERSAL, $reversal->document_type);
    }

    public function test_expense_advance_return_and_route_leg_respect_the_lock(): void
    {
        $expenses = app(TreasuryExpenseService::class);
        $advances = app(TreasuryAdvanceService::class);
        $expense = $expenses->createDraft($this->project, $this->owner, [
            'source_wallet_id' => (string) $this->bank->id, 'destination_party_id' => (string) $this->holder->id, 'amount' => '300', 'transaction_date' => '2026-10-10',
            'allocations' => [['cost_source_type' => 'contract_expense', 'cost_source_id' => (string) $this->cost->id, 'amount' => '300']],
        ]);
        $expenses->submit($this->project, $this->owner, $expense);
        $advanceDraft = $advances->createDraft($this->project, $this->owner, [
            'source_wallet_id' => (string) $this->bank->id, 'destination_party_id' => (string) $this->holder->id, 'amount' => '400', 'transaction_date' => '2026-10-11',
        ]);
        $advances->submit($this->project, $this->owner, $advanceDraft);
        $route = app(TreasuryRouteService::class)->createRoute($this->project, $this->owner, $this->funding('700', '2026-10-12', 'VR-1', $this->site), true);

        $this->locks()->lock($this->project, $this->owner, '2026-10-31');

        $this->assertLocked(fn () => $expenses->approve($this->project, $this->accountant, $expense->fresh()));
        $this->assertLocked(fn () => $advances->approve($this->project, $this->accountant, $advanceDraft->fresh()));
        $this->assertLocked(fn () => app(TreasuryRouteService::class)->recordLeg($this->project, $this->owner, $route, [
            'from_wallet_id' => null, 'to_wallet_id' => (string) $this->site->id, 'amount' => '700', 'occurred_at' => '2026-10-20',
        ]));
        $this->assertSame(Document::STATUS_SUBMITTED, $expense->fresh()->status);

        $this->locks()->unlock($this->project, $this->owner, null, 'Ghi bổ sung tháng 10');
        $advance = $advances->approve($this->project, $this->accountant, $advanceDraft->fresh());
        $this->locks()->lock($this->project, $this->owner, '2026-10-31');
        $this->assertLocked(fn () => $advances->recordReturn($this->project, $this->accountant, $advance, [
            'destination_wallet_id' => (string) $this->bank->id, 'amount' => '100', 'transaction_date' => '2026-10-30',
        ]));
        $advances->recordReturn($this->project, $this->accountant, $advance, [
            'destination_wallet_id' => (string) $this->bank->id, 'amount' => '100', 'transaction_date' => '2026-11-03',
        ]);
    }

    public function test_reconciliation_stays_allowed_in_a_locked_period(): void
    {
        $this->locks()->lock($this->project, $this->owner, '2026-10-31');
        $entry = TreasuryLedgerEntry::query()->where('wallet_id', $this->bank->id)->firstOrFail();

        $reconciliation = app(TreasuryReconciliationService::class)->reconcile($this->project, $this->accountant, $this->bank, [
            'reconciliation_type' => 'bank_statement', 'external_reference' => 'SK-09', 'reconciled_at' => '2026-11-04', 'ledger_entry_ids' => [(string) $entry->id],
        ]);

        $this->assertNotNull($reconciliation->id);
    }

    public function test_lock_moves_forward_only_unlock_needs_reason_and_history_is_audited(): void
    {
        $locks = $this->locks();
        $locks->lock($this->project, $this->owner, '2026-09-30');
        $this->assertRule(fn () => $locks->lock($this->project, $this->owner, '2026-09-15'), 'chỉ tiến lên');
        $this->assertRule(fn () => $locks->lock($this->project, $this->owner, '2026-12-01'), 'tương lai');
        $locks->lock($this->project, $this->owner, '2026-10-31');
        $this->assertSame('2026-10-31', $locks->lockedThrough($this->project)?->toDateString());

        $this->assertRule(fn () => $locks->unlock($this->project, $this->owner, '2026-09-30', ''), 'lý do');
        $this->assertRule(fn () => $locks->unlock($this->project, $this->owner, '2026-11-01', 'x'), 'sớm hơn');
        $locks->unlock($this->project, $this->owner, '2026-09-30', 'Ghi bổ sung tháng 10');
        $this->assertSame('2026-09-30', $locks->lockedThrough($this->project)?->toDateString());
        $locks->unlock($this->project, $this->owner, null, 'Bỏ khoá');
        $this->assertNull($locks->lockedThrough($this->project));

        $this->assertCount(4, $locks->history($this->project));
        $this->assertSame(2, AuditLog::query()->where('action', 'treasury.period.locked')->count());
        $this->assertSame(2, AuditLog::query()->where('action', 'treasury.period.unlocked')->count());
        $this->assertRule(fn () => $locks->lock($this->project, $this->accountant, '2026-10-31'), 'không có quyền');
    }

    public function test_lock_is_per_project(): void
    {
        $other = $this->projectFor($this->tenant);
        $otherWallet = TreasuryWallet::factory()->create(['project_id' => $other->id]);
        $this->locks()->lock($this->project, $this->owner, '2026-10-31');

        $doc = $this->posting()->declareFunding($other, $this->owner, [
            'document_type' => 'funding', 'source_party_id' => (string) $this->investor->id,
            'destination_wallet_id' => (string) $otherWallet->id, 'amount' => '5', 'transaction_date' => '2026-10-10', 'reference' => 'F-OTHER',
        ]);
        $this->assertNotNull($doc->id);
    }

    public function test_project_page_panel_and_routes(): void
    {
        $page = route('operator.treasury.projects.show', $this->project->id);
        $this->actingAs($this->owner)->get($page, $this->headers)->assertOk()
            ->assertSee('treasury-period-open', false)->assertSee('treasury-period-lock-form', false);
        $this->actingAs($this->owner)->post(route('operator.treasury.projects.period.lock', $this->project->id), ['locked_through' => '2026-10-31', 'reason' => 'Chốt'], $this->headers)
            ->assertRedirect($page);
        $this->actingAs($this->owner)->get($page, $this->headers)->assertOk()->assertSee('31/10/2026')->assertSee('treasury-period-unlock-form', false);

        $this->actingAs($this->accountant)->get($page, $this->headers)->assertOk()
            ->assertSee('treasury-period-locked', false)->assertDontSee('treasury-period-lock-form', false);
        $this->actingAs($this->accountant)->post(route('operator.treasury.projects.period.unlock', $this->project->id), ['reason' => 'x'], $this->headers);
        $this->assertSame('2026-10-31', $this->locks()->lockedThrough($this->project)?->toDateString());
    }

    private function locks(): TreasuryPeriodLockService
    {
        return app(TreasuryPeriodLockService::class);
    }

    private function posting(): TreasuryPostingService
    {
        return app(TreasuryPostingService::class);
    }

    /**
     * @return array<string, string>
     */
    private function funding(string $amount, string $date, string $reference, ?TreasuryWallet $wallet = null): array
    {
        return [
            'document_type' => 'funding', 'source_party_id' => (string) $this->investor->id,
            'destination_wallet_id' => (string) ($wallet ?? $this->bank)->id, 'amount' => $amount, 'transaction_date' => $date, 'reference' => $reference,
        ];
    }

    private function assertLocked(callable $call): void
    {
        $this->assertRule($call, 'đã khoá');
    }

    private function assertRule(callable $call, string $messagePart): void
    {
        try {
            $call();
            $this->fail('Expected TreasuryRuleViolation');
        } catch (TreasuryRuleViolation $e) {
            $this->assertStringContainsString($messagePart, $e->getMessage());
        }
    }
}
