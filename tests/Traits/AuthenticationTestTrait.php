<?php declare(strict_types=1);

namespace Tests\Traits;

use App\Models\Tenant;
use App\Models\User;
use Src\RBAC\Models\Role;
use Src\RBAC\Models\Permission;
use Src\RBAC\Services\AuthService;
use Illuminate\Foundation\Testing\WithFaker;

/**
 * Trait AuthenticationTestTrait
 * 
 * Provides authentication utilities for testing JWT-based API endpoints
 * Handles user creation, role assignment, and JWT token generation
 * 
 * @package Tests\Traits
 */
trait AuthenticationTestTrait
{
    use AuthenticationTrait, ApiTestTrait, WithFaker;

    protected ?User $apiFeatureUser = null;
    protected ?Tenant $apiFeatureTenant = null;
    protected ?string $apiFeatureToken = null;

    /**
     * Create a test user with specified roles and permissions
     * 
     * @param array $roles Array of role names to assign
     * @param array $permissions Array of permission codes to assign
     * @param array $attributes Additional user attributes
     * @return User
     */
    protected function createTestUser(array $roles = [], array $permissions = [], array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'tenant_id' => 1,
            'email' => $this->faker->unique()->safeEmail,
            'name' => $this->faker->name,
        ], $attributes));

        // Assign system roles
        foreach ($roles as $roleName) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
            ], [
                'scope' => 'system',
                'is_active' => true,
            ]);
            $user->systemRoles()->attach($role->id);
        }

        // Assign specific permissions
        foreach ($permissions as $permissionCode) {
            $permission = Permission::firstOrCreate([
                'code' => $permissionCode,
                'module' => explode('.', $permissionCode)[0],
                'action' => explode('.', $permissionCode)[1] ?? 'access'
            ]);
            
            // Create a temporary role for this permission if needed
            $tempRole = Role::firstOrCreate([
                'name' => 'temp_' . $permissionCode,
            ], [
                'scope' => 'custom',
                'is_active' => true,
            ]);
            $tempRole->permissions()->syncWithoutDetaching($permission->id);
            $user->systemRoles()->syncWithoutDetaching($tempRole->id);
        }

        return $user;
    }

    /**
     * Create an admin user with full permissions
     * 
     * @return User
     */
    protected function createAdminUser(): User
    {
        return $this->createTestUser(['admin'], [
            'project.create', 'project.update', 'project.delete',
            'component.create', 'component.update', 'component.delete',
            'task.create', 'task.update', 'task.delete',
            'user.manage', 'role.manage'
        ]);
    }

    /**
     * Create a project manager user
     * 
     * @return User
     */
    protected function createProjectManagerUser(): User
    {
        return $this->createTestUser(['project_manager'], [
            'project.view', 'project.update',
            'component.create', 'component.update',
            'task.create', 'task.update', 'task.assign'
        ]);
    }

    /**
     * Create a regular user with basic permissions
     * 
     * @return User
     */
    protected function createRegularUser(): User
    {
        return $this->createTestUser(['user'], [
            'project.view', 'task.view', 'task.update'
        ]);
    }

    /**
     * Generate JWT token for a user
     * 
     * @param User $user
     * @return string
     */
    protected function generateJwtToken(User $user): string
    {
        $authService = app(AuthService::class);
        return $authService->createTokenForUser($user);
    }

    /**
     * Get authorization headers with JWT token
     * 
     * @param User $user
     * @return array
     */
    protected function getAuthHeaders(User $user): array
    {
        $token = $this->generateJwtToken($user);
        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];
    }

    /**
     * Act as an existing user instance for subsequent requests
     * 
     * @param User $user
     * @return $this
     */
    protected function actingAsExistingUser(User $user)
    {
        $token = $this->generateJwtToken($user);
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ]);
    }

    /**
     * Prepare headers for a tenant-authenticated request using an existing token.
     *
     * @param User $user
     * @param string $tenantId
     * @param string $token
     * @return static
     */
    protected function actingAsTenantUser(User $user, string $tenantId, string $token): static
    {
        $headers = array_merge(
            $this->apiHeadersForTenant($tenantId),
            ['Authorization' => 'Bearer ' . $token]
        );

        $this->apiHeaders = $headers;

        return $this->withHeaders($headers);
    }

    /**
     * Build headers that only include tenant context (no authentication)
     * 
     * @param array $headers
     * @return array
     */
    protected function tenantHeaders(array $headers = []): array
    {
        $tenantId = $this->apiFeatureTenant?->id ?? null;

        if ($tenantId === null && property_exists($this, 'tenantId')) {
            $tenantId = $this->tenantId;
        }

        if ($tenantId !== null) {
            return $this->apiHeadersForTenant((string) $tenantId, $headers);
        }

        return array_merge([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], $headers);
    }

    /**
     * Assert JWT token is valid and contains expected claims
     * 
     * @param string $token
     * @param array $expectedClaims
     * @return void
     */
    protected function assertValidJwtToken(string $token, array $expectedClaims = []): void
    {
        $authService = app(AuthService::class);
        $payload = $authService->validateToken($token);
        
        $this->assertNotNull($payload);
        $this->assertNotNull($payload['user_id'] ?? $payload['sub']); // User ID
        $this->assertNotNull($payload['iat']); // Issued at
        $this->assertNotNull($payload['exp']); // Expires at
        
        foreach ($expectedClaims as $claim => $value) {
            $this->assertEquals($value, $payload[$claim]);
        }
    }

    /**
     * Decode JWT token payload
     * 
     * @param string $token
     * @return array|null
     */
    protected function decodeJwtToken(string $token): ?array
    {
        $authService = app(AuthService::class);
        return $authService->validateToken($token);
    }
    
    /**
     * Authenticate as a tenant administrator for API requests.
     *
     * @param array $attributes
     * @param Tenant|null $tenant
     * @return User
     */
    protected function apiActingAsTenantAdmin(array $attributes = [], ?Tenant $tenant = null): User
    {
        $tenant = $tenant ?? Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, $attributes);

        $token = $this->apiLoginToken($user, $tenant);

        $this->apiFeatureTenant = $tenant;
        $this->apiFeatureUser = $user;
        $this->apiFeatureToken = $token;
        $this->apiHeaders = array_merge(
            $this->apiHeadersForTenant((string) $tenant->id),
            ['Authorization' => 'Bearer ' . $token]
        );

        return $user;
    }
}
