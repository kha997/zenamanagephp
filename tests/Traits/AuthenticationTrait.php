<?php declare(strict_types=1);

namespace Tests\Traits;

use App\Models\User;
use App\Models\Tenant;
use Src\RBAC\Models\Role;
use Src\RBAC\Services\AuthService;

/**
 * Trait để hỗ trợ authentication trong tests
 */
trait AuthenticationTrait
{
    /**
     * Tạo user với JWT token cho testing
     *
     * @param array $attributes
     * @param array $roles
     * @return User
     */
    protected function createAuthenticatedUser(array $attributes = [], array $roles = []): User
    {
        $tenant = Tenant::factory()->create();
        
        $user = User::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
        ], $attributes));
        
        // Assign roles if provided
        if (!empty($roles)) {
            foreach ($roles as $roleCode) {
                $role = Role::factory()->create(['name' => $roleCode]);
                $user->systemRoles()->attach($role->id);
            }
        }
        
        return $user;
    }
    
    /**
     * Authenticate user và set JWT token
     *
     * @param User $user
     * @return string
     */
    protected function authenticateUser(User $user): string
    {
        $authService = app(AuthService::class);
        $token = $authService->createTokenForUser($user);
        $this->withHeader('Authorization', 'Bearer ' . $token);
        
        return $token;
    }
    
    /**
     * Tạo và authenticate user trong một bước
     *
     * @param array $attributes
     * @param array $roles
     * @return User
     */
    protected function actingAsUser(array $attributes = [], array $roles = []): User
    {
        $user = $this->createAuthenticatedUser($attributes, $roles);
        $this->authenticateUser($user);
        
        return $user;
    }
}