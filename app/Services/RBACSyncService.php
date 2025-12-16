<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * RBAC Sync Service
 * 
 * Ensures permissions are synchronized between frontend and backend.
 * Provides mechanism to detect and report permission drift.
 */
class RbacSyncService
{
    /**
     * Get user permissions in format compatible with frontend
     * 
     * @param User $user
     * @return array{permissions: array<string>, abilities: array<string>, role: string}
     */
    public function getUserPermissions(User $user): array
    {
        $cacheKey = "user_permissions_{$user->id}";
        
        return Cache::remember($cacheKey, 300, function () use ($user) {
            $role = $user->role ?? 'member';
            $permissions = config("permissions.roles.{$role}", []);
            
            // Get abilities
            $abilities = [];
            $isSuperAdmin = $user->isSuperAdmin() || $user->can('admin.access');
            $isOrgAdmin = $user->can('admin.access.tenant');
            
            if ($isSuperAdmin) {
                $abilities[] = 'admin';
            }
            if ($isOrgAdmin || $user->tenant_id) {
                $abilities[] = 'tenant';
            }
            
            return [
                'permissions' => $permissions,
                'abilities' => $abilities,
                'role' => $role,
            ];
        });
    }

    /**
     * Validate frontend permission check against backend
     * 
     * This can be called from a middleware or endpoint to verify
     * that frontend permission checks match backend enforcement.
     * 
     * @param User $user
     * @param string $permission
     * @param string|null $resource
     * @return bool
     */
    public function validatePermission(User $user, string $permission, ?string $resource = null): bool
    {
        // Check via Gate/Policy
        if ($resource) {
            return \Illuminate\Support\Facades\Gate::forUser($user)->allows($permission, $resource);
        }
        
        return $user->can($permission);
    }

    /**
     * Get all available permissions for documentation/sync
     * 
     * @return array<string, array<string>>
     */
    public function getAllPermissions(): array
    {
        return config('permissions.roles', []);
    }

    /**
     * Detect permission drift between frontend and backend
     * 
     * Checks if frontend is checking permissions that don't exist in backend,
     * or if backend has permissions that frontend doesn't know about.
     * 
     * @param array $frontendPermissions Permissions used by frontend
     * @return array{drift_detected: bool, missing_in_backend: array<string>, missing_in_frontend: array<string>}
     */
    public function detectDrift(array $frontendPermissions): array
    {
        $allBackendPermissions = $this->getAllPermissions();
        $backendPermissionSet = [];
        
        // Flatten backend permissions
        foreach ($allBackendPermissions as $role => $permissions) {
            foreach ($permissions as $permission) {
                $backendPermissionSet[$permission] = true;
            }
        }
        
        $backendPermissions = array_keys($backendPermissionSet);
        
        // Find permissions in frontend but not in backend
        $missingInBackend = array_diff($frontendPermissions, $backendPermissions);
        
        // Find permissions in backend but not in frontend
        $missingInFrontend = array_diff($backendPermissions, $frontendPermissions);
        
        $driftDetected = !empty($missingInBackend) || !empty($missingInFrontend);
        
        if ($driftDetected) {
            Log::warning('RBAC drift detected', [
                'missing_in_backend' => $missingInBackend,
                'missing_in_frontend' => $missingInFrontend,
            ]);
        }
        
        return [
            'drift_detected' => $driftDetected,
            'missing_in_backend' => array_values($missingInBackend),
            'missing_in_frontend' => array_values($missingInFrontend),
        ];
    }

    /**
     * Invalidate permission cache for user
     * 
     * Call this when user permissions change (role update, etc.)
     * 
     * @param User $user
     * @return void
     */
    public function invalidateUserPermissions(User $user): void
    {
        $cacheKey = "user_permissions_{$user->id}";
        Cache::forget($cacheKey);
        
        Log::info('User permissions cache invalidated', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);
    }

    /**
     * Get route permissions mapping
     * 
     * Maps routes to required permissions for frontend route guards.
     * 
     * @return array<string, string|array<string>>
     */
    public function getRoutePermissions(): array
    {
        $routes = Route::getRoutes();
        $routePermissions = [];
        
        foreach ($routes as $route) {
            $name = $route->getName();
            if (!$name) {
                continue;
            }
            
            // Extract permission from middleware
            $middleware = $route->gatherMiddleware();
            $permission = $this->extractPermissionFromMiddleware($middleware);
            
            if ($permission) {
                $routePermissions[$name] = $permission;
            }
        }
        
        return $routePermissions;
    }

    /**
     * Extract permission from route middleware
     * 
     * @param array $middleware
     * @return string|null
     */
    private function extractPermissionFromMiddleware(array $middleware): ?string
    {
        foreach ($middleware as $mw) {
            if (is_string($mw) && str_starts_with($mw, 'permission:')) {
                return str_replace('permission:', '', $mw);
            }
            
            if (is_string($mw) && str_starts_with($mw, 'ability:')) {
                return str_replace('ability:', '', $mw);
            }
        }
        
        return null;
    }

    /**
     * Generate TypeScript types for permissions
     * 
     * This can be used to generate frontend types from backend permissions.
     * 
     * @return string TypeScript type definition
     */
    public function generateFrontendTypes(): string
    {
        $allPermissions = $this->getAllPermissions();
        $permissionSet = [];
        
        foreach ($allPermissions as $role => $permissions) {
            foreach ($permissions as $permission) {
                $permissionSet[$permission] = true;
            }
        }
        
        $permissions = array_keys($permissionSet);
        sort($permissions);
        
        $types = "export type Permission = \n";
        foreach ($permissions as $perm) {
            $types .= "  | '{$perm}'\n";
        }
        $types .= ";\n\n";
        
        $types .= "export type Role = \n";
        foreach (array_keys($allPermissions) as $role) {
            $types .= "  | '{$role}'\n";
        }
        $types .= ";\n";
        
        return $types;
    }
}
