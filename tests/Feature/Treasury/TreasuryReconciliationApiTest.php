<?php declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryLedgerEntry as Entry;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP-067 S4a — reconciliation over the canonical Zena API.
 */
class TreasuryReconciliationApiTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;
    private Project $project;
    private User $owner;
    private User $accountant;
    private User $engineer;
    private TreasuryWallet $bank;
    private Document $funding;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $this->seedTreasuryPermissions();
        $this->tenant = Tenant::factory()->create();
        $this->project = $this->projectFor($this->tenant);
        $this->owner = $this->userWithRole($this->tenant, 'Admin');
        $this->accountant = $this->userWithRole($this->tenant, 'Finance');
        $this->engineer = $this->userWithRole($this->tenant, 'site_engineer');
        $this->addMember($this->project, $this->engineer);
        $investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor']);
        $this->bank = TreasuryWallet::factory()->create(['project_id' => $this->project->id]);
        $this->funding = app(TreasuryPostingService::class)->declareFunding($this->project, $this->owner, [
            'document_type' => 'funding', 'source_party_id' => (string) $investor->id,
            'destination_wallet_id' => (string) $this->bank->id, 'amount' => '5000000', 'transaction_date' => '2026-10-01',
        ]);
    }

    public function test_accountant_reconciles_then_undoes_a_line_and_the_whole_reconciliation(): void
    {
        $entryId = $this->entryId();
        $this->getJson($this->route('wallet'), $this->headersFor($this->engineer))
            ->assertOk()
            ->assertJsonPath('data.balances.unreconciled', '5000000.00')
            ->assertJsonPath('data.unreconciled_entries.0.ledger_entry_id', $entryId);

        $recId = (string) $this->postJson($this->route('store'), $this->payload([$entryId]), $this->headersFor($this->accountant))
            ->assertStatus(201)
            ->assertJsonPath('data.external_reference', 'SK-10')
            ->assertJsonPath('data.lines.0.active', true)
            ->json('data.id');
        $lineId = (string) $this->getJson($this->route('index') . '?wallet_id=' . $this->bank->id, $this->headersFor($this->engineer))
            ->assertOk()->assertJsonCount(1, 'data')->json('data.0.lines.0.id');
        $this->assertSame(Document::STATUS_POSTED_RECONCILED, $this->funding->fresh()?->status);
        $this->getJson($this->route('wallet'), $this->headersFor($this->owner))
            ->assertJsonPath('data.balances.reconciled', '5000000.00')
            ->assertJsonCount(0, 'data.unreconciled_entries');

        $this->postJson($this->route('store'), $this->payload([$entryId]), $this->headersFor($this->owner))
            ->assertStatus(422)->assertJsonValidationErrors(['ledger_entry_ids'], 'error.details.data');

        $this->postJson($this->route('entries.undo', $lineId), [], $this->headersFor($this->accountant))
            ->assertStatus(422)->assertJsonValidationErrors(['reason'], 'error.details.data');
        $this->postJson($this->route('entries.undo', $lineId), ['reason' => 'Sai số tiền'], $this->headersFor($this->accountant))
            ->assertOk()->assertJsonPath('data.lines.0.undo_reason', 'Sai số tiền');
        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, $this->funding->fresh()?->status);

        $this->postJson($this->route('undo', $recId), ['reason' => 'Lại'], $this->headersFor($this->accountant))
            ->assertStatus(422)->assertJsonValidationErrors(['reconciliation'], 'error.details.data');

        $second = (string) $this->postJson($this->route('store'), $this->payload([$entryId], 'cash_count', null), $this->headersFor($this->owner))
            ->assertStatus(201)->json('data.id');
        $this->postJson($this->route('undo', $second), ['reason' => 'Kiểm quỹ lại'], $this->headersFor($this->owner))
            ->assertOk()->assertJsonPath('data.active', false);
        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, $this->funding->fresh()?->status);
    }

    public function test_validation_errors(): void
    {
        $entryId = $this->entryId();
        $this->postJson($this->route('store'), ['reconciliation_type' => 'phone'] + $this->payload([$entryId]), $this->headersFor($this->owner))
            ->assertStatus(422)->assertJsonValidationErrors(['reconciliation_type'], 'error.details.data');
        $this->postJson($this->route('store'), $this->payload([$entryId], 'voucher', null), $this->headersFor($this->owner))
            ->assertStatus(422)->assertJsonValidationErrors(['external_reference'], 'error.details.data');
        $this->postJson($this->route('store'), ['reconciled_at' => now()->addDays(2)->toDateString()] + $this->payload([$entryId]), $this->headersFor($this->owner))
            ->assertStatus(422)->assertJsonValidationErrors(['reconciled_at'], 'error.details.data');
        $this->postJson($this->route('store'), $this->payload([]), $this->headersFor($this->owner))
            ->assertStatus(422)->assertJsonValidationErrors(['ledger_entry_ids'], 'error.details.data');
        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, $this->funding->fresh()?->status);
    }

    public function test_engineer_and_viewer_can_view_but_not_reconcile(): void
    {
        $viewer = $this->userWithRole($this->tenant, 'Designer');
        $this->addMember($this->project, $viewer);

        foreach ([$this->engineer, $viewer] as $user) {
            $this->getJson($this->route('wallet'), $this->headersFor($user))->assertOk();
            $this->getJson($this->route('index'), $this->headersFor($user))->assertOk();
            $this->postJson($this->route('store'), $this->payload([$this->entryId()]), $this->headersFor($user))->assertStatus(403);
        }
        $this->assertSame(Document::STATUS_POSTED_UNRECONCILED, $this->funding->fresh()?->status);
    }

    public function test_client_other_project_and_other_tenant_are_refused(): void
    {
        $client = $this->userWithRole($this->tenant, 'Client');
        $this->addMember($this->project, $client);
        $this->getJson($this->route('wallet'), $this->headersFor($client))->assertStatus(403);

        $otherProject = $this->projectFor($this->tenant);
        $this->getJson(route('api.zena.treasury.reconciliation.wallet', ['project' => (string) $otherProject->id, 'wallet' => (string) $this->bank->id], false), $this->headersFor($this->owner))
            ->assertStatus(404);
        $this->postJson(route('api.zena.treasury.reconciliation.store', ['project' => (string) $otherProject->id, 'wallet' => (string) $this->bank->id], false), $this->payload([$this->entryId()]), $this->headersFor($this->owner))
            ->assertStatus(404);

        $foreign = $this->projectFor(Tenant::factory()->create());
        $this->getJson(route('api.zena.treasury.reconciliation.index', ['project' => (string) $foreign->id], false), $this->headersFor($this->owner))->assertStatus(404);
        $this->postJson($this->route('undo', '01JZZZZZZZZZZZZZZZZZZZZZZZ'), ['reason' => 'x'], $this->headersFor($this->owner))->assertStatus(404);
        $this->postJson($this->route('entries.undo', '01JZZZZZZZZZZZZZZZZZZZZZZZ'), ['reason' => 'x'], $this->headersFor($this->owner))->assertStatus(404);
    }

    private function entryId(): string
    {
        return (string) Entry::query()->where('source_financial_document_id', (string) $this->funding->id)->value('id');
    }

    /**
     * @param list<string> $entryIds
     * @return array<string, mixed>
     */
    private function payload(array $entryIds, string $type = 'bank_statement', ?string $reference = 'SK-10'): array
    {
        return [
            'reconciliation_type' => $type,
            'external_reference' => $reference,
            'reconciled_at' => now()->toDateString(),
            'ledger_entry_ids' => $entryIds,
        ];
    }

    private function route(string $name, ?string $id = null): string
    {
        $params = ['project' => (string) $this->project->id];
        if (in_array($name, ['wallet', 'store'], true)) {
            $params['wallet'] = (string) $this->bank->id;
        }
        if ($name === 'undo') {
            $params['treasuryReconciliation'] = (string) $id;
        }
        if ($name === 'entries.undo') {
            $params['treasuryReconciliationEntry'] = (string) $id;
        }

        return route('api.zena.treasury.reconciliation.' . $name, $params, false);
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        return [
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $user->createToken('treasury-reconciliation-test')->plainTextToken,
        ];
    }
}
