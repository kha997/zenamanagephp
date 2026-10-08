<?php declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryLedgerEntry as Entry;
use App\Models\Treasury\TreasuryReconciliation;
use App\Models\Treasury\TreasuryReconciliationEntry as RecEntry;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryPostingService;
use App\Services\Treasury\TreasuryReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP-068 — reconciliation responses never depend on the history window, and
 * the history is paged (API page/per_page, 50 per page on the web).
 */
class TreasuryReconciliationHistoryPagingTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;
    private Project $project;
    private User $owner;
    private TreasuryWallet $wallet;
    private TreasuryReconciliationService $reconciliation;

    /** @var list<string> */
    private array $entryIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $this->seedTreasuryPermissions();
        $this->tenant = Tenant::factory()->create();
        $this->project = $this->projectFor($this->tenant);
        $this->owner = $this->userWithRole($this->tenant, 'Admin');
        $this->wallet = TreasuryWallet::factory()->create(['project_id' => $this->project->id]);
        $this->reconciliation = app(TreasuryReconciliationService::class);
        $investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor']);
        $posting = app(TreasuryPostingService::class);
        for ($i = 0; $i < 103; $i++) {
            $doc = $posting->declareFunding($this->project, $this->owner, [
                'document_type' => 'funding', 'source_party_id' => (string) $investor->id,
                'destination_wallet_id' => (string) $this->wallet->id, 'amount' => '1',
                'transaction_date' => '2026-10-01', 'reference' => 'R' . $i,
            ], true);
            $this->entryIds[] = (string) Entry::query()->where('source_financial_document_id', (string) $doc->id)->value('id');
        }
    }

    public function test_backdated_reconciliation_beyond_the_newest_100_is_returned(): void
    {
        $this->reconcileNewest(100);

        $this->postJson($this->api('store'), [
            'reconciliation_type' => 'cash_count',
            'reconciled_at' => now()->subDays(5)->toDateString(),
            'ledger_entry_ids' => [$this->entryIds[101]],
        ], $this->headers())
            ->assertStatus(201)
            ->assertJsonPath('data.lines.0.ledger_entry_id', $this->entryIds[101])
            ->assertJsonPath('data.reconciled_at', now()->subDays(5)->toDateString());
    }

    public function test_undoing_an_old_reconciliation_or_line_returns_it(): void
    {
        $old = $this->oldReconciliations(2);
        $this->reconcileNewest(100);

        $this->postJson($this->api('undo', ['treasuryReconciliation' => $old[0]]), ['reason' => 'Sai sao kê'], $this->headers())
            ->assertOk()->assertJsonPath('data.id', $old[0])->assertJsonPath('data.active', false);

        $lineId = (string) RecEntry::query()->where('reconciliation_id', $old[1])->value('id');
        $this->postJson($this->api('entries.undo', ['treasuryReconciliationEntry' => $lineId]), ['reason' => 'Gỡ dòng'], $this->headers())
            ->assertOk()->assertJsonPath('data.id', $old[1])->assertJsonPath('data.lines.0.undo_reason', 'Gỡ dòng');
    }

    public function test_api_history_is_paged_and_validated(): void
    {
        $old = $this->oldReconciliations(1);
        $this->reconcileNewest(100);

        $this->getJson($this->api('index'), $this->headers())->assertOk()->assertJsonCount(100, 'data');
        $this->getJson($this->api('index') . '?page=2', $this->headers())
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $old[0]);
        $this->getJson($this->api('index') . '?per_page=40&page=3', $this->headers())->assertOk()->assertJsonCount(21, 'data');

        foreach (['page=0', 'per_page=0', 'per_page=101', 'page=x'] as $query) {
            $this->getJson($this->api('index') . '?' . $query, $this->headers())->assertStatus(422);
        }
    }

    public function test_web_history_has_pages_and_old_reconciliations_can_be_undone(): void
    {
        $old = $this->oldReconciliations(1);
        $this->reconcileNewest(50);
        $page = route('operator.treasury.projects.wallets.reconcile', ['project' => (string) $this->project->id, 'wallet' => (string) $this->wallet->id], false);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $first = $this->actingAs($this->owner)->get($page, $headers)->assertOk();
        $first->assertSee('Trang sau')->assertDontSee('Trang trước');
        $this->assertSame(50, substr_count((string) $first->getContent(), 'data-testid="treasury-reconciliation-history"'));
        $first->assertDontSee('/reconciliations/' . $old[0] . '/undo', false);

        $second = $this->actingAs($this->owner)->get($page . '?page=2', $headers)->assertOk();
        $second->assertSee('Trang trước')->assertDontSee('Trang sau')
            ->assertSee('/reconciliations/' . $old[0] . '/undo', false);

        $this->actingAs($this->owner)->post(route('operator.treasury.projects.reconciliations.undo', [
            'project' => (string) $this->project->id, 'treasuryReconciliation' => $old[0],
        ], false), ['reason' => 'Đếm lại'], $headers)->assertRedirect()->assertSessionHas('success');
        $this->assertSame(1, RecEntry::query()->where('reconciliation_id', $old[0])->where('direction', 'reverse')->count());
    }

    /**
     * Reconciles entries from the end of the list, dated today (newest).
     */
    private function reconcileNewest(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->reconciliation->reconcile($this->project, $this->owner, $this->wallet, [
                'reconciliation_type' => 'cash_count',
                'reconciled_at' => now()->toDateString(),
                'ledger_entry_ids' => [$this->entryIds[$i]],
            ]);
        }
    }

    /**
     * Reconciliations dated 10 days ago, on entries never used by reconcileNewest().
     *
     * @return list<string>
     */
    private function oldReconciliations(int $count): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = (string) $this->reconciliation->reconcile($this->project, $this->owner, $this->wallet, [
                'reconciliation_type' => 'cash_count',
                'reconciled_at' => now()->subDays(10)->toDateString(),
                'ledger_entry_ids' => [$this->entryIds[102 - $i]],
            ])->id;
        }
        $this->assertSame($count, TreasuryReconciliation::query()->count());

        return $ids;
    }

    /**
     * @param array<string, string> $extra
     */
    private function api(string $name, array $extra = []): string
    {
        $params = ['project' => (string) $this->project->id] + $extra;
        if ($name === 'store') {
            $params['wallet'] = (string) $this->wallet->id;
        }

        return route('api.zena.treasury.reconciliation.' . $name, $params, false);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $this->tenant->id,
            'Authorization' => 'Bearer ' . $this->owner->createToken('gap-068')->plainTextToken,
        ];
    }
}
