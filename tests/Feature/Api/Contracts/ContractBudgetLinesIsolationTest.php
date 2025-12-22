<?php declare(strict_types=1);

namespace Tests\Feature\Api\Contracts;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Contract;
use App\Models\ContractBudgetLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Contract Budget Lines API cross-tenant isolation
 * 
 * Round 43: Cost Control / Budget vs Actual (Backend-only Foundation)
 * 
 * Tests that contract budget lines endpoints properly enforce tenant isolation.
 * 
 * @group contracts
 * @group contract-budget-lines
 * @group tenant-isolation
 */
class ContractBudgetLinesIsolationTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private Contract $contractA;
    private Contract $contractB;
    private ContractBudgetLine $lineA;
    private ContractBudgetLine $lineB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(99999);
        $this->setDomainName('contract-budget-lines-isolation');
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

        // Create budget line A in contract A (tenant A)
        $this->lineA = ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $this->contractA->id,
            'name' => 'Tenant A Budget Line',
            'total_amount' => 10000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);

        // Create budget line B in contract B (tenant B)
        $this->lineB = ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'contract_id' => $this->contractB->id,
            'name' => 'Tenant B Budget Line',
            'total_amount' => 20000.00,
            'created_by_id' => $this->userB->id,
            'updated_by_id' => $this->userB->id,
        ]);
    }

    /**
     * Test that user tenant A cannot view budget lines of contract in another tenant
     */
    public function test_user_cannot_view_budget_lines_of_contract_in_another_tenant(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // User A should NOT be able to GET tenant B's contract budget lines
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/contracts/{$this->contractB->id}/budget-lines");

        // Should return 404 (not found) or 403 (forbidden) - depends on implementation
        $this->assertContains(
            $response->status(),
            [403, 404],
            'User from tenant A should not be able to access tenant B contract budget lines'
        );
    }

    /**
     * Test that user tenant A cannot modify budget lines of contract in another tenant
     */
    public function test_user_cannot_modify_budget_lines_of_contract_in_another_tenant(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // User A should NOT be able to DELETE tenant B's budget line
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/contracts/{$this->contractB->id}/budget-lines/{$this->lineB->id}");

        // Should return 404 (not found) or 403 (forbidden)
        $this->assertContains(
            $response->status(),
            [403, 404],
            'User from tenant A should not be able to delete tenant B budget line'
        );

        // Verify budget line B still exists
        $this->assertDatabaseHas('contract_budget_lines', [
            'id' => $this->lineB->id,
            'tenant_id' => $this->tenantB->id,
        ]);

        // User A should NOT be able to UPDATE tenant B's budget line
        $updateResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-update-' . uniqid(),
        ])->putJson("/api/v1/app/contracts/{$this->contractB->id}/budget-lines/{$this->lineB->id}", [
            'name' => 'Hacked Budget Line',
        ]);

        $this->assertContains(
            $updateResponse->status(),
            [403, 404],
            'User from tenant A should not be able to update tenant B budget line'
        );
    }

    /**
     * Test that budget lines query is scoped to contract and tenant
     */
    public function test_budget_lines_query_is_scoped_to_contract_and_tenant(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // User A should only see budget lines from contract A in tenant A
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/contracts/{$this->contractA->id}/budget-lines");

        $response->assertStatus(200);
        $budgetLines = $response->json('data') ?? [];
        
        // Verify all returned budget lines belong to contract A in tenant A
        foreach ($budgetLines as $line) {
            $lineTenantId = $line['tenant_id'] ?? null;
            $lineContractId = $line['contract_id'] ?? null;
            
            $this->assertEquals(
                $this->tenantA->id,
                $lineTenantId,
                'Budget lines should only be from tenant A'
            );
            
            $this->assertEquals(
                $this->contractA->id,
                $lineContractId,
                'Budget lines should only be from contract A'
            );
        }
        
        // Verify tenant B's budget line is not included
        $lineIds = array_column($budgetLines, 'id');
        $this->assertNotContains($this->lineB->id, $lineIds, 'Tenant B budget line should not be visible');
    }

    /**
     * Test that user cannot create budget line for contract in another tenant
     */
    public function test_user_cannot_create_budget_line_for_contract_in_another_tenant(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // User A should NOT be able to create budget line for tenant B's contract
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-create-' . uniqid(),
        ])->postJson("/api/v1/app/contracts/{$this->contractB->id}/budget-lines", [
            'name' => 'Hacked Budget Line',
            'total_amount' => 5000.00,
        ]);

        // Should return 404 (not found) or 403 (forbidden)
        $this->assertContains(
            $response->status(),
            [403, 404],
            'User should not be able to create budget line for contract in different tenant'
        );
    }

    /**
     * Test that soft-deleted budget line cannot be resolved via route binding
     * 
     * Round 46: Hardening & Polish - Soft delete route binding
     */
    public function test_soft_deleted_budget_line_cannot_be_resolved_via_route_binding(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // Soft delete budget line A
        $this->lineA->delete();

        // Verify budget line is soft-deleted
        $this->assertSoftDeleted('contract_budget_lines', [
            'id' => $this->lineA->id,
        ]);

        // Try to access the budget line via route binding (update endpoint)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-update-' . uniqid(),
        ])->putJson("/api/v1/app/contracts/{$this->contractA->id}/budget-lines/{$this->lineA->id}", [
            'name' => 'Updated Budget Line',
        ]);

        // Should return 404 (not found) because route binding returns null for soft-deleted records
        $response->assertStatus(404);

        // Try to delete the already soft-deleted budget line
        $deleteResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/contracts/{$this->contractA->id}/budget-lines/{$this->lineA->id}");

        // Should return 404 (not found)
        $deleteResponse->assertStatus(404);
    }
}

