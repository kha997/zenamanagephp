<?php declare(strict_types=1);

namespace Tests\Feature\Api\Contracts;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Contract;
use App\Models\ContractPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Contract Payments API cross-tenant isolation
 * 
 * Round 36: Contract Payment Schedule Backend
 * 
 * Tests that contract payments endpoints properly enforce tenant isolation.
 * 
 * @group contracts
 * @group contract-payments
 * @group tenant-isolation
 */
class ContractPaymentsIsolationTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private Contract $contractA;
    private Contract $contractB;
    private ContractPayment $paymentA;
    private ContractPayment $paymentB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(99999);
        $this->setDomainName('contract-payments-isolation');
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

        // Create payment A in contract A (tenant A)
        $this->paymentA = ContractPayment::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $this->contractA->id,
            'name' => 'Tenant A Payment',
            'amount' => 10000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);

        // Create payment B in contract B (tenant B)
        $this->paymentB = ContractPayment::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'contract_id' => $this->contractB->id,
            'name' => 'Tenant B Payment',
            'amount' => 20000.00,
            'created_by_id' => $this->userB->id,
            'updated_by_id' => $this->userB->id,
        ]);
    }

    /**
     * Test that user tenant A cannot view payments of contract in another tenant
     */
    public function test_user_cannot_view_payments_of_contract_in_another_tenant(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // User A should NOT be able to GET tenant B's contract payments
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/contracts/{$this->contractB->id}/payments");

        // Should return 404 (not found) or 403 (forbidden) - depends on implementation
        $this->assertContains(
            $response->status(),
            [403, 404],
            'User from tenant A should not be able to access tenant B contract payments'
        );
    }

    /**
     * Test that user tenant A cannot modify payments of contract in another tenant
     */
    public function test_user_cannot_modify_payments_of_contract_in_another_tenant(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // User A should NOT be able to DELETE tenant B's payment
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/contracts/{$this->contractB->id}/payments/{$this->paymentB->id}");

        // Should return 404 (not found) or 403 (forbidden)
        $this->assertContains(
            $response->status(),
            [403, 404],
            'User from tenant A should not be able to delete tenant B payment'
        );

        // Verify payment B still exists
        $this->assertDatabaseHas('contract_payments', [
            'id' => $this->paymentB->id,
            'tenant_id' => $this->tenantB->id,
        ]);

        // User A should NOT be able to UPDATE tenant B's payment
        $updateResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-update-' . uniqid(),
        ])->putJson("/api/v1/app/contracts/{$this->contractB->id}/payments/{$this->paymentB->id}", [
            'name' => 'Hacked Payment',
        ]);

        $this->assertContains(
            $updateResponse->status(),
            [403, 404],
            'User from tenant A should not be able to update tenant B payment'
        );
    }

    /**
     * Test that payments query is scoped to contract and tenant
     */
    public function test_payments_query_is_scoped_to_contract_and_tenant(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // User A should only see payments from contract A in tenant A
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/contracts/{$this->contractA->id}/payments");

        $response->assertStatus(200);
        $payments = $response->json('data') ?? [];
        
        // Verify all returned payments belong to contract A in tenant A
        foreach ($payments as $payment) {
            $paymentTenantId = $payment['tenant_id'] ?? null;
            $paymentContractId = $payment['contract_id'] ?? null;
            
            $this->assertEquals(
                $this->tenantA->id,
                $paymentTenantId,
                'Payments should only be from tenant A'
            );
            
            $this->assertEquals(
                $this->contractA->id,
                $paymentContractId,
                'Payments should only be from contract A'
            );
        }
        
        // Verify tenant B's payment is not included
        $paymentIds = array_column($payments, 'id');
        $this->assertNotContains($this->paymentB->id, $paymentIds, 'Tenant B payment should not be visible');
    }

    /**
     * Test that user cannot create payment for contract in another tenant
     */
    public function test_user_cannot_create_payment_for_contract_in_another_tenant(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // User A should NOT be able to create payment for tenant B's contract
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-create-' . uniqid(),
        ])->postJson("/api/v1/app/contracts/{$this->contractB->id}/payments", [
            'name' => 'Hacked Payment',
            'due_date' => now()->addMonth()->toDateString(),
            'amount' => 5000.00,
        ]);

        // Should return 404 (not found) or 403 (forbidden)
        $this->assertContains(
            $response->status(),
            [403, 404],
            'User should not be able to create payment for contract in different tenant'
        );
    }

    /**
     * Test that soft-deleted payment cannot be resolved via route binding
     * 
     * Round 46: Hardening & Polish - Soft delete route binding
     */
    public function test_soft_deleted_payment_cannot_be_resolved_via_route_binding(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // Soft delete payment A
        $this->paymentA->delete();

        // Verify payment is soft-deleted
        $this->assertSoftDeleted('contract_payments', [
            'id' => $this->paymentA->id,
        ]);

        // Try to access the payment via route binding (update endpoint)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-update-' . uniqid(),
        ])->putJson("/api/v1/app/contracts/{$this->contractA->id}/payments/{$this->paymentA->id}", [
            'name' => 'Updated Payment',
        ]);

        // Should return 404 (not found) because route binding returns null for soft-deleted records
        $response->assertStatus(404);

        // Try to delete the already soft-deleted payment
        $deleteResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/contracts/{$this->contractA->id}/payments/{$this->paymentA->id}");

        // Should return 404 (not found)
        $deleteResponse->assertStatus(404);
    }
}

