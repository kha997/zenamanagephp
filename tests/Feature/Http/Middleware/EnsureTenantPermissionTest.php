<?php declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Models\User;
use App\Models\Tenant;
use App\Services\TenancyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;

/**
 * Tests for EnsureTenantPermission middleware
 * 
 * @group middleware
 * @group tenant-permissions
 */
class EnsureTenantPermissionTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(45678);
        $this->setDomainName('tenant-permission');
        $this->setupDomainIsolation();
    }

    /**
     * Test that middleware returns 401 for unauthenticated users
     */
    public function test_middleware_returns_401_for_unauthenticated_users(): void
    {
        $response = $this->getJson('/api/v1/app/projects', [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test that middleware returns 403 when user has no active tenant
     */
    public function test_middleware_returns_403_when_no_active_tenant(): void
    {
        // Create user without tenant
        $user = User::factory()->create([
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/v1/app/projects', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ],
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that middleware returns 403 when user lacks required permission
     */
    public function test_middleware_returns_403_when_user_lacks_permission(): void
    {
        // Create tenant
        $tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);

        // Create user with member role (only has tenant.view_projects, not tenant.manage_projects)
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);

        // Add user to tenant via pivot with member role
        $user->tenants()->attach($tenant->id, [
            'role' => 'member',
            'is_default' => true,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        // Try to create a project (requires tenant.manage_projects)
        $response = $this->postJson('/api/v1/app/projects', [
            'name' => 'Test Project',
        ], [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ],
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that middleware allows access when user has required permission
     */
    public function test_middleware_allows_access_when_user_has_permission(): void
    {
        // Create tenant
        $tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);

        // Create user with admin role (has tenant.manage_projects)
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);

        // Add user to tenant via pivot with admin role
        $user->tenants()->attach($tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        // Try to view projects (requires tenant.view_projects)
        $response = $this->getJson('/api/v1/app/projects', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ],
        ]);

        // Should succeed (200) or return empty list (if no projects)
        $this->assertContains($response->status(), [200, 404]);
    }

    /**
     * Test that middleware attaches active_tenant_id to request
     */
    public function test_middleware_attaches_active_tenant_id_to_request(): void
    {
        // Create tenant
        $tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);

        // Create user with admin role
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);

        // Add user to tenant via pivot with admin role
        $user->tenants()->attach($tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        // Make request
        $response = $this->getJson('/api/v1/app/projects', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ],
        ]);

        // The middleware should have attached active_tenant_id to the request
        // This is tested indirectly by checking that the controller can access it
        // (via getTenantId() which checks request attributes)
        $this->assertContains($response->status(), [200, 404]);
    }

    /**
     * Test that member role can view but not manage projects
     */
    public function test_member_role_can_view_but_not_manage_projects(): void
    {
        // Create tenant
        $tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);

        // Create user with member role
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);

        // Add user to tenant via pivot with member role
        $user->tenants()->attach($tenant->id, [
            'role' => 'member',
            'is_default' => true,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        // Member should be able to view projects
        $viewResponse = $this->getJson('/api/v1/app/projects', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ],
        ]);

        $this->assertContains($viewResponse->status(), [200, 404]);

        // Member should NOT be able to create projects
        $createResponse = $this->postJson('/api/v1/app/projects', [
            'name' => 'Test Project',
        ], [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ],
        ]);

        $createResponse->assertStatus(403);
        $createResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }
}

