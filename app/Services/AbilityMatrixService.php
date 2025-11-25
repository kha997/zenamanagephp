<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Ability Matrix Service
 * 
 * Exports ability/permission matrix for OpenAPI documentation
 * and ensures FE/BE permission synchronization.
 */
class AbilityMatrixService
{
    /**
     * Get all abilities/permissions matrix
     * 
     * Returns a matrix of all abilities with their required roles/permissions
     * for OpenAPI x-abilities extension.
     * 
     * @return array Ability matrix
     */
    public function getAbilityMatrix(): array
    {
        $abilities = $this->getAllAbilities();
        $matrix = [];

        foreach ($abilities as $ability) {
            $matrix[$ability] = [
                'required_roles' => $this->getRequiredRolesForAbility($ability),
                'required_permissions' => $this->getRequiredPermissionsForAbility($ability),
                'description' => $this->getAbilityDescription($ability),
            ];
        }

        return $matrix;
    }

    /**
     * Get all abilities defined in the system
     * 
     * @return array List of ability codes
     */
    private function getAllAbilities(): array
    {
        // System-wide abilities
        $systemAbilities = [
            'admin.access',
            'admin.access.tenant',
            'tenants.manage',
            'maintenance.*',
        ];

        // Tenant-scoped abilities
        $tenantAbilities = [
            'projects.view',
            'projects.create',
            'projects.modify',
            'projects.delete',
            'projects.manage',
            'tasks.view',
            'tasks.create',
            'tasks.modify',
            'tasks.delete',
            'tasks.manage',
            'documents.view',
            'documents.create',
            'documents.modify',
            'documents.delete',
            'documents.approve',
            'templates.manage',
            'users.view',
            'users.create',
            'users.modify',
            'users.delete',
            'reports.view',
            'reports.generate',
            'change_requests.view',
            'change_requests.create',
            'change_requests.approve',
            'change_requests.reject',
            'quotes.view',
            'quotes.create',
            'quotes.modify',
            'quotes.approve',
        ];

        return array_merge($systemAbilities, $tenantAbilities);
    }

    /**
     * Get required roles for an ability
     * 
     * @param string $ability
     * @return array List of roles that have this ability
     */
    private function getRequiredRolesForAbility(string $ability): array
    {
        // Map abilities to roles based on permission inheritance
        $roleAbilityMap = [
            'admin.access' => ['super_admin'],
            'admin.access.tenant' => ['super_admin', 'admin'],
            'tenants.manage' => ['super_admin'],
            'maintenance.*' => ['super_admin'],
            'projects.manage' => ['super_admin', 'admin', 'project_manager'],
            'tasks.manage' => ['super_admin', 'admin', 'project_manager'],
            'templates.manage' => ['super_admin', 'admin'],
            'users.manage' => ['super_admin', 'admin'],
            'reports.generate' => ['super_admin', 'admin', 'project_manager'],
            'change_requests.approve' => ['super_admin', 'admin', 'project_manager'],
            'quotes.approve' => ['super_admin', 'admin', 'project_manager'],
        ];

        // If specific mapping exists, return it
        if (isset($roleAbilityMap[$ability])) {
            return $roleAbilityMap[$ability];
        }

        // Default: check all roles
        $roles = ['super_admin', 'admin', 'project_manager', 'member', 'client'];
        $hasAbility = [];

        foreach ($roles as $role) {
            // Check if role has this ability via Gate or PermissionService
            if ($this->roleHasAbility($role, $ability)) {
                $hasAbility[] = $role;
            }
        }

        return $hasAbility;
    }

    /**
     * Check if a role has an ability
     * 
     * @param string $role
     * @param string $ability
     * @return bool
     */
    private function roleHasAbility(string $role, string $ability): bool
    {
        // Use PermissionService to check
        $permissionService = app(\App\Services\PermissionService::class);
        
        // Create a mock user with the role
        $user = new User();
        $user->role = $role;
        
        // Check via Gate or User model
        try {
            return Gate::forUser($user)->allows($ability) || 
                   $user->hasPermission($ability);
        } catch (\Exception $e) {
            Log::debug('Error checking ability', [
                'role' => $role,
                'ability' => $ability,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get required permissions for an ability
     * 
     * @param string $ability
     * @return array List of permission codes
     */
    private function getRequiredPermissionsForAbility(string $ability): array
    {
        // Most abilities map 1:1 to permissions
        return [$ability];
    }

    /**
     * Get description for an ability
     * 
     * @param string $ability
     * @return string
     */
    private function getAbilityDescription(string $ability): string
    {
        $descriptions = [
            'admin.access' => 'Access to system admin panel',
            'admin.access.tenant' => 'Access to tenant admin panel',
            'tenants.manage' => 'Manage tenants (system-wide)',
            'maintenance.*' => 'All maintenance operations',
            'projects.view' => 'View projects',
            'projects.create' => 'Create new projects',
            'projects.modify' => 'Modify existing projects',
            'projects.delete' => 'Delete projects',
            'projects.manage' => 'Full project management',
            'tasks.view' => 'View tasks',
            'tasks.create' => 'Create new tasks',
            'tasks.modify' => 'Modify existing tasks',
            'tasks.delete' => 'Delete tasks',
            'tasks.manage' => 'Full task management',
            'documents.view' => 'View documents',
            'documents.create' => 'Create documents',
            'documents.modify' => 'Modify documents',
            'documents.delete' => 'Delete documents',
            'documents.approve' => 'Approve documents',
            'templates.manage' => 'Manage templates',
            'users.view' => 'View users',
            'users.create' => 'Create users',
            'users.modify' => 'Modify users',
            'users.delete' => 'Delete users',
            'reports.view' => 'View reports',
            'reports.generate' => 'Generate reports',
            'change_requests.view' => 'View change requests',
            'change_requests.create' => 'Create change requests',
            'change_requests.approve' => 'Approve change requests',
            'change_requests.reject' => 'Reject change requests',
            'quotes.view' => 'View quotes',
            'quotes.create' => 'Create quotes',
            'quotes.modify' => 'Modify quotes',
            'quotes.approve' => 'Approve quotes',
        ];

        return $descriptions[$ability] ?? "Ability: {$ability}";
    }

    /**
     * Export ability matrix for OpenAPI
     * 
     * Returns format suitable for OpenAPI x-abilities extension
     * 
     * @return array OpenAPI-compatible ability matrix
     */
    public function exportForOpenAPI(): array
    {
        $matrix = $this->getAbilityMatrix();
        
        return [
            'x-abilities' => $matrix,
            'x-ability-descriptions' => $this->getAbilityDescriptions(),
        ];
    }

    /**
     * Get all ability descriptions
     * 
     * @return array
     */
    private function getAbilityDescriptions(): array
    {
        $descriptions = [];
        $abilities = $this->getAllAbilities();
        
        foreach ($abilities as $ability) {
            $descriptions[$ability] = $this->getAbilityDescription($ability);
        }
        
        return $descriptions;
    }
}

