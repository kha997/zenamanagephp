<?php

namespace App\Traits;

trait HasRoles
{
    /**
     * Check if user has a specific role
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roleNames): bool
    {
        return $this->roles()->whereIn('name', $roleNames)->exists();
    }

    /**
     * Check if user has all of the given roles
     */
    public function hasAllRoles(array $roleNames): bool
    {
        $userRoles = $this->roles()->pluck('name')->toArray();
        return count(array_intersect($roleNames, $userRoles)) === count($roleNames);
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Check if user is admin (but not super admin)
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin') && !$this->isSuperAdmin();
    }

    /**
     * Check if user has tenant context
     */
    public function hasTenant(): bool
    {
        return !is_null($this->tenant_id);
    }

    /**
     * Check if user belongs to specific tenant
     */
    public function belongsToTenant(string $tenantId): bool
    {
        return $this->tenant_id === $tenantId;
    }

    /**
     * Get user's primary role (first role)
     */
    public function getPrimaryRole(): ?string
    {
        $role = $this->roles()->first();
        return $role ? $role->name : null;
    }

    /**
     * Get all user role names
     */
    public function getRoleNames(): array
    {
        return $this->roles()->pluck('name')->toArray();
    }

    /**
     * Check if user can access admin area
     */
    public function canAccessAdmin(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Check if user can access app area
     */
    public function canAccessApp(): bool
    {
        return $this->hasTenant() && !$this->isSuperAdmin();
    }

    /**
     * Check if user has permission for specific action
     */
    public function hasPermission(string $permission): bool
    {
        // For now, we'll use role-based permissions
        // This can be extended to use a full permission system later
        
        $rolePermissions = [
            'super_admin' => ['*'], // All permissions
            'admin' => ['manage_users', 'manage_tenants', 'view_reports'],
            'project_manager' => ['manage_projects', 'manage_tasks', 'view_reports'],
            'designer' => ['manage_designs', 'view_projects'],
            'site_engineer' => ['manage_construction', 'view_projects'],
            'qc_engineer' => ['manage_quality', 'view_projects'],
            'procurement' => ['manage_procurement', 'view_projects'],
            'finance' => ['manage_finance', 'view_reports'],
            'client' => ['view_projects', 'view_reports'],
        ];

        $userRoles = $this->getRoleNames();
        
        foreach ($userRoles as $role) {
            if (isset($rolePermissions[$role])) {
                $permissions = $rolePermissions[$role];
                if (in_array('*', $permissions) || in_array($permission, $permissions)) {
                    return true;
                }
            }
        }
        
        return false;
    }
}