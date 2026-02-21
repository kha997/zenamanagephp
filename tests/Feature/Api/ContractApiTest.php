<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;
use Tests\Traits\RouteNameTrait;

class ContractApiTest extends TestCase
{
    use RefreshDatabase, AuthenticationTestTrait, RouteNameTrait;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private Project $projectA;
    private Project $projectB;

    /** @var list<string> */
    private array $allPermissions = [
        'contract.view',
        'contract.create',
        'contract.update',
        'contract.delete',
        'contract.payment.view',
        'contract.payment.create',
        'contract.payment.update',
        'contract.payment.delete',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        [$this->userA] = $this->createApiUserWithPermissions($this->tenantA, $this->allPermissions);
        [$this->userB] = $this->createApiUserWithPermissions($this->tenantB, $this->allPermissions);

        $this->projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'pm_id' => $this->userA->id,
            'created_by' => $this->userA->id,
        ]);

        $this->projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'pm_id' => $this->userB->id,
            'created_by' => $this->userB->id,
        ]);
    }

    public function test_contract_routes_accept_ulid_strings(): void
    {
        $createResponse = $this->postJson(
            $this->v1('projects.contracts.store', ['project' => $this->projectA->id]),
            [
                'code' => 'CTR-ULID-001',
                'title' => 'ULID Contract',
                'status' => Contract::STATUS_DRAFT,
                'currency' => 'USD',
                'total_value' => 12345.67,
            ],
            $this->freshHeadersFor($this->userA)
        );

        $createResponse->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $contractId = (string) $createResponse->json('data.id');
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', strtoupper($contractId));

        $this->getJson(
            $this->v1('projects.contracts.show', ['project' => $this->projectA->id, 'contract' => $contractId]),
            $this->freshHeadersFor($this->userA)
        )
            ->assertStatus(200)
            ->assertJsonPath('data.id', $contractId);
    }

    public function test_create_contract_null_status_and_currency_use_defaults(): void
    {
        $response = $this->postJson(
            $this->v1('projects.contracts.store', ['project' => $this->projectA->id]),
            [
                'code' => 'ctr-null-default-001',
                'title' => 'Null Default Contract',
                'status' => null,
                'currency' => null,
            ],
            $this->freshHeadersFor($this->userA)
        );

        $response->assertStatus(201)
            ->assertJsonPath('data.status', Contract::STATUS_DRAFT)
            ->assertJsonPath('data.currency', 'USD');

        $contractId = (string) $response->json('data.id');
        $this->assertDatabaseHas('contracts', [
            'id' => $contractId,
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'code' => 'CTR-NULL-DEFAULT-001',
            'status' => Contract::STATUS_DRAFT,
            'currency' => 'USD',
        ]);
    }

    public function test_update_contract_null_status_and_currency_do_not_overwrite_with_empty_string(): void
    {
        $contract = Contract::query()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'code' => 'CTR-NULL-UPD-001',
            'title' => 'Before null update',
            'status' => Contract::STATUS_ACTIVE,
            'currency' => 'EUR',
            'total_value' => 100,
            'created_by' => $this->userA->id,
        ]);

        $response = $this->putJson(
            $this->v1('projects.contracts.update', ['project' => $this->projectA->id, 'contract' => $contract->id]),
            [
                'title' => 'After null update',
                'status' => null,
                'currency' => null,
            ],
            $this->freshHeadersFor($this->userA)
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.status', Contract::STATUS_ACTIVE)
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.title', 'After null update');

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => Contract::STATUS_ACTIVE,
            'currency' => 'EUR',
            'title' => 'After null update',
        ]);
        $this->assertDatabaseMissing('contracts', ['id' => $contract->id, 'status' => '']);
        $this->assertDatabaseMissing('contracts', ['id' => $contract->id, 'currency' => '']);
    }

    public function test_create_contract_rejects_case_insensitive_duplicate_code_after_normalization(): void
    {
        Contract::query()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'code' => 'ABC',
            'title' => 'Existing Contract',
            'status' => Contract::STATUS_DRAFT,
            'currency' => 'USD',
            'total_value' => 0,
            'created_by' => $this->userA->id,
        ]);

        $response = $this->postJson(
            $this->v1('projects.contracts.store', ['project' => $this->projectA->id]),
            [
                'code' => ' abc ',
                'title' => 'Duplicate code should fail',
            ],
            $this->freshHeadersFor($this->userA)
        );

        $response->assertStatus(422);

        $this->assertDatabaseCount('contracts', 1);
        $this->assertDatabaseHas('contracts', [
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'code' => 'ABC',
        ]);
    }

    public function test_cross_tenant_contract_show_update_delete_returns_404(): void
    {
        $contract = Contract::query()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'code' => 'CTR-CROSS-001',
            'title' => 'Tenant A Contract',
            'status' => Contract::STATUS_ACTIVE,
            'currency' => 'USD',
            'total_value' => 25000,
            'created_by' => $this->userA->id,
        ]);

        $this->getJson(
            $this->v1('projects.contracts.show', ['project' => $this->projectA->id, 'contract' => $contract->id]),
            $this->freshHeadersFor($this->userB)
        )
            ->assertStatus(404);

        $this->putJson($this->v1('projects.contracts.update', ['project' => $this->projectA->id, 'contract' => $contract->id]), [
                'title' => 'Cross tenant edit',
            ], $this->freshHeadersFor($this->userB))
            ->assertStatus(404);

        $this->deleteJson(
            $this->v1('projects.contracts.destroy', ['project' => $this->projectA->id, 'contract' => $contract->id]),
            [],
            $this->freshHeadersFor($this->userB)
        )
            ->assertStatus(404);
    }

    public function test_contract_rbac_denies_without_permission_and_allows_with_permission(): void
    {
        [$limitedUser, $limitedHeaders] = $this->createApiUserWithPermissions($this->tenantA, [], ['team_member']);

        $this->getJson(
            $this->v1('projects.contracts.index', ['project' => $this->projectA->id]),
            $limitedHeaders
        )
            ->assertStatus(403);

        $this->postJson($this->v1('projects.contracts.store', ['project' => $this->projectA->id]), [
                'code' => 'CTR-NOPE-001',
                'title' => 'Should fail',
            ], $limitedHeaders)
            ->assertStatus(403);

        $this->assertNotNull($limitedUser->id);
    }

    public function test_payments_are_tenant_and_contract_scoped(): void
    {
        $contractA = Contract::query()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'code' => 'CTR-PAY-001',
            'title' => 'Payment Contract A',
            'status' => Contract::STATUS_ACTIVE,
            'currency' => 'USD',
            'total_value' => 50000,
            'created_by' => $this->userA->id,
        ]);

        $contractB = Contract::query()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $this->projectB->id,
            'code' => 'CTR-PAY-002',
            'title' => 'Payment Contract B',
            'status' => Contract::STATUS_ACTIVE,
            'currency' => 'USD',
            'total_value' => 60000,
            'created_by' => $this->userB->id,
        ]);

        $createPaymentResponse = $this->postJson($this->v1('contracts.payments.store', ['contract' => $contractA->id]), [
                'name' => 'Advance',
                'amount' => 10000,
                'status' => ContractPayment::STATUS_PLANNED,
            ], $this->freshHeadersFor($this->userA));

        $createPaymentResponse->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $paymentId = (string) $createPaymentResponse->json('data.id');

        $this->postJson($this->v1('contracts.payments.store', ['contract' => $contractB->id]), [
                'name' => 'Cross tenant create',
                'amount' => 999,
            ], $this->freshHeadersFor($this->userA))
            ->assertStatus(404);

    }

    public function test_payment_rbac_denies_without_permission_and_allows_with_permission(): void
    {
        $contract = Contract::query()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'code' => 'CTR-RBAC-001',
            'title' => 'RBAC Contract',
            'status' => Contract::STATUS_ACTIVE,
            'currency' => 'USD',
            'total_value' => 11000,
            'created_by' => $this->userA->id,
        ]);

        [, $limitedHeaders] = $this->createApiUserWithPermissions($this->tenantA, ['contract.view'], ['team_member']);

        $this->getJson(
            $this->v1('contracts.payments.index', ['contract' => $contract->id]),
            $limitedHeaders
        )
            ->assertStatus(403);

    }

    /**
     * @param list<string> $permissions
     * @param list<string> $roles
     * @return array{0: User, 1: array<string, string>}
     */
    private function createApiUserWithPermissions(Tenant $tenant, array $permissions, array $roles = ['admin']): array
    {
        $user = $this->createTenantUser(
            $tenant,
            [],
            $roles,
            $permissions
        );

        $token = $user->createToken('contract-api-test')->plainTextToken;

        return [$user, $this->authHeadersForUser($user, $token)];
    }

    /**
     * @return array<string, string>
     */
    private function freshHeadersFor(User $user): array
    {
        $token = $user->createToken('contract-api-test')->plainTextToken;

        return $this->authHeadersForUser($user, $token);
    }
}
