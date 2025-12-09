<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Contract;
use App\Models\ContractLine;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * ContractApiTest
 * 
 * Round 219: Core Contracts & Budget (Backend-first)
 * 
 * Tests for contracts API endpoints with tenant isolation and CRUD operations
 * 
 * @group contracts
 * @group api-v1
 */
class ContractApiTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected Project $projectA;
    protected Project $projectB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(219002);
        $this->setDomainName('contract-api');
        $this->setupDomainIsolation();

        // Create tenants
        $this->tenantA = Tenant::factory()->create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a-' . uniqid(),
        ]);
        
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-' . uniqid(),
        ]);

        // Create users
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'pm',
        ]);

        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'role' => 'pm',
        ]);

        // Attach users to tenants
        $this->userA->tenants()->attach($this->tenantA->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);
        
        $this->userB->tenants()->attach($this->tenantB->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);

        // Round 229: Ensure users have cost permissions for tests
        $costViewPermission = Permission::firstOrCreate(['name' => 'projects.cost.view']);
        $costEditPermission = Permission::firstOrCreate(['name' => 'projects.cost.edit']);
        
        $role = Role::firstOrCreate(['name' => 'pm']);
        $role->permissions()->syncWithoutDetaching([
            $costViewPermission->id,
            $costEditPermission->id,
        ]);
        
        $this->userA->roles()->syncWithoutDetaching([$role->id]);
        $this->userB->roles()->syncWithoutDetaching([$role->id]);

        // Create projects
        $this->projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Project A',
        ]);

        $this->projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Project B',
        ]);
    }

    public function test_it_lists_contracts_for_project(): void
    {
        Sanctum::actingAs($this->userA);

        // Create contracts
        Contract::factory()->count(3)->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/contracts");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'project_id',
                        'code',
                        'name',
                    ]
                ]
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_it_creates_contract_with_lines_for_project(): void
    {
        Sanctum::actingAs($this->userA);

        $data = [
            'code' => 'CT-001',
            'name' => 'Test Contract',
            'type' => 'subcontract',
            'party_name' => 'Test Party',
            'base_amount' => 10000000,
            'currency' => 'VND',
            'vat_percent' => 10,
            'total_amount_with_vat' => 11000000,
            'status' => 'active',
            'lines' => [
                [
                    'description' => 'Line Item 1',
                    'quantity' => 10,
                    'unit_price' => 1000000,
                    'amount' => 10000000,
                ],
            ],
        ];

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->projectA->id}/contracts",
            $data
        );

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'code',
                    'name',
                    'lines',
                ]
            ]);

        $this->assertDatabaseHas('contracts', [
            'project_id' => $this->projectA->id,
            'code' => 'CT-001',
            'name' => 'Test Contract',
        ]);

        $contract = Contract::where('code', 'CT-001')->first();
        $this->assertDatabaseHas('contract_lines', [
            'contract_id' => $contract->id,
            'description' => 'Line Item 1',
        ]);
    }

    public function test_it_updates_contract_basic_fields(): void
    {
        Sanctum::actingAs($this->userA);

        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'code' => 'CT-ORIG',
            'name' => 'Original Name',
        ]);

        $data = [
            'name' => 'Updated Name',
            'status' => 'completed',
        ];

        $response = $this->patchJson(
            "/api/v1/app/projects/{$this->projectA->id}/contracts/{$contract->id}",
            $data
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'name' => 'Updated Name',
            'status' => 'completed',
        ]);
    }

    public function test_it_soft_deletes_contract_for_project(): void
    {
        Sanctum::actingAs($this->userA);

        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/app/projects/{$this->projectA->id}/contracts/{$contract->id}"
        );

        $response->assertStatus(200);

        $this->assertSoftDeleted('contracts', [
            'id' => $contract->id,
        ]);
    }

    public function test_it_enforces_tenant_isolation_for_contracts(): void
    {
        Sanctum::actingAs($this->userA);

        // Create contract in tenant B's project
        $contractB = Contract::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $this->projectB->id,
        ]);

        // Try to access tenant B's contract from tenant A
        $response = $this->getJson(
            "/api/v1/app/projects/{$this->projectB->id}/contracts"
        );

        // Should return 404 because project doesn't belong to tenant A
        $response->assertStatus(404);

        // Try to update tenant B's contract
        $response = $this->patchJson(
            "/api/v1/app/projects/{$this->projectB->id}/contracts/{$contractB->id}",
            ['name' => 'Hacked']
        );

        $response->assertStatus(404);
    }
}
