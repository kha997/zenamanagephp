<?php declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * GAP-063 S1 — parties and project wallets over the canonical Zena API.
 */
class TreasurySetupApiTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $this->seedTreasuryPermissions();
        $this->tenant = Tenant::factory()->create();
    }

    public function test_accountant_manages_parties_end_to_end(): void
    {
        $accountant = $this->userWithRole($this->tenant, 'Finance');
        $linkedUser = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $create = $this->postJson('/api/zena/treasury/parties', [
            'party_type' => 'employee',
            'name' => 'Kỹ sư Z',
            'linked_user_id' => (string) $linkedUser->id,
        ], $this->headersFor($accountant))->assertStatus(201);
        $partyId = (string) $create->json('data.id');

        $this->getJson('/api/zena/treasury/parties', $this->headersFor($accountant))
            ->assertOk()->assertJsonPath('data.0.id', $partyId);

        $this->putJson("/api/zena/treasury/parties/{$partyId}", ['name' => 'Kỹ sư Z - công trường 1'], $this->headersFor($accountant))
            ->assertOk()->assertJsonPath('data.name', 'Kỹ sư Z - công trường 1');

        $this->deleteJson("/api/zena/treasury/parties/{$partyId}", [], $this->headersFor($accountant))->assertOk();
        $this->assertDatabaseMissing('treasury_financial_parties', ['id' => $partyId]);
    }

    public function test_party_validation_rejects_unknown_type_and_other_tenant_links(): void
    {
        $accountant = $this->userWithRole($this->tenant, 'Finance');
        $foreignUser = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $this->postJson('/api/zena/treasury/parties', [
            'party_type' => 'bank_robber',
            'name' => 'X',
            'linked_user_id' => (string) $foreignUser->id,
        ], $this->headersFor($accountant))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['party_type', 'linked_user_id'], 'error.details.data');
    }

    public function test_engineer_can_list_parties_but_not_create(): void
    {
        $engineer = $this->userWithRole($this->tenant, 'site_engineer');

        $this->getJson('/api/zena/treasury/parties', $this->headersFor($engineer))->assertOk();
        $this->postJson('/api/zena/treasury/parties', ['party_type' => 'supplier', 'name' => 'NCC'], $this->headersFor($engineer))
            ->assertStatus(403);
    }

    public function test_party_used_as_custodian_cannot_be_deleted(): void
    {
        $accountant = $this->userWithRole($this->tenant, 'Finance');
        $party = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id]);
        TreasuryWallet::factory()->create([
            'project_id' => $this->projectFor($this->tenant)->id,
            'custodian_party_id' => $party->id,
        ]);

        $this->deleteJson("/api/zena/treasury/parties/{$party->id}", [], $this->headersFor($accountant))->assertStatus(409);
        $this->assertDatabaseHas('treasury_financial_parties', ['id' => $party->id]);
    }

    public function test_other_tenant_party_is_not_found(): void
    {
        $accountant = $this->userWithRole($this->tenant, 'Finance');
        $foreign = TreasuryFinancialParty::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $this->getJson("/api/zena/treasury/parties/{$foreign->id}", $this->headersFor($accountant))->assertStatus(404);
        $this->deleteJson("/api/zena/treasury/parties/{$foreign->id}", [], $this->headersFor($accountant))->assertStatus(404);
    }

    public function test_accountant_manages_project_wallets_end_to_end(): void
    {
        $accountant = $this->userWithRole($this->tenant, 'Finance');
        $project = $this->projectFor($this->tenant);
        $custodian = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'employee']);
        $base = "/api/zena/projects/{$project->id}/treasury/wallets";

        $create = $this->postJson($base, [
            'wallet_type' => 'employee_cash',
            'name' => 'Quỹ tiền mặt công trường',
            'custodian_party_id' => (string) $custodian->id,
        ], $this->headersFor($accountant))->assertStatus(201)
            ->assertJsonPath('data.project_id', (string) $project->id)
            ->assertJsonPath('data.tenant_id', (string) $this->tenant->id);
        $walletId = (string) $create->json('data.id');

        $this->getJson($base, $this->headersFor($accountant))
            ->assertOk()->assertJsonPath('data.0.custodian_party.id', (string) $custodian->id);

        $this->putJson("{$base}/{$walletId}", ['name' => 'Quỹ công trường A'], $this->headersFor($accountant))
            ->assertOk()->assertJsonPath('data.name', 'Quỹ công trường A');

        $this->deleteJson("{$base}/{$walletId}", [], $this->headersFor($accountant))->assertOk();
        $this->assertDatabaseMissing('treasury_wallets', ['id' => $walletId]);
    }

    public function test_wallet_project_cannot_be_changed(): void
    {
        $accountant = $this->userWithRole($this->tenant, 'Finance');
        $project = $this->projectFor($this->tenant);
        $other = $this->projectFor($this->tenant);
        $wallet = TreasuryWallet::factory()->create(['project_id' => $project->id]);

        $this->putJson("/api/zena/projects/{$project->id}/treasury/wallets/{$wallet->id}", ['project_id' => (string) $other->id], $this->headersFor($accountant))
            ->assertStatus(422);
        $this->assertSame((string) $project->id, (string) $wallet->fresh()?->project_id);
    }

    public function test_wallet_rejects_unknown_type_and_other_tenant_custodian(): void
    {
        $accountant = $this->userWithRole($this->tenant, 'Finance');
        $project = $this->projectFor($this->tenant);
        $foreign = TreasuryFinancialParty::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $this->postJson("/api/zena/projects/{$project->id}/treasury/wallets", [
            'wallet_type' => 'piggy_bank',
            'name' => 'W',
            'custodian_party_id' => (string) $foreign->id,
        ], $this->headersFor($accountant))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['wallet_type', 'custodian_party_id'], 'error.details.data');
    }

    public function test_engineer_member_can_view_but_not_manage_and_non_member_is_forbidden(): void
    {
        $project = $this->projectFor($this->tenant);
        TreasuryWallet::factory()->create(['project_id' => $project->id]);
        $member = $this->userWithRole($this->tenant, 'project_manager');
        $this->addMember($project, $member);
        $outsider = $this->userWithRole($this->tenant, 'project_manager');
        $base = "/api/zena/projects/{$project->id}/treasury/wallets";

        $this->getJson($base, $this->headersFor($member))->assertOk()->assertJsonCount(1, 'data');
        $this->postJson($base, ['wallet_type' => 'company_cash', 'name' => 'W'], $this->headersFor($member))->assertStatus(403);
        $this->getJson($base, $this->headersFor($outsider))->assertStatus(403);
    }

    public function test_client_member_is_forbidden(): void
    {
        $project = $this->projectFor($this->tenant);
        $client = $this->userWithRole($this->tenant, 'Client');
        $this->addMember($project, $client);

        $this->getJson("/api/zena/projects/{$project->id}/treasury/wallets", $this->headersFor($client))->assertStatus(403);
    }

    public function test_other_tenant_project_is_not_found(): void
    {
        $accountant = $this->userWithRole($this->tenant, 'Finance');
        $foreignProject = $this->projectFor(Tenant::factory()->create());

        $this->getJson("/api/zena/projects/{$foreignProject->id}/treasury/wallets", $this->headersFor($accountant))->assertStatus(404);
    }

    public function test_wallet_with_a_reconciliation_cannot_be_deleted(): void
    {
        $accountant = $this->userWithRole($this->tenant, 'Finance');
        $project = $this->projectFor($this->tenant);
        $wallet = TreasuryWallet::factory()->create(['project_id' => $project->id]);
        DB::table('treasury_reconciliations')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => (string) $this->tenant->id,
            'wallet_id' => (string) $wallet->id,
            'created_at' => now(),
            'updated_at' => now(),
            'reconciliation_type' => 'bank_statement',
            'reconciled_at' => now(),
            'reconciled_by' => (string) $accountant->id,
        ]);

        $this->deleteJson("/api/zena/projects/{$project->id}/treasury/wallets/{$wallet->id}", [], $this->headersFor($accountant))
            ->assertStatus(409);
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        return [
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $user->createToken('treasury-test')->plainTextToken,
        ];
    }
}
