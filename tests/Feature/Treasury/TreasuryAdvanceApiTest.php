<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryAdvanceSettlement as Settlement;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryExpenseService as Expenses;
use App\Services\Treasury\TreasuryPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP-096 S5 — advances over the canonical Zena API.
 */
class TreasuryAdvanceApiTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;

    private Project $project;

    private User $owner;

    private User $accountant;

    private User $engineer;

    private TreasuryWallet $bank;

    private TreasuryWallet $engineerCash;

    private TreasuryFinancialParty $holder;

    private ContractExpense $cost;

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
        $this->holder = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'employee', 'linked_user_id' => $this->engineer->id]);
        $this->bank = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Bank']);
        $this->engineerCash = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Z cash', 'custodian_party_id' => $this->holder->id]);
        $posting = app(TreasuryPostingService::class);
        $posting->declareFunding($this->project, $this->owner, [
            'document_type' => 'funding', 'source_party_id' => (string) $investor->id,
            'destination_wallet_id' => (string) $this->bank->id, 'amount' => '10000000', 'transaction_date' => '2026-10-01',
        ]);
        $posting->transfer($this->project, $this->owner, [
            'source_wallet_id' => (string) $this->bank->id, 'destination_wallet_id' => (string) $this->engineerCash->id,
            'amount' => '5000000', 'transaction_date' => '2026-10-02',
        ]);
        $contract = Contract::factory()->create(['tenant_id' => $this->tenant->id, 'project_id' => $this->project->id, 'created_by' => $this->owner->id]);
        $this->cost = ContractExpense::query()->create([
            'tenant_id' => $this->tenant->id, 'contract_id' => $contract->id, 'expense_date' => '2026-10-01',
            'amount' => '3000000', 'category' => 'labor', 'description' => 'Nhân công',
        ]);
    }

    public function test_advance_lifecycle_over_the_api(): void
    {
        $engineer = $this->headersFor($this->engineer);
        $accountant = $this->headersFor($this->accountant);

        $id = (string) $this->postJson($this->url('store'), [
            'source_wallet_id' => (string) $this->engineerCash->id, 'destination_party_id' => (string) $this->holder->id,
            'amount' => '2000000', 'transaction_date' => '2026-10-03',
        ], $engineer)->assertStatus(201)->assertJsonPath('data.status', Document::STATUS_DRAFT)->json('data.id');

        $this->postJson($this->url('submit', $id), [], $engineer)->assertOk()->assertJsonPath('data.status', Document::STATUS_SUBMITTED);
        $this->postJson($this->url('approve', $id), [], $engineer)->assertForbidden();
        $this->postJson($this->url('approve', $id), [], $accountant)->assertOk()
            ->assertJsonPath('data.status', Document::STATUS_POSTED_UNRECONCILED)
            ->assertJsonPath('data.advance.outstanding', '2000000.00');

        $this->postJson($this->url('settlement-request.store', $id), [
            'amount' => '1500000',
            'allocations' => [['cost_source_type' => Expenses::COST_CONTRACT_EXPENSE, 'cost_source_id' => (string) $this->cost->id, 'amount' => '1500000']],
        ], $engineer)->assertStatus(201)->assertJsonPath('data.open_request.amount', '1500000.00');
        $this->postJson($this->url('settlement-request.approve', $id), [], $accountant)->assertOk()
            ->assertJsonPath('data.open_request', null)
            ->assertJsonPath('data.advance.settled', '1500000.00')
            ->assertJsonPath('data.advance.outstanding', '500000.00');

        $this->postJson($this->url('returns.store', $id), ['destination_wallet_id' => (string) $this->bank->id, 'amount' => '200000', 'transaction_date' => '2026-10-05'], $engineer)->assertForbidden();
        $this->postJson($this->url('returns.store', $id), ['destination_wallet_id' => (string) $this->bank->id, 'amount' => '200000', 'transaction_date' => '2026-10-05'], $accountant)
            ->assertStatus(201)->assertJsonPath('data.advance.outstanding', '300000.00')->assertJsonCount(1, 'data.returns');

        $this->getJson($this->url('ageing'), $engineer)->assertOk()
            ->assertJsonPath('data.rows.0.id', $id)
            ->assertJsonPath('data.rows.0.bucket', 'under_30')
            ->assertJsonPath('data.totals.under_30', '300000.00');

        $settlementId = (string) Settlement::query()->where('settlement_type', Settlement::SETTLEMENT_TYPE_APPROVED_EXPENSE)->value('id');
        $remove = route('api.zena.treasury.advances.settlements.remove', ['project' => (string) $this->project->id, 'treasuryAdvanceSettlement' => $settlementId], false);
        $this->postJson($remove, ['reason' => 'Nhầm'], $engineer)->assertForbidden();
        $this->postJson($remove, [], $accountant)->assertStatus(422)->assertJsonValidationErrors(['reason'], 'error.details.data');
        $this->postJson($remove, ['reason' => 'Nhầm'], $accountant)->assertOk()->assertJsonPath('data.advance.outstanding', '1800000.00');
    }

    public function test_validation_and_rule_errors(): void
    {
        // The accountant approves but does not request (S3 permissions).
        $this->postJson($this->url('store'), ['amount' => '1'], $this->headersFor($this->accountant))->assertForbidden();
        $owner = $this->headersFor($this->owner);
        $this->postJson($this->url('store'), ['amount' => '1'], $owner)->assertStatus(422)
            ->assertJsonValidationErrors(['source_wallet_id', 'destination_party_id', 'transaction_date'], 'error.details.data');
        $this->getJson($this->url('show', '01JAAAAAAAAAAAAAAAAAAAAAAA'), $owner)->assertNotFound();

        $id = (string) $this->postJson($this->url('store'), [
            'source_wallet_id' => (string) $this->bank->id, 'destination_party_id' => (string) $this->holder->id,
            'amount' => '1000', 'transaction_date' => '2026-10-03',
        ], $owner)->assertStatus(201)->json('data.id');
        // Not paid out yet: no settlement.
        $this->postJson($this->url('settlement-request.store', $id), [
            'amount' => '1000',
            'allocations' => [['cost_source_type' => Expenses::COST_CONTRACT_EXPENSE, 'cost_source_id' => (string) $this->cost->id, 'amount' => '1000']],
        ], $owner)->assertStatus(422)->assertJsonValidationErrors(['advance'], 'error.details.data');
        $this->postJson($this->url('settlement-request.store', $id), ['amount' => '1000'], $owner)
            ->assertStatus(422)->assertJsonValidationErrors(['allocations'], 'error.details.data');
    }

    private function url(string $name, ?string $advance = null): string
    {
        $params = ['project' => (string) $this->project->id];
        if ($advance !== null) {
            $params['treasuryAdvance'] = $advance;
        }

        return route('api.zena.treasury.advances.' . $name, $params, false);
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        return [
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $user->createToken('treasury-advance-test')->plainTextToken,
        ];
    }
}
