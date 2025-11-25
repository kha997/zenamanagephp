<?php declare(strict_types=1);

namespace Tests\Feature\Api\Contracts;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Contracts API cross-tenant isolation
 * 
 * Round 33: MVP Contract Backend
 * 
 * Tests that contracts endpoints properly enforce tenant isolation.
 * 
 * @group contracts
 * @group tenant-isolation
 */
class ContractsIsolationTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private Contract $contractA;
    private Contract $contractB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(99999);
        $this->setDomainName('contracts-isolation');
        $this->setupDomainIsolation();
        
        // Create tenant A
        $this->tenantA = Tenant::factory()->create([
            'name' => 'Test Tenant A',
            'slug' => 'test-tenant-a-' . uniqid(),
        ]);
        
        // Create tenant B
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Test Tenant B',
            'slug' => 'test-tenant-b-' . uniqid(),
        ]);
        
        // Create user A in tenant A
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email_verified_at' => now(),
        ]);
        
        $this->userA->tenants()->attach($this->tenantA->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        // Create user B in tenant B
        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'email_verified_at' => now(),
        ]);
        
        $this->userB->tenants()->attach($this->tenantB->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        // Create contract A in tenant A
        $this->contractA = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-A-001',
            'name' => 'Tenant A Contract',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Create contract B in tenant B
        $this->contractB = Contract::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'code' => 'CT-B-001',
            'name' => 'Tenant B Contract',
            'created_by_id' => $this->userB->id,
            'updated_by_id' => $this->userB->id,
        ]);
    }

    /**
     * Test that user tenant A can only see contracts from tenant A
     */
    public function test_user_tenant_a_can_only_see_tenant_a_contracts(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // User A should only see contracts from tenant A
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/contracts');

        $response->assertStatus(200);
        $contracts = $response->json('data') ?? [];
        
        // Verify all returned contracts belong to tenant A
        foreach ($contracts as $contract) {
            $contractTenantId = $contract['tenant_id'] ?? null;
            $this->assertEquals(
                $this->tenantA->id,
                $contractTenantId,
                'Contracts should only be from tenant A'
            );
        }
        
        // Verify tenant B's contract is not included
        $contractIds = array_column($contracts, 'id');
        $this->assertNotContains($this->contractB->id, $contractIds, 'Tenant B contract should not be visible');
    }

    /**
     * Test that user tenant A cannot access tenant B's contract
     */
    public function test_user_tenant_a_cannot_access_tenant_b_contract(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // User A should NOT be able to GET tenant B's contract
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/contracts/{$this->contractB->id}");

        // Should return 404 (not found) or 403 (forbidden) - depends on implementation
        $this->assertContains(
            $response->status(),
            [403, 404],
            'User from tenant A should not be able to access tenant B contract'
        );
    }

    /**
     * Test that user tenant A cannot delete tenant B's contract
     */
    public function test_user_tenant_a_cannot_delete_tenant_b_contract(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // User A should NOT be able to DELETE tenant B's contract
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/contracts/{$this->contractB->id}");

        // Should return 404 (not found) or 403 (forbidden)
        $this->assertContains(
            $response->status(),
            [403, 404],
            'User from tenant A should not be able to delete tenant B contract'
        );

        // Verify contract B still exists
        $this->assertDatabaseHas('contracts', [
            'id' => $this->contractB->id,
            'tenant_id' => $this->tenantB->id,
        ]);
    }

    /**
     * Test that user cannot create contract with client_id from different tenant
     */
    public function test_user_cannot_create_contract_with_cross_tenant_client(): void
    {
        // Create a client in tenant B
        $clientB = \App\Models\Client::factory()->create([
            'tenant_id' => $this->tenantB->id,
        ]);

        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // User A should NOT be able to create contract with tenant B's client
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-create-' . uniqid(),
        ])->postJson('/api/v1/app/contracts', [
            'code' => 'CT-CROSS-' . uniqid(),
            'name' => 'Cross Tenant Contract',
            'total_value' => 10000.00,
            'client_id' => $clientB->id,
        ]);

        // Should return 422 (validation error) or 403 (forbidden)
        $this->assertContains(
            $response->status(),
            [403, 422],
            'User should not be able to create contract with client from different tenant'
        );
    }
}

