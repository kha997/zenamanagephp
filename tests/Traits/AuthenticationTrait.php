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
            'role' => 'super_admin',
        ], $attributes));
        

        // Assign roles if provided
        if (!empty($roles)) {
            foreach ($roles as $roleCode) {
                $role = Role::factory()->create(['name' => $roleCode]);
                $user->roles()->attach($role->id);
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
        $sanctumToken = $user->createToken('testing-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $sanctumToken);
        
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
