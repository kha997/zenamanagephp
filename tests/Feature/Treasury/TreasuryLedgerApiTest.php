<?php declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP-064 S2 — ledger actions over the canonical Zena API.
 */
class TreasuryLedgerApiTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;
    private Project $project;
    private TreasuryFinancialParty $investor;
    private TreasuryWallet $bank;
    private TreasuryWallet $cash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $this->seedTreasuryPermissions();
        $this->tenant = Tenant::factory()->create();
        $this->project = $this->projectFor($this->tenant);
        $this->investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor']);
        $this->bank = TreasuryWallet::factory()->create(['project_id' => $this->project->id]);
        $this->cash = TreasuryWallet::factory()->create(['project_id' => $this->project->id]);
    }

    public function test_owner_runs_funding_transfer_reversal_and_reads_balances(): void
    {
        $owner = $this->userWithRole($this->tenant, 'Admin');

        $funding = $this->postJson($this->url('funding'), $this->fundingPayload('100000000'), $this->headersFor($owner))
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'posted_unreconciled')
            ->assertJsonPath('data.transaction_date', fn ($v) => str_starts_with((string) $v, '2026-10-01'));
        $fundingId = (string) $funding->json('data.id');

        $this->postJson($this->url('transfers'), [
            'source_wallet_id' => (string) $this->bank->id,
            'destination_wallet_id' => (string) $this->cash->id,
            'amount' => '40000000',
            'transaction_date' => '2026-10-02',
        ], $this->headersFor($owner))->assertStatus(201);

        $this->getJson($this->url('balances'), $this->headersFor($owner))
            ->assertOk()
            ->assertJsonPath('data.held_total', '100000000.00')
            ->assertJsonPath('data.wallets.' . $this->bank->id, '60000000.00')
            ->assertJsonPath('data.wallets.' . $this->cash->id, '40000000.00');

        $this->postJson($this->url("documents/{$fundingId}/reverse"), [
            'transaction_date' => '2026-10-03',
            'description' => 'Nhập nhầm',
        ], $this->headersFor($owner))->assertStatus(201)->assertJsonPath('data.document_type', 'reversal');

        $this->getJson($this->url('documents'), $this->headersFor($owner))
            ->assertOk()->assertJsonCount(3, 'data');
        $this->getJson($this->url("documents/{$fundingId}"), $this->headersFor($owner))
            ->assertOk()->assertJsonPath('data.status', 'reversed');
    }

    public function test_duplicate_returns_409_until_confirmed(): void
    {
        $owner = $this->userWithRole($this->tenant, 'Admin');
        $this->postJson($this->url('funding'), $this->fundingPayload('5000000'), $this->headersFor($owner))->assertStatus(201);

        $this->postJson($this->url('funding'), $this->fundingPayload('5000000'), $this->headersFor($owner))
            ->assertStatus(409);
        $this->postJson($this->url('funding'), $this->fundingPayload('5000000') + ['confirm_duplicate' => true], $this->headersFor($owner))
            ->assertStatus(201);
    }

    public function test_validation_and_rule_errors_are_422(): void
    {
        $owner = $this->userWithRole($this->tenant, 'Admin');

        $this->postJson($this->url('funding'), ['amount' => '-5'] + $this->fundingPayload('1'), $this->headersFor($owner))
            ->assertStatus(422)->assertJsonValidationErrors(['amount'], 'error.details.data');
        $this->postJson($this->url('transfers'), [
            'source_wallet_id' => (string) $this->bank->id, 'destination_wallet_id' => (string) $this->cash->id,
            'amount' => '1', 'transaction_date' => '2026-10-02',
        ], $this->headersFor($owner))->assertStatus(422)->assertJsonValidationErrors(['source_wallet_id'], 'error.details.data');
        $this->postJson($this->url('adjustments'), [
            'wallet_id' => (string) $this->cash->id, 'direction' => 'increase', 'amount' => '1', 'transaction_date' => '2026-10-02',
        ], $this->headersFor($owner))->assertStatus(422)->assertJsonValidationErrors(['description'], 'error.details.data');
    }

    public function test_permissions_follow_the_role_defaults(): void
    {
        $engineer = $this->userWithRole($this->tenant, 'site_engineer');
        $this->addMember($this->project, $engineer);
        $accountant = $this->userWithRole($this->tenant, 'Finance');
        $viewer = $this->userWithRole($this->tenant, 'Designer');
        $this->addMember($this->project, $viewer);

        $this->postJson($this->url('funding'), $this->fundingPayload('1000'), $this->headersFor($engineer))->assertStatus(201);
        $this->postJson($this->url('adjustments'), [
            'wallet_id' => (string) $this->cash->id, 'direction' => 'increase', 'amount' => '1',
            'transaction_date' => '2026-10-02', 'description' => 'x',
        ], $this->headersFor($engineer))->assertStatus(403);

        $this->postJson($this->url('funding'), $this->fundingPayload('2000'), $this->headersFor($accountant))->assertStatus(403);
        $this->postJson($this->url('adjustments'), [
            'wallet_id' => (string) $this->cash->id, 'direction' => 'increase', 'amount' => '1',
            'transaction_date' => '2026-10-02', 'description' => 'Số dư đầu kỳ',
        ], $this->headersFor($accountant))->assertStatus(201);

        $this->getJson($this->url('balances'), $this->headersFor($viewer))->assertOk();
        $this->postJson($this->url('funding'), $this->fundingPayload('3000'), $this->headersFor($viewer))->assertStatus(403);
    }

    public function test_other_tenant_project_and_document_are_not_found(): void
    {
        $owner = $this->userWithRole($this->tenant, 'Admin');
        $foreignProject = $this->projectFor(Tenant::factory()->create());

        $this->getJson("/api/zena/projects/{$foreignProject->id}/treasury/balances", $this->headersFor($owner))->assertStatus(404);
        $this->postJson($this->url('documents/01J0000000000000000000000X/reverse'), [
            'transaction_date' => '2026-10-03', 'description' => 'x',
        ], $this->headersFor($owner))->assertStatus(404);
    }

    /**
     * @return array<string, string>
     */
    private function fundingPayload(string $amount): array
    {
        return [
            'document_type' => 'funding',
            'source_party_id' => (string) $this->investor->id,
            'destination_wallet_id' => (string) $this->bank->id,
            'amount' => $amount,
            'transaction_date' => '2026-10-01',
            'reference' => 'UNC-77',
        ];
    }

    private function url(string $suffix): string
    {
        return "/api/zena/projects/{$this->project->id}/treasury/{$suffix}";
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        return [
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $user->createToken('treasury-ledger-test')->plainTextToken,
        ];
    }
}
