<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use Src\Foundation\Permission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Permission Service
 * 
 * Handles RBAC (Role-Based Access Control) and permission validation
 * with advanced features including role hierarchy, caching, and audit logging.
 */
class PermissionService
{
    private PermissionCacheService $cacheService;
    private SecurityAuditService $auditService;
    
    // Role hierarchy definition
    private const ROLE_HIERARCHY = [
        'super_admin' => ['admin', 'project_manager', 'member', 'client'],
        'admin' => ['project_manager', 'member', 'client'],
        'project_manager' => ['member', 'client'],
        'member' => ['client'],
        'client' => []
    ];
    
    // Permission inheritance rules
    private const PERMISSION_INHERITANCE = [
        'super_admin' => ['*'], // All permissions
        'admin' => [
            'projects.*', 'tasks.*', 'clients.*', 'teams.*',
            'users.view', 'users.modify', 'reports.*'
        ],
        'project_manager' => [
            'projects.view', 'projects.create', 'projects.modify',
            'tasks.*', 'clients.view', 'teams.view'
        ],
        'member' => [
            'projects.view', 'tasks.view', 'tasks.create', 'tasks.modify',
            'clients.view'
        ],
        'client' => [
            'projects.view', 'tasks.view', 'clients.view'
        ]
    ];
    
