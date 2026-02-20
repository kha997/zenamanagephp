<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Contract;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;
use Tests\Traits\RouteNameTrait;

class ContractApiHardeningTest extends TestCase
{
    use RefreshDatabase, AuthenticationTestTrait, RouteNameTrait;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private Project $projectA;
    private Contract $contractA;

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

        $this->contractA = Contract::query()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'code' => 'CTR-A-HARD-001',
            'title' => 'Tenant A Contract',
            'status' => Contract::STATUS_ACTIVE,
            'currency' => 'USD',
            'total_value' => 15000,
            'created_by' => $this->userA->id,
        ]);

    }

    public function test_contract_index_denied_without_contract_view_permission(): void
    {
        [, $headers] = $this->createApiUserWithPermissions($this->tenantA, [], ['team_member']);

        $this->getJson(
            $this->v1('projects.contracts.index', ['project' => $this->projectA->id]),
            $headers
        )
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'E403.AUTHORIZATION');
    }

    public function test_contract_show_returns_404_cross_tenant(): void
    {
        $this->getJson(
            $this->v1('projects.contracts.show', [
                'project' => $this->projectA->id,
                'contract' => $this->contractA->id,
            ]),
            $this->freshHeadersFor($this->userB)
        )
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'E404.NOT_FOUND');
    }

    public function test_contract_update_returns_404_cross_tenant(): void
    {
        $this->putJson(
            $this->v1('projects.contracts.update', [
                'project' => $this->projectA->id,
                'contract' => $this->contractA->id,
            ]),
            ['title' => 'Cross tenant update attempt'],
            $this->freshHeadersFor($this->userB)
        )
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'E404.NOT_FOUND');
    }

    public function test_payment_create_denied_without_contract_payment_create_permission(): void
    {
        [, $headers] = $this->createApiUserWithPermissions($this->tenantA, ['contract.payment.view'], ['team_member']);

        $this->postJson(
            $this->v1('contracts.payments.store', ['contract' => $this->contractA->id]),
            [
                'name' => 'Initial payment',
                'amount' => 1000,
            ],
            $headers
        )
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'E403.AUTHORIZATION');
    }

    public function test_payment_create_returns_404_when_contract_cross_tenant(): void
    {
        $this->postJson(
            $this->v1('contracts.payments.store', ['contract' => $this->contractA->id]),
            [
                'name' => 'Cross tenant create',
                'amount' => 500,
            ],
            $this->freshHeadersFor($this->userB)
        )
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'E404.NOT_FOUND');
    }

    public function test_payment_index_returns_404_cross_tenant_contract(): void
    {
        $this->getJson(
            $this->v1('contracts.payments.index', ['contract' => $this->contractA->id]),
            $this->freshHeadersFor($this->userB)
        )
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'E404.NOT_FOUND');
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

        $token = $user->createToken('contract-api-hardening-test')->plainTextToken;

        return [$user, $this->authHeadersForUser($user, $token)];
    }

    /**
     * @return array<string, string>
     */
    private function freshHeadersFor(User $user): array
    {
        $token = $user->createToken('contract-api-hardening-test')->plainTextToken;

        return $this->authHeadersForUser($user, $token);
    }
}
