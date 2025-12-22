<?php declare(strict_types=1);

namespace Tests\Feature\Api\Contracts;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Contract;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Contracts API basic behavior
 * 
 * Round 33: MVP Contract Backend
 * 
 * Tests basic CRUD operations and business logic.
 * 
 * @group contracts
 */
class ContractsBasicBehaviorTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(77777);
        $this->setDomainName('contracts-basic');
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
    }

    /**
     * Test that user can create and update contract within tenant
     */
    public function test_can_create_and_update_contract_within_tenant(): void
    {
        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Create client and project in same tenant
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Create contract
        $createResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-create-' . uniqid(),
        ])->postJson('/api/v1/app/contracts', [
            'code' => 'CT-TEST-001',
            'name' => 'Test Contract',
            'status' => 'draft',
            'client_id' => $client->id,
            'project_id' => $project->id,
            'total_value' => 50000.00,
            'currency' => 'USD',
            'signed_at' => now()->toDateTimeString(),
            'effective_from' => now()->toDateString(),
            'effective_to' => now()->addYear()->toDateString(),
            'notes' => 'Test contract notes',
        ]);

        $createResponse->assertStatus(201);
        $createResponse->assertJson([
            'success' => true,
        ]);

        $contractData = $createResponse->json('data');
        $contractId = $contractData['id'];

        // Assert DB has record with correct tenant, created_by_id
        $this->assertDatabaseHas('contracts', [
            'id' => $contractId,
            'tenant_id' => $this->tenant->id,
            'code' => 'CT-TEST-001',
            'name' => 'Test Contract',
            'client_id' => $client->id,
            'project_id' => $project->id,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Update contract
        $updateResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-update-' . uniqid(),
        ])->putJson("/api/v1/app/contracts/{$contractId}", [
            'name' => 'Updated Test Contract',
            'status' => 'active',
            'total_value' => 60000.00,
        ]);

        $updateResponse->assertStatus(200);
        $updateResponse->assertJson([
            'success' => true,
        ]);

        // Assert updated_by_id is set correctly
        $this->assertDatabaseHas('contracts', [
            'id' => $contractId,
            'name' => 'Updated Test Contract',
            'status' => 'active',
            'total_value' => 60000.00,
            'updated_by_id' => $this->user->id,
        ]);
    }

    /**
     * Test that contract code must be unique per tenant
     */
    public function test_contract_code_must_be_unique_per_tenant(): void
    {
        // Create first contract
        $contract1 = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'CT-UNIQUE-001',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Try to create second contract with same code in same tenant - should fail
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-create-duplicate-' . uniqid(),
        ])->postJson('/api/v1/app/contracts', [
            'code' => 'CT-UNIQUE-001', // Same code
            'name' => 'Duplicate Code Contract',
            'total_value' => 10000.00,
        ]);

        $response->assertStatus(422);
        // Check if validation errors exist (standard Laravel format or ErrorEnvelopeService format)
        $responseData = $response->json();
        if (isset($responseData['errors']['code']) || isset($responseData['errors'])) {
            $response->assertJsonValidationErrors(['code']);
        } elseif (isset($responseData['details']['validation']['code']) || isset($responseData['details']['validation'])) {
            // ErrorEnvelopeService format
            $this->assertArrayHasKey('details', $responseData);
            $this->assertArrayHasKey('validation', $responseData['details']);
            $this->assertArrayHasKey('code', $responseData['details']['validation']);
        } else {
            // Alternative format check
            $this->assertTrue(
                isset($responseData['errors']) || isset($responseData['error']) || isset($responseData['details']['validation']),
                'Response should contain validation errors'
            );
        }
    }

    /**
     * Test that contract can be deleted (soft delete)
     */
    public function test_contract_can_be_deleted(): void
    {
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Delete contract
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/contracts/{$contract->id}");

        $response->assertStatus(204);

        // Verify contract is soft deleted
        $this->assertSoftDeleted('contracts', [
            'id' => $contract->id,
        ]);
    }
}

