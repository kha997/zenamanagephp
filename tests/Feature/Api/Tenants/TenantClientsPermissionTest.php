<?php declare(strict_types=1);

namespace Tests\Feature\Api\Tenants;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Clients API permission enforcement
 * 
 * Tests that clients endpoints properly enforce tenant.permission middleware
 * for view endpoints (GET) and mutation endpoints (POST, PUT, PATCH, DELETE).
 * 
 * Round 27: Security / RBAC Hardening
 * 
 * @group tenant-clients
 * @group tenant-permissions
 */
class TenantClientsPermissionTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private Tenant $tenantB;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(33333);
        $this->setDomainName('tenant-clients-permission');
        $this->setupDomainIsolation();
        
        // Create tenant A
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant A',
            'slug' => 'test-tenant-a-' . uniqid(),
        ]);
        
        // Create tenant B for isolation tests
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Test Tenant B',
            'slug' => 'test-tenant-b-' . uniqid(),
        ]);
        
        // Create a client in tenant A
        $this->client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Client',
            'email' => 'client@example.com',
        ]);
    }

    /**
     * Test that GET /api/v1/app/clients requires tenant.view_projects permission
     */
    public function test_get_clients_requires_view_permission(): void
    {
        $roles = ['owner', 'admin', 'member', 'viewer'];
        
        foreach ($roles as $role) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'email_verified_at' => now(),
            ]);
            
            $user->tenants()->attach($this->tenant->id, [
                'role' => $role,
                'is_default' => true,
            ]);
            
            Sanctum::actingAs($user);
            $token = $user->createToken('test-token')->plainTextToken;
            
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/app/clients');
            
            $response->assertStatus(200, "Role {$role} should be able to GET clients (has tenant.view_projects)");
            $response->assertJsonStructure([
                'success',
                'data',
            ]);
        }
    }

    /**
     * Test that GET /api/v1/app/clients/{id} requires tenant.view_projects permission
     */
    public function test_get_client_requires_view_permission(): void
    {
        $viewer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $viewer->tenants()->attach($this->tenant->id, [
            'role' => 'viewer',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($viewer);
        $token = $viewer->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/clients/{$this->client->id}");
        
        $response->assertStatus(200);
    }

    /**
     * Test that POST /api/v1/app/clients requires tenant.manage_projects permission
     */
    public function test_create_client_requires_manage_permission(): void
    {
        $roles = ['owner', 'admin'];
        
        foreach ($roles as $role) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'email_verified_at' => now(),
            ]);
            
            $user->tenants()->attach($this->tenant->id, [
                'role' => $role,
                'is_default' => true,
            ]);
            
            Sanctum::actingAs($user);
            $token = $user->createToken('test-token')->plainTextToken;
            
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-client-' . $role . '-' . uniqid(),
            ])->postJson('/api/v1/app/clients', [
                'name' => 'New Client ' . uniqid(),
                'email' => 'newclient' . uniqid() . '@example.com',
                'lifecycle_stage' => 'lead',
            ]);
            
            $response->assertStatus(201, "Role {$role} should be able to create client");
            $response->assertJsonStructure([
                'success',
                'data',
            ]);
        }
    }

    /**
     * Test that POST /api/v1/app/clients returns 403 without permission
     */
    public function test_create_client_returns_403_without_permission(): void
    {
        $roles = ['member', 'viewer'];
        
        foreach ($roles as $role) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'email_verified_at' => now(),
            ]);
            
            $user->tenants()->attach($this->tenant->id, [
                'role' => $role,
                'is_default' => true,
            ]);
            
            Sanctum::actingAs($user);
            $token = $user->createToken('test-token')->plainTextToken;
            
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-client-' . $role . '-' . uniqid(),
            ])->postJson('/api/v1/app/clients', [
                'name' => 'New Client',
                'email' => 'newclient@example.com',
            ]);
            
            $response->assertStatus(403, "Role {$role} should NOT be able to create client");
            $response->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ]);
        }
    }

    /**
     * Test that PUT /api/v1/app/clients/{id} requires tenant.manage_projects permission
     */
    public function test_update_client_requires_manage_permission(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $admin->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($admin);
        $token = $admin->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-update-' . uniqid(),
        ])->putJson("/api/v1/app/clients/{$this->client->id}", [
            'name' => 'Updated Client Name',
            'email' => $this->client->email,
        ]);
        
        $response->assertStatus(200);
    }

    /**
     * Test that PUT /api/v1/app/clients/{id} returns 403 without permission
     */
    public function test_update_client_returns_403_without_permission(): void
    {
        $viewer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $viewer->tenants()->attach($this->tenant->id, [
            'role' => 'viewer',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($viewer);
        $token = $viewer->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-update-' . uniqid(),
        ])->putJson("/api/v1/app/clients/{$this->client->id}", [
            'name' => 'Updated Client Name',
        ]);
        
        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that DELETE /api/v1/app/clients/{id} requires tenant.manage_projects permission
     */
    public function test_delete_client_requires_manage_permission(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $admin->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        $clientToDelete = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        
        Sanctum::actingAs($admin);
        $token = $admin->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/clients/{$clientToDelete->id}");
        
        $response->assertStatus(200);
    }

    /**
     * Test tenant isolation - clients from tenant A not visible in tenant B
     */
    public function test_tenant_isolation(): void
    {
        // Create client in tenant B
        $clientB = Client::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Tenant B Client',
        ]);
        
        // Create user in tenant A
        $userA = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $userA->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($userA);
        $token = $userA->createToken('test-token')->plainTextToken;
        
        // User A should only see clients from tenant A
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/clients');
        
        $response->assertStatus(200);
        $clients = $response->json('data', []);
        
        // Verify client B is not in the list
        $clientIds = array_column($clients, 'id');
        $this->assertNotContains($clientB->id, $clientIds, 'Tenant B client should not be visible in tenant A');
        
        // Verify user A cannot access client B directly
        $response2 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/clients/{$clientB->id}");
        
        // Should return 403 or 404 (depending on implementation)
        $this->assertContains($response2->status(), [403, 404], 'Should not be able to access tenant B client');
    }

    /**
     * Test that tenant A cannot modify client of tenant B
     */
    public function test_cannot_modify_client_of_another_tenant(): void
    {
        // Create client in tenant B
        $clientB = Client::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Tenant B Client',
        ]);
        
        // Create owner of tenant A
        $userOwnerA = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $userOwnerA->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($userOwnerA);
        $token = $userOwnerA->createToken('test-token')->plainTextToken;
        
        // Attempt to update client of tenant B
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-cross-tenant-' . uniqid(),
        ])->putJson("/api/v1/app/clients/{$clientB->id}", [
            'name' => 'Hacked Name',
        ]);
        
        // Should return 403 or 404
        $this->assertContains($response->status(), [403, 404], 'Should not be able to update tenant B client');
        
        // Verify client B is unchanged
        $clientB->refresh();
        $this->assertEquals('Tenant B Client', $clientB->name, 'Client should not be modified');
    }
}