    public function __construct(
        PermissionCacheService $cacheService,
        SecurityAuditService $auditService
    ) {
        $this->cacheService = $cacheService;
        $this->auditService = $auditService;
    }
    /**
     * Check if user can create projects
     */
    public function canUserCreateProjects(string $userId, string $tenantId): bool
    {
        try {
            $user = User::where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$user) {
                Log::warning('Permission check failed: User not found', [
                    'user_id' => $userId,
                    'tenant_id' => $tenantId
                ]);
                return false;
            }

            // Use Permission class to check permissions
            $userRoles = [['id' => $user->role ?? 'member', 'scope' => 'system']];
            $permissions = Permission::getUserPermissions($userId, null, $userRoles);
            
            return in_array('projects.create', array_keys($permissions));
        } catch (\Exception $e) {
            Log::error('Permission check error: ' . $e->getMessage(), [
                'user_id' => $userId,
                'tenant_id' => $tenantId
            ]);
            return false;
        }
    }

    /**
     * Check if user can access project
     */
    public function canUserAccessProject(Project $project, string $userId): bool
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                return false;
            }

            // Super admin can access all projects
            if ($user->role === 'super_admin') {
                return true;
            }

            // Check tenant isolation
            if ($project->tenant_id !== $user->tenant_id) {
                Log::warning('Tenant isolation violation attempt', [
                    'user_id' => $userId,
                    'user_tenant_id' => $user->tenant_id,
                    'project_id' => $project->id,
                    'project_tenant_id' => $project->tenant_id
                ]);
                return false;
            }

            // Check if user is project owner or team member
            if ($project->user_id === $userId) {
                return true;
            }

            // Check if user is team member (if project has team members)
            // This would require a project_team_members table relationship
            // For now, we'll allow admin and project_manager roles
            $allowedRoles = ['admin', 'project_manager'];
            return in_array($user->role, $allowedRoles);
        } catch (\Exception $e) {
            Log::error('Project access check error: ' . $e->getMessage(), [
                'user_id' => $userId,
                'project_id' => $project->id ?? null
            ]);
            return false;
        }
    }

    /**
     * Check if user can access task
     */
    public function canUserAccessTask(Task $task, string $userId): bool
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                return false;
            }

            // Super admin can access all tasks
            if ($user->role === 'super_admin') {
                return true;
            }

            // Check tenant isolation
            if ($task->tenant_id !== $user->tenant_id) {
                Log::warning('Tenant isolation violation attempt for task', [
                    'user_id' => $userId,
                    'user_tenant_id' => $user->tenant_id,
                    'task_id' => $task->id,
                    'task_tenant_id' => $task->tenant_id
                ]);
                return false;
            }

            // Check if user is task owner or assignee
            if ($task->user_id === $userId) {
                return true;
            }

            // Check if user is assigned to the task
            if ($task->assigned_to === $userId) {
                return true;
            }

            // Check if user has project access
            if ($task->project_id) {
                $project = Project::find($task->project_id);
                if ($project && $this->canUserAccessProject($project, $userId)) {
                    return true;
                }
            }

            // Allow admin and project_manager roles
            $allowedRoles = ['admin', 'project_manager'];
            return in_array($user->role, $allowedRoles);
        } catch (\Exception $e) {
            Log::error('Task access check error: ' . $e->getMessage(), [
                'user_id' => $userId,
                'task_id' => $task->id ?? null
            ]);
            return false;
        }
    }

    /**
     * Check if user can modify project
     */
    public function canUserModifyProject(Project $project, string $userId): bool
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                return false;
            }

            // Super admin can modify all projects
            if ($user->role === 'super_admin') {
                return true;
            }

            // Check tenant isolation
            if ($project->tenant_id !== $user->tenant_id) {
                return false;
            }

            // Project owner can modify
            if ($project->user_id === $userId) {
                return true;
            }

            // Admin and project_manager can modify
            $allowedRoles = ['admin', 'project_manager'];
            return in_array($user->role, $allowedRoles);
        } catch (\Exception $e) {
            Log::error('Project modify check error: ' . $e->getMessage(), [
                'user_id' => $userId,
                'project_id' => $project->id ?? null
            ]);
            return false;
        }
    }

    /**
     * Check if user can modify task
     */
    public function canUserModifyTask(Task $task, string $userId): bool
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                return false;
            }

            // Super admin can modify all tasks
            if ($user->role === 'super_admin') {
                return true;
            }

            // Check tenant isolation
            if ($task->tenant_id !== $user->tenant_id) {
                return false;
            }

            // Task owner can modify
            if ($task->user_id === $userId) {
                return true;
            }

            // Task assignee can modify
            if ($task->assigned_to === $userId) {
                return true;
            }

            // Check project permissions
            if ($task->project_id) {
                $project = Project::find($task->project_id);
                if ($project && $this->canUserModifyProject($project, $userId)) {
                    return true;
                }
            }

            // Admin and project_manager can modify
            $allowedRoles = ['admin', 'project_manager'];
            return in_array($user->role, $allowedRoles);
        } catch (\Exception $e) {
            Log::error('Task modify check error: ' . $e->getMessage(), [
                'user_id' => $userId,
                'task_id' => $task->id ?? null
            ]);
            return false;
        }
    }

    /**
     * Check if user can delete project
     */
    public function canUserDeleteProject(Project $project, string $userId): bool
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                return false;
            }

            // Super admin can delete all projects
            if ($user->role === 'super_admin') {
                return true;
            }

            // Check tenant isolation
            if ($project->tenant_id !== $user->tenant_id) {
                return false;
            }

            // Only project owner and admin can delete
            if ($project->user_id === $userId) {
                return true;
            }

            return $user->role === 'admin';
        } catch (\Exception $e) {
            Log::error('Project delete check error: ' . $e->getMessage(), [
                'user_id' => $userId,
                'project_id' => $project->id ?? null
            ]);
            return false;
        }
    }

    /**
     * Check if user can delete task
     */
    public function canUserDeleteTask(Task $task, string $userId): bool
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                return false;
            }

            // Super admin can delete all tasks
            if ($user->role === 'super_admin') {
                return true;
            }

            // Check tenant isolation
            if ($task->tenant_id !== $user->tenant_id) {
                return false;
            }

            // Task owner can delete
            if ($task->user_id === $userId) {
                return true;
            }

            // Check project permissions
            if ($task->project_id) {
                $project = Project::find($task->project_id);
                if ($project && $this->canUserDeleteProject($project, $userId)) {
                    return true;
                }
            }

            // Admin can delete
            return $user->role === 'admin';
        } catch (\Exception $e) {
            Log::error('Task delete check error: ' . $e->getMessage(), [
                'user_id' => $userId,
                'task_id' => $task->id ?? null
            ]);
            return false;
        }
    }

    /**
     * Get user permissions for a specific resource
     */
    public function getUserPermissions(string $userId, string $resourceType, $resource = null): array
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                return [];
            }

            $permissions = [];

            switch ($resourceType) {
                case 'project':
                    if ($resource instanceof Project) {
                        $permissions = [
                            'view' => $this->canUserAccessProject($resource, $userId),
                            'modify' => $this->canUserModifyProject($resource, $userId),
                            'delete' => $this->canUserDeleteProject($resource, $userId)
                        ];
                    }
                    break;

                case 'task':
                    if ($resource instanceof Task) {
                        $permissions = [
                            'view' => $this->canUserAccessTask($resource, $userId),
                            'modify' => $this->canUserModifyTask($resource, $userId),
                            'delete' => $this->canUserDeleteTask($resource, $userId)
                        ];
                    }
                    break;

                case 'projects':
                    $permissions = [
                        'create' => $this->canUserCreateProjects($userId, $user->tenant_id)
                    ];
                    break;
            }

            return $permissions;
        } catch (\Exception $e) {
            Log::error('Get user permissions error: ' . $e->getMessage(), [
                'user_id' => $userId,
                'resource_type' => $resourceType
            ]);
            return [];
        }
    }
    
    /**
     * Get effective permissions for a user considering role hierarchy
     */
    public function getEffectivePermissions(int $userId, int $tenantId): array
    {
        try {
            // Try to get from cache first
            $cachedPermissions = $this->cacheService->getCachedPermissions($userId, $tenantId);
            if (!empty($cachedPermissions)) {
                return $cachedPermissions;
            }
            
            $user = User::find($userId);
            if (!$user || $user->tenant_id != $tenantId) {
                return [];
            }
            
            $effectivePermissions = [];
            $userRole = $user->role ?? 'member';
            
            // Get permissions from role hierarchy
            $effectivePermissions = array_merge(
                $effectivePermissions,
                $this->getRolePermissions($userRole)
            );
            
            // Get permissions from inherited roles
            $inheritedRoles = $this->getRoleHierarchy($userRole);
            foreach ($inheritedRoles as $inheritedRole) {
                $effectivePermissions = array_merge(
                    $effectivePermissions,
                    $this->getRolePermissions($inheritedRole)
                );
            }
            
            // Remove duplicates and sort
            $effectivePermissions = array_unique($effectivePermissions);
            sort($effectivePermissions);
            
            // Cache the result
            $this->cacheService->warmUpPermissionCache($userId, $tenantId);
            
            return $effectivePermissions;
            
        } catch (\Exception $e) {
            Log::error('Get effective permissions error', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Check permission with role hierarchy consideration
     */
    public function checkPermissionWithHierarchy(string $permission, int $userId, int $tenantId): bool
    {
        try {
            $effectivePermissions = $this->getEffectivePermissions($userId, $tenantId);
            
            // Check exact permission
            if (in_array($permission, $effectivePermissions)) {
                $this->auditService->logPermissionCheck($permission, $userId, $tenantId, true);
                return true;
            }
            
            // Check wildcard permissions
            foreach ($effectivePermissions as $effectivePermission) {
                if ($this->matchesWildcardPermission($permission, $effectivePermission)) {
                    $this->auditService->logPermissionCheck($permission, $userId, $tenantId, true);
                    return true;
                }
            }
            
            $this->auditService->logPermissionCheck($permission, $userId, $tenantId, false);
            return false;
            
        } catch (\Exception $e) {
            Log::error('Permission hierarchy check error', [
                'permission' => $permission,
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            
            $this->auditService->logPermissionCheck($permission, $userId, $tenantId, false);
            return false;
        }
    }
    
    /**
     * Get role hierarchy for a given role
     */
    public function getRoleHierarchy(int $roleId): array
    {
        $role = $this->getRoleById($roleId);
        if (!$role) {
            return [];
        }
        
        return self::ROLE_HIERARCHY[$role->name] ?? [];
    }
    
    /**
     * Get permissions for a specific role
     */
    private function getRolePermissions(string $roleName): array
    {
        return self::PERMISSION_INHERITANCE[$roleName] ?? [];
    }
    
    /**
     * Check if a permission matches a wildcard pattern
     */
    private function matchesWildcardPermission(string $permission, string $pattern): bool
    {
        if ($pattern === '*') {
            return true;
        }
        
        if (str_ends_with($pattern, '.*')) {
            $prefix = substr($pattern, 0, -2);
            return str_starts_with($permission, $prefix . '.');
        }
        
        return $permission === $pattern;
    }
    
    /**
     * Get role by ID (placeholder - would need actual Role model)
     */
    private function getRoleById(int $roleId): ?object
    {
        // This would typically query a roles table
        // For now, return a mock object
        return (object) ['id' => $roleId, 'name' => 'member'];
    }
    
    /**
     * Invalidate user permissions cache
     */
    public function invalidateUserPermissions(int $userId, int $tenantId): void
    {
        $this->cacheService->invalidateUserPermissions($userId, $tenantId);
    }
    
    /**
     * Invalidate tenant permissions cache
     */
    public function invalidateTenantPermissions(int $tenantId): void
    {
        $this->cacheService->invalidateTenantPermissions($tenantId);
    }
    
    /**
     * Get permission cache statistics
     */
    public function getPermissionCacheStatistics(): array
    {
        return $this->cacheService->getCacheStatistics();
    }
}