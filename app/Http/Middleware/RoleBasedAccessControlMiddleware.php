<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use LogicException;
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
    private array $legacyPermissionAliases = [];
    private array $actionAliasMap = [];
    private array $projectScopedModules = [];

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

        if ($this->shouldBypassRbac()) {
            $this->logRbacEvent('rbac.bypass', 'info', $user, $request, $roleOrPermission ?? 'rbac:authenticated', $projectParam);

            return $next($request);
        }
        
        if ($roleOrPermission === null) {
            return $this->handleGeneralAccess($user, $request, $next);
        }

        // Check if user has required role or permission
        $hasAccess = $this->checkAccess($user, $roleOrPermission, $request, $projectParam);
        
        if (!$hasAccess) {
            $this->logRbacEvent('rbac.deny', 'warning', $user, $request, $roleOrPermission, $projectParam);

            return response()->json([
                'success' => false,
                'error' => 'Access Denied',
                'message' => 'You do not have permission to access this resource',
                'code' => 'ACCESS_DENIED'
            ], 403);
        }

        $this->logRbacEvent('rbac.allow', 'info', $user, $request, $roleOrPermission, $projectParam);
        
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
     * Check if the string represents a permission identifier.
     */
    private function isPermission(string $roleOrPermission): bool
    {
        return str_contains($roleOrPermission, '.') || array_key_exists($roleOrPermission, $this->getLegacyPermissionAliases());
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
        foreach ($this->getPermissionCandidates($permission) as $candidate) {
            if (!$this->hasPermissionSet($user, $candidate)) {
                continue;
            }

            if ($projectParam && $this->isProjectSpecificPermission($candidate)) {
                if (!$this->checkProjectAccess($user, $request, $projectParam)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    private function getPermissionCandidates(string $permission): array
    {
        $candidates = [[$permission]];

        foreach ($this->resolveAliasTargets($permission) as $aliasSet) {
            $candidates[] = $aliasSet;
        }

        return $candidates;
    }

    private function resolveAliasTargets(string $permission): array
    {
        if (!str_contains($permission, '.')) {
            return [];
        }

        $lastDot = strrpos($permission, '.');
        if ($lastDot === false) {
            return [];
        }

        $module = substr($permission, 0, $lastDot);
        $action = strtolower(substr($permission, $lastDot + 1));

        $actionAliasMap = $this->getActionAliasMap();

        if (!isset($actionAliasMap[$action])) {
            return [];
        }

        $mappedActions = (array) $actionAliasMap[$action];

        $aliasSets = [];
        foreach ($mappedActions as $mappedAction) {
            $aliasSets[] = ["{$module}.{$mappedAction}"];
        }

        return $aliasSets;
    }

    private function getLegacyPermissionAliases(): array
    {
        if ($this->legacyPermissionAliases === []) {
            $this->legacyPermissionAliases = config('rbac.legacy_permission_aliases', []);
        }

        return $this->legacyPermissionAliases;
    }

    private function getActionAliasMap(): array
    {
        if ($this->actionAliasMap === []) {
            $this->actionAliasMap = config('rbac.action_alias_map', []);
        }

        return $this->actionAliasMap;
    }

    private function getProjectScopedModules(): array
    {
        if ($this->projectScopedModules === []) {
            $this->projectScopedModules = config('rbac.project_scoped_modules', []);
        }

        return $this->projectScopedModules;
    }

    private function hasPermissionSet($user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$user->hasPermission($permission)) {
                return false;
            }
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
     * Check if permission requires project context.
     */
    private function isProjectSpecificPermission(array|string $permission): bool
    {
        $permissions = is_array($permission) ? $permission : [$permission];

        foreach ($permissions as $perm) {
            if (!str_contains($perm, '.')) {
                continue;
            }

            $lastDot = strrpos($perm, '.');
            if ($lastDot === false) {
                continue;
            }

            $module = substr($perm, 0, $lastDot);

            if (in_array($module, $this->getProjectScopedModules(), true)) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * Check project access
     */
    private function checkProjectAccess($user, Request $request, string $projectParam): bool
    {
        $projectParamValue = $this->resolveProjectParamValue($request, $projectParam);
        $projectId = $this->normalizeProjectIdentifier($projectParamValue);
        
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
            $this->logRbacEvent(
                'rbac.deny',
                'warning',
                $user,
                $request,
                'rbac:authenticated',
                null,
                ['roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames() : []]
            );

            return response()->json([
                'success' => false,
                'error' => 'Access Denied',
                'message' => 'You do not have sufficient RBAC assignments to access this resource',
                'code' => 'RBAC_ACCESS_DENIED'
            ], 403);
        }

        $this->logRbacEvent('rbac.allow', 'info', $user, $request, 'rbac:authenticated');
        $request->attributes->set('required_role_permission', 'rbac:authenticated');
        $request->attributes->set('access_granted', true);

        return $next($request);
    }

    /**
     * Determine if RBAC should be bypassed in the current environment.
     */
    private function shouldBypassRbac(): bool
    {
        return app()->environment('testing') && config('rbac.bypass_testing', true);
    }

    /**
     * Log RBAC decisions with a consistent structure.
     */
    private function logRbacEvent(string $event, string $level, $user, Request $request, ?string $roleOrPermission = null, ?string $projectParam = null, array $extraContext = []): void
    {
        $context = [
            'user_id' => $user->id ?? null,
            'tenant_id' => $user->tenant_id ?? null,
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'required_role_permission' => $roleOrPermission ?? 'rbac:authenticated',
            'event' => $event,
        ];

        if ($projectParam) {
            $context['projectParam'] = $projectParam;
            $context['projectParamValue'] = $this->resolveProjectParamValue($request, $projectParam);
        }

        $context = array_merge($context, $extraContext);

        if ($level === 'warning') {
            Log::warning("RBAC event: {$event}", $context);
            return;
        }

        Log::info("RBAC event: {$event}", $context);
    }

    /**
     * Resolve the value of a project parameter from route or input.
     */
    private function resolveProjectParamValue(Request $request, string $projectParam): mixed
    {
        try {
            $routeValue = $request->route($projectParam);
        } catch (LogicException) {
            $routeValue = null;
        }

        return $routeValue ?? $request->input($projectParam);
    }

    private function normalizeProjectIdentifier(mixed $projectIdentifier): ?string
    {
        if (is_object($projectIdentifier)) {
            if (method_exists($projectIdentifier, 'getKey')) {
                return (string) $projectIdentifier->getKey();
            }

            if (property_exists($projectIdentifier, 'id')) {
                return (string) $projectIdentifier->id;
            }
        }

        if ($projectIdentifier !== null && $projectIdentifier !== false) {
            return (string) $projectIdentifier;
        }

        return null;
    }
}
