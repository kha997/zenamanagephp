<?php declare(strict_types=1);

namespace Tests\Feature\Api\Tenants;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Quote;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Quotes API permission enforcement
 * 
 * Tests that quotes endpoints properly enforce tenant.permission middleware
 * for view endpoints (GET) and mutation endpoints (POST, PUT, PATCH, DELETE).
 * 
 * Round 27: Security / RBAC Hardening
 * 
 * @group tenant-quotes
 * @group tenant-permissions
 */
class TenantQuotesPermissionTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private Tenant $tenantB;
    private Quote $quote;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(44444);
        $this->setDomainName('tenant-quotes-permission');
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
        
        // Create a quote in tenant A
        $this->quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'title' => 'Test Quote',
            'status' => 'draft',
        ]);
    }

    /**
     * Test that GET /api/v1/app/quotes requires tenant.view_projects permission
     */
    public function test_get_quotes_requires_view_permission(): void
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
            ])->getJson('/api/v1/app/quotes');
            
            $response->assertStatus(200, "Role {$role} should be able to GET quotes (has tenant.view_projects)");
            $response->assertJsonStructure([
                'success',
                'data',
            ]);
        }
    }

    /**
     * Test that GET /api/v1/app/quotes/{id} requires tenant.view_projects permission
     */
    public function test_get_quote_requires_view_permission(): void
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
        ])->getJson("/api/v1/app/quotes/{$this->quote->id}");
        
        $response->assertStatus(200);
    }

    /**
     * Test that POST /api/v1/app/quotes requires tenant.manage_projects permission
     */
    public function test_create_quote_requires_manage_permission(): void
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
                'Idempotency-Key' => 'test-quote-' . $role . '-' . uniqid(),
            ])->postJson('/api/v1/app/quotes', [
                'title' => 'New Quote ' . uniqid(),
                'client_id' => $this->client->id,
                'amount' => 1000,
            ]);
            
            $response->assertStatus(201, "Role {$role} should be able to create quote");
            $response->assertJsonStructure([
                'success',
                'data',
            ]);
        }
    }

    /**
     * Test that POST /api/v1/app/quotes returns 403 without permission
     */
    public function test_create_quote_returns_403_without_permission(): void
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
                'Idempotency-Key' => 'test-quote-' . $role . '-' . uniqid(),
            ])->postJson('/api/v1/app/quotes', [
                'title' => 'New Quote',
                'client_id' => $this->client->id,
                'amount' => 1000,
            ]);
            
            $response->assertStatus(403, "Role {$role} should NOT be able to create quote");
            $response->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ]);
        }
    }

    /**
     * Test that PUT /api/v1/app/quotes/{id} requires tenant.manage_projects permission
     */
    public function test_update_quote_requires_manage_permission(): void
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
        ])->putJson("/api/v1/app/quotes/{$this->quote->id}", [
            'title' => 'Updated Quote Name',
            'client_id' => $this->client->id,
            'amount' => 2000,
        ]);
        
        $response->assertStatus(200);
    }

    /**
     * Test that PUT /api/v1/app/quotes/{id} returns 403 without permission
     */
    public function test_update_quote_returns_403_without_permission(): void
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
        ])->putJson("/api/v1/app/quotes/{$this->quote->id}", [
            'title' => 'Updated Quote Name',
        ]);
        
        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that POST /api/v1/app/quotes/{quote}/send requires tenant.manage_projects permission
     */
    public function test_send_quote_requires_manage_permission(): void
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
            'Idempotency-Key' => 'test-send-' . uniqid(),
        ])->postJson("/api/v1/app/quotes/{$this->quote->id}/send");
        
        $response->assertStatus(200);
    }

    /**
     * Test that DELETE /api/v1/app/quotes/{id} requires tenant.manage_projects permission
     */
    public function test_delete_quote_requires_manage_permission(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $admin->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        $quoteToDelete = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
        ]);
        
        Sanctum::actingAs($admin);
        $token = $admin->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/quotes/{$quoteToDelete->id}");
        
        $response->assertStatus(200);
    }

    /**
     * Test tenant isolation - quotes from tenant A not visible in tenant B
     */
    public function test_tenant_isolation(): void
    {
        // Create client and quote in tenant B
        $clientB = Client::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Tenant B Client',
        ]);
        
        $quoteB = Quote::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'client_id' => $clientB->id,
            'title' => 'Tenant B Quote',
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
        
        // User A should only see quotes from tenant A
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/quotes');
        
        $response->assertStatus(200);
        $quotes = $response->json('data', []);
        
        // Verify quote B is not in the list
        $quoteIds = array_column($quotes, 'id');
        $this->assertNotContains($quoteB->id, $quoteIds, 'Tenant B quote should not be visible in tenant A');
        
        // Verify user A cannot access quote B directly
        $response2 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/quotes/{$quoteB->id}");
        
        // Should return 403 or 404 (depending on implementation)
        $this->assertContains($response2->status(), [403, 404], 'Should not be able to access tenant B quote');
    }

    /**
     * Test that tenant A cannot modify quote of tenant B
     */
    public function test_cannot_modify_quote_of_another_tenant(): void
    {
        // Create client and quote in tenant B
        $clientB = Client::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Tenant B Client',
        ]);
        
        $quoteB = Quote::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'client_id' => $clientB->id,
            'title' => 'Tenant B Quote',
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
        
        // Attempt to update quote of tenant B
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-cross-tenant-' . uniqid(),
        ])->putJson("/api/v1/app/quotes/{$quoteB->id}", [
            'title' => 'Hacked Name',
        ]);
        
        // Should return 403 or 404
        $this->assertContains($response->status(), [403, 404], 'Should not be able to update tenant B quote');
        
        // Verify quote B is unchanged
        $quoteB->refresh();
        $this->assertEquals('Tenant B Quote', $quoteB->title, 'Quote should not be modified');
    }
}

