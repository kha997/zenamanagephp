<?php declare(strict_types=1);

namespace Tests\Traits;

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
    use WithFaker;

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
                'scope' => 'system'
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
                'scope' => 'custom'
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
     * Act as a specific user for subsequent requests
     * 
     * @param User $user
     * @return $this
     */
    protected function actingAsUser(User $user)
    {
        $token = $this->generateJwtToken($user);
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ]);
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
}