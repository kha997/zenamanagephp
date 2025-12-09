<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\ChangeOrder;
use App\Models\ChangeOrderLine;
use App\Models\Contract;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * ChangeOrderApiTest
 * 
 * Round 220: Change Orders for Contracts
 * 
 * Tests for change orders API endpoints with tenant isolation and CRUD operations
 * 
 * @group change-orders
 * @group api-v1
 */
class ChangeOrderApiTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected Project $projectA;
    protected Project $projectB;
    protected Contract $contractA;
    protected Contract $contractB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(220001);
        $this->setDomainName('change-order-api');
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

        // Create projects
        $this->projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Project A',
        ]);

        $this->projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Project B',
        ]);

        // Create contracts
        $this->contractA = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'code' => 'CT-A-001',
            'base_amount' => 10000000,
        ]);

        $this->contractB = Contract::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $this->projectB->id,
            'code' => 'CT-B-001',
            'base_amount' => 20000000,
        ]);
    }

    public function test_it_lists_change_orders_for_contract(): void
    {
        Sanctum::actingAs($this->userA);

        // Create change orders for contract A
        ChangeOrder::factory()->count(3)->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
        ]);

        // Create change order for contract B (should not appear)
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $this->projectB->id,
            'contract_id' => $this->contractB->id,
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/contracts/{$this->contractA->id}/change-orders");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'contract_id',
                        'code',
                        'title',
                        'status',
                        'amount_delta',
                    ]
                ]
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_it_creates_change_order_with_lines_for_contract(): void
    {
        Sanctum::actingAs($this->userA);

        $data = [
            'code' => 'CO-001',
            'title' => 'Test Change Order',
            'reason' => 'design_change',
            'status' => 'approved',
            'amount_delta' => 5000000,
            'effective_date' => '2025-12-15',
            'lines' => [
                [
                    'description' => 'Additional work item',
                    'amount_delta' => 5000000,
                ],
            ],
        ];

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->projectA->id}/contracts/{$this->contractA->id}/change-orders",
            $data
        );

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'code',
                    'title',
                    'status',
                    'amount_delta',
                    'lines',
                ]
            ]);

        $this->assertDatabaseHas('change_orders', [
            'contract_id' => $this->contractA->id,
            'code' => 'CO-001',
            'title' => 'Test Change Order',
            'status' => 'approved',
        ]);

        $changeOrder = ChangeOrder::where('code', 'CO-001')->first();
        $this->assertDatabaseHas('change_order_lines', [
            'change_order_id' => $changeOrder->id,
            'description' => 'Additional work item',
        ]);
    }

    public function test_it_computes_contract_current_amount_with_approved_change_orders(): void
    {
        Sanctum::actingAs($this->userA);

        // Create approved change order
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'status' => 'approved',
            'amount_delta' => 5000000,
        ]);

        // Create draft change order (should not be included)
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'status' => 'draft',
            'amount_delta' => 2000000,
        ]);

        // Refresh contract to get current_amount
        $this->contractA->refresh();

        // base_amount (10000000) + approved CO (5000000) = 15000000
        $this->assertEquals(15000000, $this->contractA->current_amount);

        // Verify via API
        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/contracts/{$this->contractA->id}");
        $response->assertStatus(200);
        $this->assertEquals(15000000, $response->json('data.current_amount'));
    }

    public function test_it_updates_change_order_and_rebuilds_lines(): void
    {
        Sanctum::actingAs($this->userA);

        $changeOrder = ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'code' => 'CO-ORIG',
            'title' => 'Original Title',
        ]);

        ChangeOrderLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'change_order_id' => $changeOrder->id,
            'description' => 'Original Line',
        ]);

        $data = [
            'title' => 'Updated Title',
            'status' => 'approved',
            'lines' => [
                [
                    'description' => 'New Line Item',
                    'amount_delta' => 3000000,
                ],
            ],
        ];

        $response = $this->patchJson(
            "/api/v1/app/projects/{$this->projectA->id}/contracts/{$this->contractA->id}/change-orders/{$changeOrder->id}",
            $data
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('change_orders', [
            'id' => $changeOrder->id,
            'title' => 'Updated Title',
            'status' => 'approved',
        ]);

        // Old line should be soft deleted
        $this->assertSoftDeleted('change_order_lines', [
            'change_order_id' => $changeOrder->id,
            'description' => 'Original Line',
        ]);

        // New line should exist
        $this->assertDatabaseHas('change_order_lines', [
            'change_order_id' => $changeOrder->id,
            'description' => 'New Line Item',
        ]);
    }

    public function test_it_soft_deletes_change_order_for_contract(): void
    {
        Sanctum::actingAs($this->userA);

        $changeOrder = ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $this->contractA->id,
            'status' => 'approved',
            'amount_delta' => 5000000,
        ]);

        $response = $this->deleteJson(
            "/api/v1/app/projects/{$this->projectA->id}/contracts/{$this->contractA->id}/change-orders/{$changeOrder->id}"
        );

        $response->assertStatus(200);

        $this->assertSoftDeleted('change_orders', [
            'id' => $changeOrder->id,
        ]);

        // Verify it no longer appears in index
        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/contracts/{$this->contractA->id}/change-orders");
        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_it_enforces_tenant_isolation_for_change_orders(): void
    {
        Sanctum::actingAs($this->userA);

        // Create change order in tenant B's contract
        $changeOrderB = ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $this->projectB->id,
            'contract_id' => $this->contractB->id,
        ]);

        // Try to access tenant B's change order from tenant A
        $response = $this->getJson(
            "/api/v1/app/projects/{$this->projectB->id}/contracts/{$this->contractB->id}/change-orders"
        );

        // Should return 404 because project doesn't belong to tenant A
        $response->assertStatus(404);

        // Try to update tenant B's change order
        $response = $this->patchJson(
            "/api/v1/app/projects/{$this->projectB->id}/contracts/{$this->contractB->id}/change-orders/{$changeOrderB->id}",
            ['title' => 'Hacked']
        );

        $response->assertStatus(404);
    }
}
