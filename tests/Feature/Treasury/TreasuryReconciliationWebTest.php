<?php declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryLedgerEntry as Entry;
use App\Models\Treasury\TreasuryReconciliationEntry as RecEntry;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP-067 S4a — reconciliation page and project-page additions.
 */
class TreasuryReconciliationWebTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;
    private Project $project;
    private User $owner;
    private User $engineer;
    private TreasuryWallet $bank;
    private Document $funding;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $this->seedTreasuryPermissions();
        $this->tenant = Tenant::factory()->create();
        $this->headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $this->project = $this->projectFor($this->tenant);
        $this->owner = $this->userWithRole($this->tenant, 'Admin');
        $this->engineer = $this->userWithRole($this->tenant, 'site_engineer');
        $this->addMember($this->project, $this->engineer);
        $investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor']);
        $this->bank = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'TK công ty']);
        $this->funding = app(TreasuryPostingService::class)->declareFunding($this->project, $this->owner, [
            'document_type' => 'funding', 'source_party_id' => (string) $investor->id,
            'destination_wallet_id' => (string) $this->bank->id, 'amount' => '3000000', 'transaction_date' => '2026-10-01',
            'reference' => 'UNC-77',
        ]);
    }

    public function test_owner_reconciles_from_the_page_then_undoes_the_line_with_a_reason(): void
    {
        $page = $this->reconcilePage();
        $this->actingAs($this->owner)->get($page, $this->headers)->assertOk()
            ->assertSee('treasury-reconcile-form', false)
            ->assertSee('UNC-77');

        $this->actingAs($this->owner)->post($this->url('operator.treasury.projects.wallets.reconcile.store', ['wallet' => (string) $this->bank->id]), [
            'reconciliation_type' => 'bank_statement', 'external_reference' => 'SK-T10',
            'reconciled_at' => now()->toDateString(), 'ledger_entry_ids' => [$this->entryId()],
        ], $this->headers)->assertRedirect($page)->assertSessionHas('success');
        $this->assertSame(Document::STATUS_POSTED_RECONCILED, $this->funding->fresh()?->status);

        $this->actingAs($this->owner)->get($page, $this->headers)->assertOk()
            ->assertSee('Không còn giao dịch chưa đối soát')
            ->assertSee('SK-T10')
            ->assertSee('Đang hiệu lực');

        $line = RecEntry::query()->sole();
        $this->actingAs($this->owner)->from($page)->post($this->url('operator.treasury.projects.reconciliation-entries.undo', ['treasuryReconciliationEntry' => (string) $line->id]), [], $this->headers)
            ->assertRedirect($page)->assertSessionHasErrors('reason');
        $this->actingAs($this->owner)->post($this->url('operator.treasury.projects.reconciliation-entries.undo', ['treasuryReconciliationEntry' => (string) $line->id]), ['reason' => 'Nhầm sao kê'], $this->headers)
            ->assertRedirect($page)->assertSessionHas('success');

        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, $this->funding->fresh()?->status);
        $this->actingAs($this->owner)->get($page, $this->headers)->assertOk()->assertSee('Nhầm sao kê');
    }

    public function test_undo_whole_reconciliation_and_reference_errors_return_to_the_form(): void
    {
        $page = $this->reconcilePage();
        $this->actingAs($this->owner)->get($page, $this->headers);
        $store = $this->url('operator.treasury.projects.wallets.reconcile.store', ['wallet' => (string) $this->bank->id]);

        $this->actingAs($this->owner)->from($page)->post($store, [
            'reconciliation_type' => 'voucher', 'external_reference' => '',
            'reconciled_at' => now()->toDateString(), 'ledger_entry_ids' => [$this->entryId()],
        ], $this->headers)->assertRedirect($page)->assertSessionHasErrors('external_reference');
        $this->actingAs($this->owner)->from($page)->post($store, [
            'reconciliation_type' => 'cash_count', 'reconciled_at' => now()->toDateString(),
        ], $this->headers)->assertRedirect($page)->assertSessionHasErrors('ledger_entry_ids');

        $this->actingAs($this->owner)->post($store, [
            'reconciliation_type' => 'cash_count', 'reconciled_at' => now()->toDateString(), 'ledger_entry_ids' => [$this->entryId()],
        ], $this->headers)->assertRedirect($page);
        $recId = (string) RecEntry::query()->sole()->reconciliation_id;

        $this->actingAs($this->owner)->post($this->url('operator.treasury.projects.reconciliations.undo', ['treasuryReconciliation' => $recId]), ['reason' => 'Đếm lại quỹ'], $this->headers)
            ->assertRedirect($page)->assertSessionHas('success');
        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, $this->funding->fresh()?->status);
        $this->assertSame(2, RecEntry::query()->count());
    }

    public function test_project_page_shows_split_balances_link_and_status_filter(): void
    {
        $show = $this->url('operator.treasury.projects.show');
        $this->actingAs($this->engineer)->get($show, $this->headers)->assertOk()
            ->assertSee('treasury-wallet-reconcile-link', false)
            ->assertSee('treasury-register-filter', false)
            ->assertSee('3.000.000 ₫');

        $this->actingAs($this->engineer)->get($show . '?status=posted_reconciled', $this->headers)->assertOk()
            ->assertDontSee('treasury-document-row', false);
        $this->actingAs($this->engineer)->get($show . '?status=posted_unreconciled', $this->headers)->assertOk()
            ->assertSee('treasury-document-row', false);
    }

    public function test_engineer_sees_the_page_read_only_and_cannot_post(): void
    {
        $page = $this->reconcilePage();
        $this->actingAs($this->engineer)->get($page, $this->headers)->assertOk()
            ->assertDontSee('treasury-reconcile-form', false)
            ->assertSee('UNC-77');

        // Missing treasury.reconcile: the rbac middleware answers web requests
        // with a redirect + flash error.
        $this->actingAs($this->engineer)->post($this->url('operator.treasury.projects.wallets.reconcile.store', ['wallet' => (string) $this->bank->id]), [
            'reconciliation_type' => 'cash_count', 'reconciled_at' => now()->toDateString(), 'ledger_entry_ids' => [$this->entryId()],
        ], $this->headers)->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, RecEntry::query()->count());

        $otherProject = $this->projectFor($this->tenant);
        $this->actingAs($this->owner)->get(route('operator.treasury.projects.wallets.reconcile', ['project' => (string) $otherProject->id, 'wallet' => (string) $this->bank->id], false), $this->headers)
            ->assertNotFound();
    }

    private function reconcilePage(): string
    {
        return $this->url('operator.treasury.projects.wallets.reconcile', ['wallet' => (string) $this->bank->id]);
    }

    /**
     * @param array<string, string> $params
     */
    private function url(string $name, array $params = []): string
    {
        return route($name, ['project' => (string) $this->project->id] + $params, false);
    }

    private function entryId(): string
    {
        return (string) Entry::query()->where('source_financial_document_id', (string) $this->funding->id)->value('id');
    }
}
