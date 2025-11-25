<?php declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Role;

/**
 * Policy Test Helper
 * 
 * Provides helper methods for creating test data for policy tests
 */
class PolicyTestHelper
{
    /**
     * Create a user with role assigned
     * 
     * @param Tenant $tenant
     * @param string $roleName
     * @param array $attributes
     * @return User
     */
    public static function createUserWithRole(Tenant $tenant, string $roleName, array $attributes = []): User
    {
        // Create or get role (check by name only, scope can vary)
        $role = Role::where('name', $roleName)->first();
        if (!$role) {
            $role = Role::create([
                'name' => $roleName,
                'scope' => 'system',
                'description' => "Role: {$roleName}",
                'is_active' => true,
            ]);
        }
        
        // Create user
        $user = TestDataSeeder::createUser($tenant, array_merge([
            'role' => $roleName, // Set role field for backward compatibility
        ], $attributes));
        
        // Attach role to user via zena_user_roles table
        \DB::table('zena_user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Refresh user to load roles relationship
        $user->refresh();
        
        return $user;
    }

    /**
     * Create multiple users with different roles
     * 
     * @param Tenant $tenant
     * @param array $roleNames
     * @return array Array of User instances indexed by role name
     */
    public static function createUsersWithRoles(Tenant $tenant, array $roleNames): array
    {
        $users = [];
        foreach ($roleNames as $roleName) {
            $users[$roleName] = self::createUserWithRole($tenant, $roleName);
        }
        return $users;
    }
}

