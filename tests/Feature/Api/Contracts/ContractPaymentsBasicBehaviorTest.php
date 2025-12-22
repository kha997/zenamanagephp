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
 * Tests for Contract Payments API basic behavior
 * 
 * Round 36: Contract Payment Schedule Backend
 * 
 * Tests basic CRUD operations and business logic.
 * 
 * @group contracts
 * @group contract-payments
 */
class ContractPaymentsBasicBehaviorTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private User $user;
    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(77777);
        $this->setDomainName('contract-payments-basic');
        $this->setupDomainIsolation();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);
        
        // Create user with admin role
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $this->user->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);

        // Create contract
        $this->contract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'CT-TEST-001',
            'name' => 'Test Contract',
            'currency' => 'USD',
            'total_value' => 100000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);
    }

    /**
     * Test that user can create, update and delete contract payment within tenant
     */
    public function test_can_create_update_and_delete_contract_payment_within_tenant(): void
    {
        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Create payment
        $createResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-create-' . uniqid(),
        ])->postJson("/api/v1/app/contracts/{$this->contract->id}/payments", [
            'name' => 'First Payment',
            'due_date' => now()->addMonth()->toDateString(),
            'amount' => 30000.00,
            'currency' => 'USD',
            'status' => 'planned',
        ]);

        $createResponse->assertStatus(201);
        $createResponse->assertJson([
            'success' => true,
        ]);

        $paymentData = $createResponse->json('data');
        $paymentId = $paymentData['id'];

        // Assert DB has record with correct tenant, contract, created_by_id
        $this->assertDatabaseHas('contract_payments', [
            'id' => $paymentId,
            'tenant_id' => $this->tenant->id,
            'contract_id' => $this->contract->id,
            'name' => 'First Payment',
            'amount' => 30000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Update payment
        $updateResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-update-' . uniqid(),
        ])->putJson("/api/v1/app/contracts/{$this->contract->id}/payments/{$paymentId}", [
            'name' => 'Updated Payment',
            'amount' => 35000.00,
            'status' => 'due',
            'due_date' => now()->addDays(15)->toDateString(),
        ]);

        $updateResponse->assertStatus(200);
        $updateResponse->assertJson([
            'success' => true,
        ]);

        // Assert updated_by_id is set correctly
        $this->assertDatabaseHas('contract_payments', [
            'id' => $paymentId,
            'name' => 'Updated Payment',
            'amount' => 35000.00,
            'status' => 'due',
            'updated_by_id' => $this->user->id,
        ]);

        // Delete payment
        $deleteResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/contracts/{$this->contract->id}/payments/{$paymentId}");

        $deleteResponse->assertStatus(204);

        // Verify payment is soft deleted
        $this->assertSoftDeleted('contract_payments', [
            'id' => $paymentId,
        ]);
    }

    /**
     * Test that contract payment inherits contract currency by default
     */
    public function test_contract_payment_inherits_contract_currency_by_default(): void
    {
        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Create contract with VND currency
        $contractVND = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'CT-VND-001',
            'name' => 'VND Contract',
            'currency' => 'VND',
            'total_value' => 50000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Create payment without currency - should inherit from contract
        $createResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-create-currency-' . uniqid(),
        ])->postJson("/api/v1/app/contracts/{$contractVND->id}/payments", [
            'name' => 'Payment Without Currency',
            'due_date' => now()->addMonth()->toDateString(),
            'amount' => 10000.00,
            // currency not provided
        ]);

        $createResponse->assertStatus(201);
        $paymentData = $createResponse->json('data');
        $paymentId = $paymentData['id'];

        // Assert payment currency matches contract currency
        $this->assertDatabaseHas('contract_payments', [
            'id' => $paymentId,
            'currency' => 'VND', // Should inherit from contract
        ]);
    }

    /**
     * Test that payments are ordered by sort_order and due_date
     */
    public function test_payments_are_ordered_by_sort_order_and_due_date(): void
    {
        // Create multiple payments with different sort_order and due_date
        $payment1 = ContractPayment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $this->contract->id,
            'name' => 'Payment 1',
            'sort_order' => 2,
            'due_date' => now()->addMonths(2),
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        $payment2 = ContractPayment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $this->contract->id,
            'name' => 'Payment 2',
            'sort_order' => 1,
            'due_date' => now()->addMonth(),
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        $payment3 = ContractPayment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $this->contract->id,
            'name' => 'Payment 3',
            'sort_order' => 1,
            'due_date' => now()->addMonths(3),
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Get payments list
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/contracts/{$this->contract->id}/payments");

        $response->assertStatus(200);
        $payments = $response->json('data') ?? [];

        // Verify ordering: sort_order first (1, 1, 2), then due_date (month, 3 months, 2 months)
        // Expected order: payment2 (sort_order=1, due=+1 month), payment3 (sort_order=1, due=+3 months), payment1 (sort_order=2, due=+2 months)
        $paymentIds = array_column($payments, 'id');
        
        // Payment 2 should come before Payment 3 (same sort_order, but earlier due_date)
        $payment2Index = array_search($payment2->id, $paymentIds);
        $payment3Index = array_search($payment3->id, $paymentIds);
        $payment1Index = array_search($payment1->id, $paymentIds);

        $this->assertNotFalse($payment2Index, 'Payment 2 should be in response');
        $this->assertNotFalse($payment3Index, 'Payment 3 should be in response');
        $this->assertNotFalse($payment1Index, 'Payment 1 should be in response');

        // Payment 2 (sort_order=1, due=+1 month) should come before Payment 3 (sort_order=1, due=+3 months)
        $this->assertLessThan($payment3Index, $payment2Index, 'Payment 2 should come before Payment 3 (same sort_order, earlier due_date)');
        
        // Payment 3 (sort_order=1) should come before Payment 1 (sort_order=2)
        $this->assertLessThan($payment1Index, $payment3Index, 'Payment 3 should come before Payment 1 (lower sort_order)');
    }

    /**
     * Test that cannot create payments exceeding contract total_value
     * 
     * Round 37: Payment Hardening - Business invariant test
     */
    public function test_cannot_create_payments_exceeding_contract_total_value(): void
    {
        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Contract with total_value = 1000
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'CT-LIMIT-001',
            'name' => 'Limited Contract',
            'currency' => 'USD',
            'total_value' => 1000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Create first payment: 600
        $createResponse1 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-create-1-' . uniqid(),
        ])->postJson("/api/v1/app/contracts/{$contract->id}/payments", [
            'name' => 'Payment 1',
            'due_date' => now()->addMonth()->toDateString(),
            'amount' => 600.00,
        ]);

        $createResponse1->assertStatus(201);
        $payment1Id = $createResponse1->json('data.id');

        // Try to create second payment: 500 (total would be 1100 > 1000)
        $createResponse2 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-create-2-' . uniqid(),
        ])->postJson("/api/v1/app/contracts/{$contract->id}/payments", [
            'name' => 'Payment 2',
            'due_date' => now()->addMonths(2)->toDateString(),
            'amount' => 500.00,
        ]);

        // Should fail with 422 validation error
        $createResponse2->assertStatus(422);
        $createResponse2->assertJson([
            'ok' => false,
            'code' => 'PAYMENT_TOTAL_EXCEEDED',
        ]);
        
        // Check error envelope structure
        $responseData = $createResponse2->json();
        $this->assertArrayHasKey('ok', $responseData);
        $this->assertFalse($responseData['ok']);
        $this->assertArrayHasKey('code', $responseData);
        $this->assertEquals('PAYMENT_TOTAL_EXCEEDED', $responseData['code']);
        $this->assertArrayHasKey('details', $responseData);
        $this->assertArrayHasKey('validation', $responseData['details']);
        $this->assertArrayHasKey('amount', $responseData['details']['validation']);

        // Verify total payments in DB is still 600
        $totalPayments = ContractPayment::where('contract_id', $contract->id)
            ->whereNull('deleted_at')
            ->sum('amount');
        $this->assertEquals(600.00, (float) $totalPayments, 'Total payments should remain 600');
    }

    /**
     * Test that cannot update payment to exceed contract total_value
     * 
     * Round 37: Payment Hardening - Business invariant test
     */
    public function test_cannot_update_payment_to_exceed_contract_total_value(): void
    {
        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Contract with total_value = 1000
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'CT-UPDATE-001',
            'name' => 'Update Test Contract',
            'currency' => 'USD',
            'total_value' => 1000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Create two payments: P1 = 600, P2 = 300 (total = 900)
        $payment1 = ContractPayment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract->id,
            'name' => 'Payment 1',
            'amount' => 600.00,
            'due_date' => now()->addMonth(),
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        $payment2 = ContractPayment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract->id,
            'name' => 'Payment 2',
            'amount' => 300.00,
            'due_date' => now()->addMonths(2),
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Try to update P2 from 300 to 500 (new total = 600 + 500 = 1100 > 1000)
        $updateResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-update-exceed-' . uniqid(),
        ])->putJson("/api/v1/app/contracts/{$contract->id}/payments/{$payment2->id}", [
            'amount' => 500.00,
        ]);

        // Should fail with 422 validation error
        $updateResponse->assertStatus(422);
        $updateResponse->assertJson([
            'ok' => false,
            'code' => 'PAYMENT_TOTAL_EXCEEDED',
        ]);

        // Verify payment amount in DB is still 300
        $payment2->refresh();
        $this->assertEquals(300.00, (float) $payment2->amount, 'Payment 2 amount should remain 300');

        // Now update P2 to 400 (new total = 600 + 400 = 1000, should succeed)
        $updateResponse2 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-update-ok-' . uniqid(),
        ])->putJson("/api/v1/app/contracts/{$contract->id}/payments/{$payment2->id}", [
            'amount' => 400.00,
        ]);

        // Should succeed
        $updateResponse2->assertStatus(200);
        $updateResponse2->assertJson([
            'success' => true,
        ]);

        // Verify payment amount was updated
        $payment2->refresh();
        $this->assertEquals(400.00, (float) $payment2->amount, 'Payment 2 amount should be updated to 400');
    }

    /**
     * Test that invariant is skipped if contract total_value is null
     * 
     * Round 37: Payment Hardening - Business invariant test
     * 
     * Note: Since total_value column is NOT NULL in DB schema, we test the service logic
     * directly by creating a contract and then testing that the service method
     * correctly skips validation when total_value is null (in memory).
     */
    public function test_invariant_is_skipped_if_contract_total_value_is_null(): void
    {
        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Create contract with a very large total_value (effectively unlimited for testing)
        // In real scenario, null total_value would mean contract not finalized
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'CT-UNLIMITED-001',
            'name' => 'Unlimited Contract',
            'currency' => 'USD',
            'total_value' => 999999999999.00, // Very large value to simulate unlimited
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Test service logic directly: create a contract instance with null total_value in memory
        // This simulates the scenario where contract.total_value is null (not finalized)
        $service = app(\App\Services\Contracts\ContractPaymentService::class);
        
        // Create a contract instance with null total_value (in memory only, not persisted)
        $contractWithNullTotal = new Contract();
        $contractWithNullTotal->id = $contract->id;
        $contractWithNullTotal->tenant_id = $contract->tenant_id;
        $contractWithNullTotal->total_value = null; // Simulate null total_value
        
        // Verify that service allows creating payments when total_value is null
        // This should not throw an exception
        try {
            $payment = $service->createPaymentForContract(
                $contractWithNullTotal,
                [
                    'name' => 'Large Payment',
                    'due_date' => now()->addMonth()->toDateString(),
                    'amount' => 999999999.00,
                ],
                (string) $this->user->id
            );
            
            // If we get here, the invariant was correctly skipped
            $this->assertNotNull($payment);
            $this->assertEquals(999999999.00, (float) $payment->amount);
        } catch (\Exception $e) {
            $this->fail('Service should not throw exception when contract total_value is null. Error: ' . $e->getMessage());
        }
    }
}

