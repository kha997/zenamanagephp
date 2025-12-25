<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-Based Access Control Middleware
 * 
 * Ensures users have the required roles/permissions to access specific endpoints.
 * Supports both role-based and permission-based access control.
 */
class RoleBasedAccessControlMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string|null  $roleOrPermission
     * @param  string|null  $projectParam
     */
    public function handle(Request $request, Closure $next, ?string $roleOrPermission = null, ?string $projectParam = null): Response
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'User not authenticated',
                'code' => 'USER_NOT_AUTHENTICATED'
            ], 401);
        }
        
        if ($roleOrPermission === null) {
            return $this->handleGeneralAccess($user, $request, $next);
        }

        // Check if user has required role or permission
        $hasAccess = $this->checkAccess($user, $roleOrPermission, $request, $projectParam);
        
        if (!$hasAccess) {
            Log::warning('Access denied', [
                'user_id' => $user->id,
                'email' => $user->email,
                'tenant_id' => $user->tenant_id,
                'required_role_permission' => $roleOrPermission,
                'route' => $request->route()?->getName(),
                'method' => $request->method(),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Access Denied',
                'message' => 'You do not have permission to access this resource',
                'code' => 'ACCESS_DENIED'
            ], 403);
        }
        
        // Add access context to request
        $request->attributes->set('required_role_permission', $roleOrPermission);
        $request->attributes->set('access_granted', true);
        
        return $next($request);
    }
    
    /**
     * Check if user has required access
     */
    private function checkAccess($user, string $roleOrPermission, Request $request, ?string $projectParam = null): bool
    {
        // Super admin has access to everything
        if ($user->isSuperAdmin()) {
            return true;
        }
        
        // Check for specific roles
        if ($this->isRole($roleOrPermission)) {
            return $this->checkRole($user, $roleOrPermission);
        }
        
        // Check for specific permissions
        if ($this->isPermission($roleOrPermission)) {
            return $this->checkPermission($user, $roleOrPermission, $request, $projectParam);
        }
        
        // Default: deny access
        return false;
    }
    
    /**
     * Check if the string is a role
     */
    private function isRole(string $roleOrPermission): bool
    {
        $roles = ['admin', 'project_manager', 'team_member', 'client', 'viewer'];
        return in_array($roleOrPermission, $roles);
    }
    
    /**
     * Check if the string is a permission
     */
    private function isPermission(string $roleOrPermission): bool
    {
        $permissions = [
            'create_project', 'edit_project', 'delete_project', 'view_project',
            'create_task', 'edit_task', 'delete_task', 'view_task',
            'manage_team', 'view_team', 'manage_documents', 'view_documents',
            'view_analytics', 'manage_settings', 'view_settings'
        ];
        return in_array($roleOrPermission, $permissions);
    }
    
    /**
     * Check if user has required role
     */
    private function checkRole($user, string $role): bool
    {
        // Get user's roles (you might need to implement this based on your role system)
        $userRoles = $this->getUserRoles($user);
        
        return in_array($role, $userRoles);
    }
    
    /**
     * Check if user has required permission
     */
    private function checkPermission($user, string $permission, Request $request, ?string $projectParam = null): bool
    {
        // Get user's permissions
        $userPermissions = $this->getUserPermissions($user);
        
        // Check if user has the permission
        if (!in_array($permission, $userPermissions)) {
            return false;
        }
        
        // If permission is project-specific, check project access
        if ($projectParam && $this->isProjectSpecificPermission($permission)) {
            return $this->checkProjectAccess($user, $request, $projectParam);
        }
        
        return true;
    }
    
    /**
     * Get user's roles
     */
    private function getUserRoles($user): array
    {
        // This is a simplified implementation
        // You might want to implement a proper role system
        $roles = [];
        
        if ($user->isSuperAdmin()) {
            $roles[] = 'admin';
        }
        
        // Add more role logic based on your system
        if ($user->hasRole('project_manager')) {
            $roles[] = 'project_manager';
        }
        
        if ($user->hasRole('team_member')) {
            $roles[] = 'team_member';
        }
        
        return $roles;
    }
    
    /**
     * Get user's permissions
     */
    private function getUserPermissions($user): array
    {
        // This is a simplified implementation
        // You might want to implement a proper permission system
        $permissions = [];
        
        if ($user->isSuperAdmin()) {
            $permissions = [
                'create_project', 'edit_project', 'delete_project', 'view_project',
                'create_task', 'edit_task', 'delete_task', 'view_task',
                'manage_team', 'view_team', 'manage_documents', 'view_documents',
                'view_analytics', 'manage_settings', 'view_settings'
            ];
        } else {
            // Add permission logic based on user's roles
            if ($user->hasRole('project_manager')) {
                $permissions = array_merge($permissions, [
                    'create_project', 'edit_project', 'view_project',
                    'create_task', 'edit_task', 'view_task',
                    'manage_team', 'view_team', 'view_documents',
                    'view_analytics', 'view_settings'
                ]);
            }
            
            if ($user->hasRole('team_member')) {
                $permissions = array_merge($permissions, [
                    'view_project', 'create_task', 'edit_task', 'view_task',
                    'view_team', 'view_documents'
                ]);
            }
        }
        
        return array_unique($permissions);
    }
    
    /**
     * Check if permission is project-specific
     */
    private function isProjectSpecificPermission(string $permission): bool
    {
        $projectSpecificPermissions = [
            'edit_project', 'delete_project', 'view_project',
            'create_task', 'edit_task', 'delete_task', 'view_task',
            'manage_documents', 'view_documents'
        ];
        
        return in_array($permission, $projectSpecificPermissions);
    }
    
    /**
     * Check project access
     */
    private function checkProjectAccess($user, Request $request, string $projectParam): bool
    {
        $projectId = $request->route($projectParam) ?? $request->input($projectParam);
        
        if (!$projectId) {
            return false;
        }
        
        // Check if user has access to this project
        $project = \App\Models\Project::where('id', $projectId)
            ->where('tenant_id', $user->tenant_id)
            ->first();
        
        if (!$project) {
            return false;
        }
        
        // Add project context to request
        $request->attributes->set('project', $project);
        
        return true;
    }

    /**
     * Handle general RBAC guard when no specific permission is requested.
     */
    private function handleGeneralAccess($user, Request $request, Closure $next): Response
    {
        $allowedRoles = [
            'super_admin',
            'admin',
            'project_manager',
            'team_member',
            'client',
            'viewer',
            'designer',
            'site_engineer',
            'qc_engineer',
            'procurement',
            'finance',
        ];

        if (!$user->hasAnyRole($allowedRoles)) {
            Log::warning('Access denied: missing RBAC assignment', [
                'user_id' => $user->id ?? null,
                'email' => $user->email ?? null,
                'tenant_id' => $user->tenant_id ?? null,
                'roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames() : []
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Access Denied',
                'message' => 'You do not have sufficient RBAC assignments to access this resource',
                'code' => 'RBAC_ACCESS_DENIED'
            ], 403);
        }

        $request->attributes->set('required_role_permission', 'rbac:authenticated');
        $request->attributes->set('access_granted', true);

        return $next($request);
    }
}
