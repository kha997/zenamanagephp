<?php declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Template;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tenancy Resolution Via HTTP Test
 * 
 * Round 197: Verify that tenant resolution works correctly through HTTP requests
 * 
 * This test acts as a "canary" to ensure that:
 * - actingAsTenantUser() helper correctly sets up tenant context
 * - TenancyService.resolveActiveTenantId() works in HTTP request context
 * - Controllers can correctly resolve tenant ID via getTenantId()
 * - Templates are correctly scoped to the resolved tenant
 */
class TenancyResolutionViaHttpTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(197001);
        $this->setDomainName('tenancy-resolution-http');
        $this->setupDomainIsolation();
    }

    /**
     * Test that authenticated tenant user resolves same tenant via HTTP
     * 
     * This test verifies the full stack:
     * 1. User is created with tenant_id and attached via pivot with is_default = true
     * 2. User is authenticated via Sanctum
     * 3. HTTP request is made to an endpoint that uses getTenantId()
     * 4. The resolved tenant ID matches the expected tenant
     */
    public function test_authenticated_tenant_user_resolves_same_tenant_via_http(): void
    {
        // Create tenant and user with pivot attachment (canonical setup)
        $tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);
        
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'pm',
        ]);
        
        // Attach user to tenant via pivot with is_default = true
        $user->tenants()->attach($tenant->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);
        
        $user->refresh();
        
        // Authenticate user
        Sanctum::actingAs($user, [], 'sanctum');
        
        // Create a template for this tenant (to verify tenant scoping works)
        $template = Template::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Template',
            'category' => 'project',
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        
        // Make HTTP request to templates index endpoint
        // This endpoint uses getTenantId() which calls TenancyService.resolveActiveTenantId()
        $response = $this->getJson('/api/v1/app/templates');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'tenant_id',
                    ],
                ],
            ]);
        
        // Verify the response contains only templates for the resolved tenant
        $responseData = $response->json('data');
        $this->assertNotEmpty($responseData, 'Should return at least one template');
        
        // All templates should belong to the expected tenant
        foreach ($responseData as $templateData) {
            $this->assertEquals(
                (string) $tenant->id,
                (string) $templateData['tenant_id'],
                'All templates should belong to the resolved tenant'
            );
        }
        
        // Verify the template we created is in the response
        $templateIds = array_map('strval', array_column($responseData, 'id'));
        $this->assertContains((string) $template->id, $templateIds, 'Created template should be in response');
        
        // Verify TenancyService resolves the same tenant ID
        $tenancyService = app(\App\Services\TenancyService::class);
        $authenticatedUser = auth()->user();
        $resolvedTenantId = $tenancyService->resolveActiveTenantId($authenticatedUser, request());
        
        $this->assertNotNull($resolvedTenantId, 'TenancyService should resolve tenant ID');
        $this->assertEquals(
            (string) $tenant->id,
            (string) $resolvedTenantId,
            'Resolved tenant ID should match expected tenant'
        );
    }

    /**
     * Test that cross-tenant access is properly blocked via HTTP
     * 
     * This test verifies that:
     * 1. User A cannot access templates belonging to Tenant B
     * 2. The tenant resolution correctly identifies User A's tenant
     * 3. Templates from other tenants are filtered out
     */
    public function test_cross_tenant_access_is_blocked_via_http(): void
    {
        // Create two tenants
        $tenantA = Tenant::factory()->create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a-' . uniqid(),
        ]);
        
        $tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-' . uniqid(),
        ]);
        
        // Create user for Tenant A
        $userA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'role' => 'pm',
        ]);
        
        // Attach user to tenant via pivot
        $userA->tenants()->attach($tenantA->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);
        
        $userA->refresh();
        
        // Create template for Tenant B (should not be accessible to User A)
        $templateB = Template::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Tenant B Template',
            'category' => 'project',
            'is_active' => true,
        ]);
        
        // Authenticate as User A
        Sanctum::actingAs($userA, [], 'sanctum');
        
        // Make HTTP request to get template by ID
        // This should return 404 because template belongs to different tenant
        $response = $this->getJson("/api/v1/app/templates/{$templateB->id}");
        
        $response->assertStatus(404);
        
        // Verify TenancyService resolves Tenant A, not Tenant B
        $tenancyService = app(\App\Services\TenancyService::class);
        $authenticatedUser = auth()->user();
        $resolvedTenantId = $tenancyService->resolveActiveTenantId($authenticatedUser, request());
        
        $this->assertEquals(
            (string) $tenantA->id,
            (string) $resolvedTenantId,
            'Resolved tenant should be Tenant A, not Tenant B'
        );
    }
}

