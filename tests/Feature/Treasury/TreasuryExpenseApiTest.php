<?php declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP-066 S3 — expenses over the canonical Zena API.
 */
class TreasuryExpenseApiTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;
    private Project $project;
    private User $owner;
    private User $engineer;
    private TreasuryWallet $cash;
    private TreasuryFinancialParty $payee;
    private ContractExpense $cost;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $this->seedTreasuryPermissions();
        $this->tenant = Tenant::factory()->create();
        $this->project = $this->projectFor($this->tenant);
        $this->owner = $this->userWithRole($this->tenant, 'Admin');
        $this->engineer = $this->userWithRole($this->tenant, 'site_engineer');
        $this->addMember($this->project, $this->engineer);
        $holder = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'employee', 'linked_user_id' => $this->engineer->id]);
        $investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor']);
        $this->payee = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'supplier']);
        $this->cash = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'custodian_party_id' => $holder->id]);
        app(TreasuryPostingService::class)->declareFunding($this->project, $this->owner, [
            'document_type' => 'funding', 'source_party_id' => (string) $investor->id,
            'destination_wallet_id' => (string) $this->cash->id, 'amount' => '10000000', 'transaction_date' => '2026-10-01',
        ]);
        $contract = Contract::factory()->create(['tenant_id' => $this->tenant->id, 'project_id' => $this->project->id, 'created_by' => $this->owner->id]);
        $this->cost = ContractExpense::query()->create([
            'tenant_id' => $this->tenant->id, 'contract_id' => $contract->id, 'expense_date' => '2026-10-01',
            'amount' => '4000000', 'category' => 'labor', 'description' => 'Nhân công',
        ]);
    }

    public function test_engineer_drafts_submits_and_owner_approves(): void
    {
        $id = (string) $this->postJson($this->route('store'), $this->payload('4000000'), $this->headersFor($this->engineer))
            ->assertStatus(201)->assertJsonPath('data.status', 'draft')->json('data.id');

        $this->postJson($this->route('submit', $id), [], $this->headersFor($this->engineer))->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->getJson($this->route('index') . '?status=submitted', $this->headersFor($this->owner))->assertOk()->assertJsonCount(1, 'data');

        $this->postJson($this->route('approve', $id), [], $this->headersFor($this->engineer))->assertStatus(403);
        $this->postJson($this->route('approve', $id), ['note' => 'OK'], $this->headersFor($this->owner))
            ->assertOk()->assertJsonPath('data.status', 'posted_unreconciled');

        $this->getJson($this->route('payables'), $this->headersFor($this->owner))
            ->assertOk()->assertJsonPath('data.0.paid', '4000000.00')->assertJsonPath('data.0.remaining', '0.00');
    }

    public function test_reject_needs_a_note_and_copy_creates_a_new_draft(): void
    {
        $id = (string) $this->postJson($this->route('store'), $this->payload('1000000'), $this->headersFor($this->engineer))->json('data.id');
        $this->postJson($this->route('submit', $id), [], $this->headersFor($this->engineer))->assertOk();

        $this->postJson($this->route('reject', $id), [], $this->headersFor($this->owner))->assertStatus(422);
        $this->postJson($this->route('reject', $id), ['note' => 'Thiếu chứng từ'], $this->headersFor($this->owner))
            ->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->postJson($this->route('copy', $id), [], $this->headersFor($this->engineer))
            ->assertStatus(201)->assertJsonPath('data.status', 'draft');
    }

    public function test_validation_errors(): void
    {
        $this->postJson($this->route('store'), ['allocations' => [['cost_source_type' => 'bogus']]] + $this->payload('1'), $this->headersFor($this->engineer))
            ->assertStatus(422)->assertJsonValidationErrors(['allocations.0.cost_source_type'], 'error.details.data');
        $this->postJson($this->route('store'), $this->payload('3999999'), $this->headersFor($this->engineer))
            ->assertStatus(422)->assertJsonValidationErrors(['allocations'], 'error.details.data');
    }

    public function test_client_and_other_tenant_are_refused(): void
    {
        $client = $this->userWithRole($this->tenant, 'Client');
        $this->addMember($this->project, $client);
        $this->getJson($this->route('index'), $this->headersFor($client))->assertStatus(403);

        $foreign = $this->projectFor(Tenant::factory()->create());
        $this->getJson(route('api.zena.treasury.expenses.index', ['project' => (string) $foreign->id], false), $this->headersFor($this->owner))->assertStatus(404);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $amount): array
    {
        return [
            'source_wallet_id' => (string) $this->cash->id,
            'destination_party_id' => (string) $this->payee->id,
            'amount' => $amount,
            'transaction_date' => '2026-10-04',
            'allocations' => [['cost_source_type' => 'contract_expense', 'cost_source_id' => (string) $this->cost->id, 'amount' => $amount === '3999999' ? '4000000' : $amount]],
        ];
    }

    private function route(string $name, ?string $expense = null): string
    {
        $params = ['project' => (string) $this->project->id];
        if ($expense !== null) {
            $params['treasuryExpense'] = $expense;
        }

        return route('api.zena.treasury.expenses.' . $name, $params, false);
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        return [
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $user->createToken('treasury-expense-test')->plainTextToken,
        ];
    }
}
