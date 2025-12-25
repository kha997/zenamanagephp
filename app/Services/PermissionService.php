<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PermissionService
{
    /**
     * Check if user has a specific permission.
     */
    public function hasPermission(User $user, string $permission): bool
    {
        $cacheKey = "user_permissions_{$user->id}";
        
        $permissions = Cache::remember($cacheKey, 300, function () use ($user) {
            return $this->getUserPermissions($user);
        });
        
        return in_array($permission, $permissions);
    }

    /**
     * Check if user has any of the specified permissions.
     */
    public function hasAnyPermission(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($user, $permission)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if user has all of the specified permissions.
     */
    public function hasAllPermissions(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($user, $permission)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get all permissions for a user.
     */
    public function getUserPermissions(User $user): array
    {
        $permissions = [];
        
        // Get permissions from user's roles
        $roles = $this->getUserRoles($user);
        
        foreach ($roles as $role) {
            $rolePermissions = $this->getRolePermissions($role);
            $permissions = array_merge($permissions, $rolePermissions);
        }
        
        // Remove duplicates and return
        return array_unique($permissions);
    }

    /**
     * Get user's roles.
     */
    protected function getUserRoles(User $user): array
    {
        
        // For now, return a default role based on user attributes
        if ($user->email === 'admin@zena.com') {
            return ['super_admin'];
        }
        
        return ['project_manager']; // Default role
    }

    /**
     * Get permissions for a role.
     */
    protected function getRolePermissions(string $role): array
    {
        $rolePermissions = [
            'super_admin' => [
                'project.read', 'project.create', 'project.update', 'project.delete', 'project.archive', 'project.restore',
                'task.read', 'task.create', 'task.update', 'task.delete', 'task.assign', 'task.comment',
                'user.read', 'user.create', 'user.update', 'user.delete', 'user.restore',
                'admin.sidebar.manage', 'admin.system.manage',
            ],
            'admin' => [
                'project.read', 'project.create', 'project.update', 'project.delete', 'project.archive',
                'task.read', 'task.create', 'task.update', 'task.delete', 'task.assign', 'task.comment',
                'user.read', 'user.create', 'user.update', 'user.delete',
                'admin.sidebar.manage',
            ],
            'project_manager' => [
                'project.read', 'project.create', 'project.update', 'project.archive',
                'task.read', 'task.create', 'task.update', 'task.delete', 'task.assign', 'task.comment',
                'user.read',
            ],
            'designer' => [
                'project.read',
                'task.read', 'task.create', 'task.update', 'task.comment',
                'user.read',
            ],
            'site_engineer' => [
                'project.read',
                'task.read', 'task.create', 'task.update', 'task.comment',
                'user.read',
            ],
            'qc' => [
                'project.read',
                'task.read', 'task.create', 'task.update', 'task.comment',
                'user.read',
            ],
            'procurement' => [
                'project.read',
                'task.read', 'task.create', 'task.update', 'task.comment',
                'user.read',
            ],
            'finance' => [
                'project.read',
                'task.read', 'task.comment',
                'user.read',
            ],
            'client' => [
                'project.read',
                'task.read',
            ],
        ];
        
        return $rolePermissions[$role] ?? [];
    }

    /**
     * Check if user can access a specific route.
     */
    public function canAccessRoute(User $user, string $route): bool
    {
        $routePermissions = $this->getRoutePermissions($route);
        
        if (empty($routePermissions)) {
            return true; // No permissions required
        }
        
        return $this->hasAnyPermission($user, $routePermissions);
    }

    /**
     * Get required permissions for a route.
     */
    protected function getRoutePermissions(string $route): array
    {
        $routePermissionMap = [
            'dashboard' => [],
            'projects.index' => ['project.read'],
            'projects.create' => ['project.create'],
            'projects.show' => ['project.read'],
            'projects.edit' => ['project.update'],
            'projects.destroy' => ['project.delete'],
            'tasks.index' => ['task.read'],
            'tasks.create' => ['task.create'],
            'tasks.show' => ['task.read'],
            'tasks.edit' => ['task.update'],
            'tasks.destroy' => ['task.delete'],
            'users.index' => ['user.read'],
            'users.create' => ['user.create'],
            'users.show' => ['user.read'],
            'users.edit' => ['user.update'],
            'users.destroy' => ['user.delete'],
            'admin.sidebar-builder' => ['admin.sidebar.manage'],
            'admin.sidebar-builder.edit' => ['admin.sidebar.manage'],
            'admin.sidebar-builder.preview' => ['admin.sidebar.manage'],
        ];
        
        return $routePermissionMap[$route] ?? [];
    }

    /**
     * Check if user can perform a specific action on a model.
     */
    public function canPerformAction(User $user, string $model, string $action): bool
    {
        $permission = strtolower($model) . '.' . strtolower($action);
        return $this->hasPermission($user, $permission);
    }

    /**
     * Filter sidebar items based on user permissions.
     */
    public function filterSidebarItems(array $items, User $user): array
    {
        $filteredItems = [];
        
        foreach ($items as $item) {
            if ($this->canUserSeeSidebarItem($item, $user)) {
                // Filter children if it's a group
                if ($item['type'] === 'group' && isset($item['children'])) {
                    $item['children'] = $this->filterSidebarItems($item['children'], $user);
                }
                
                $filteredItems[] = $item;
            }
        }
        
        return $filteredItems;
    }

    /**
     * Check if user can see a sidebar item.
     */
    protected function canUserSeeSidebarItem(array $item, User $user): bool
    {
        // Check if item is enabled
        if (!($item['enabled'] ?? true)) {
            return false;
        }
        
        // Check required permissions
        if (isset($item['required_permissions']) && !empty($item['required_permissions'])) {
            if (!$this->hasAllPermissions($user, $item['required_permissions'])) {
                return false;
            }
        }
        
        // Check route permissions if it's a link
        if ($item['type'] === 'link' && isset($item['to'])) {
            $route = $this->extractRouteFromUrl($item['to']);
            if ($route && !$this->canAccessRoute($user, $route)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Extract route name from URL.
     */
    protected function extractRouteFromUrl(string $url): ?string
    {
        // Simple route extraction - in a real app, you'd 
        
        return $urlMap[$url] ?? null;
    }

    /**
     * Clear permission cache for a user.
     */
    public function clearUserCache(User $user): void
    {
        $cacheKey = "user_permissions_{$user->id}";
        Cache::forget($cacheKey);
    }

    /**
     * Clear all permission caches.
     */
    public function clearAllCaches(): void
    {
        
        Cache::flush();
    }
}
