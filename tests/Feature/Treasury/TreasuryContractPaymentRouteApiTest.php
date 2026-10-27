<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryPaymentRoute as Route;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP-095 S4b-2 — contract-payment tracing over the canonical Zena API.
 */
class TreasuryContractPaymentRouteApiTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;

    private Project $project;

    private User $accountant;

    private User $engineer;

    private ContractPayment $payment;

    private TreasuryWallet $transit;

    private TreasuryWallet $siteCash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $this->seedTreasuryPermissions();
        $this->tenant = Tenant::factory()->create();
        $this->project = $this->projectFor($this->tenant);
        $this->accountant = $this->userWithRole($this->tenant, 'Finance');
        $this->engineer = $this->userWithRole($this->tenant, 'site_engineer');
        $this->addMember($this->project, $this->engineer);
        $contract = Contract::factory()->create(['tenant_id' => $this->tenant->id, 'project_id' => $this->project->id]);
        $this->payment = ContractPayment::factory()->create([
            'tenant_id' => $this->tenant->id, 'contract_id' => $contract->id, 'name' => 'Đợt 1',
            'amount' => '2000000', 'status' => 'paid', 'paid_at' => '2026-10-05',
        ]);
        $this->transit = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Trung chuyển']);
        $this->siteCash = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Quỹ công trường']);
    }

    public function test_accountant_traces_a_payment_and_the_list_follows(): void
    {
        $this->getJson($this->url('contract-payments.index'), $this->headersFor($this->engineer))
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $this->payment->id)
            ->assertJsonPath('data.0.remaining_amount', '2000000.00')
            ->assertJsonPath('data.0.flags.0', 'not_fully_traced');

        $routeId = (string) $this->postJson($this->url('contract-payments.routes.store'), $this->payload('2000000'), $this->headersFor($this->accountant))
            ->assertStatus(201)
            ->assertJsonPath('data.contract_payment_name', 'Đợt 1')
            ->assertJsonPath('data.status', Route::STATUS_PLANNED)
            ->assertJsonPath('data.document_id', null)
            ->json('data.id');

        $this->postJson(route('api.zena.treasury.routes.legs.store', ['project' => (string) $this->project->id, 'treasuryRoute' => $routeId], false), [
            'to_wallet_id' => (string) $this->siteCash->id, 'amount' => '2000000', 'occurred_at' => '2026-10-06',
        ], $this->headersFor($this->accountant))->assertStatus(201)->assertJsonPath('data.status', Route::STATUS_COMPLETED);

        $this->getJson($this->url('contract-payments.index'), $this->headersFor($this->engineer))
            ->assertOk()
            ->assertJsonPath('data.0.arrived_amount', '2000000.00')
            ->assertJsonCount(0, 'data.0.flags');
    }

    public function test_validation_permissions_and_cap(): void
    {
        $this->postJson($this->url('contract-payments.routes.store'), $this->payload('1'), $this->headersFor($this->engineer))->assertForbidden();
        $this->postJson($this->url('contract-payments.routes.store'), $this->payload('2000000.01'), $this->headersFor($this->accountant))
            ->assertStatus(422)->assertJsonValidationErrors(['amount'], 'error.details.data');
        $this->postJson($this->url('contract-payments.routes.store'), ['amount' => '1'], $this->headersFor($this->accountant))
            ->assertStatus(422)->assertJsonValidationErrors(['expected_destination_wallet_id'], 'error.details.data');
        $this->postJson(route('api.zena.treasury.routes.contract-payments.routes.store', ['project' => (string) $this->project->id, 'contractPayment' => '01JAAAAAAAAAAAAAAAAAAAAAAA'], false), $this->payload('1'), $this->headersFor($this->accountant))
            ->assertNotFound();
    }

    /**
     * @return array<string, string>
     */
    private function payload(string $amount): array
    {
        return ['expected_destination_wallet_id' => (string) $this->siteCash->id, 'amount' => $amount];
    }

    private function url(string $name): string
    {
        $params = ['project' => (string) $this->project->id];
        if ($name === 'contract-payments.routes.store') {
            $params['contractPayment'] = (string) $this->payment->id;
        }

        return route('api.zena.treasury.routes.' . $name, $params, false);
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        return [
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $user->createToken('treasury-contract-payment-route-test')->plainTextToken,
        ];
    }
}
