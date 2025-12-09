<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Permission Inspector Controller
 * 
 * Admin API for inspecting user permissions and their sources.
 * Round 236: Permission Inspector
 */
class PermissionInspectorController extends BaseApiV1Controller
{
    /**
     * Constructor - Check permission
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated',
                    'error' => ['id' => 'UNAUTHENTICATED']
                ], 401);
            }
            
            // Check if user has system.permissions.inspect
            if (!$this->hasPermission($user, 'system.permissions.inspect')) {
                Log::warning('User attempted to access permission inspector without permission', [
                    'user_id' => $user->id,
                    'role' => $user->role,
                    'url' => $request->url(),
                ]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient permissions',
                    'error' => [
                        'id' => 'PERMISSION_DENIED',
                        'code' => 'ADMIN_ACCESS_DENIED',
                        'details' => 'system.permissions.inspect permission required'
                    ]
                ], 403);
            }
            
            return $next($request);
        });
    }
    
    /**
     * Check if user has specific permission
     */
    private function hasPermission(User $user, string $permission): bool
    {
        // Super admin has all permissions
        if ($user->role === 'super_admin') {
            return true;
        }
        
        $role = $user->role ?? 'member';
        $permissions = config('permissions.roles.' . $role, []);
        
        if (in_array('*', $permissions)) {
            return true;
        }
        
        return in_array($permission, $permissions);
    }

    /**
     * Inspect user permissions
     * 
     * GET /api/v1/admin/permissions/inspect?user_id={userId}&filter={filter}&project_id={projectId}
     */
    public function inspect(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|string',
                'filter' => 'nullable|string|in:cost,document,task,project,user,system',
                'project_id' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                    'error' => [
                        'id' => 'VALIDATION_FAILED',
                    ],
                ], 422);
            }

            $userId = $request->input('user_id');
            $filter = $request->input('filter');
            $projectId = $request->input('project_id');

            // Resolve user (service-layer + tenant isolation)
            $user = User::with(['roles.permissions'])->find($userId);
            
            if (!$user) {
                return $this->errorResponse('User not found', 404, null, 'USER_NOT_FOUND');
            }

            // Verify tenant isolation (admin can inspect any user, but we log it)
            $currentUser = Auth::user();
            $currentTenantId = $this->normalizeTenantId($currentUser->tenant_id);
            $targetTenantId = $this->normalizeTenantId($user->tenant_id);
            if ($currentTenantId && $targetTenantId && $currentTenantId !== $targetTenantId) {
                Log::warning('Admin attempted to inspect user from different tenant', [
                    'admin_id' => $currentUser->id,
                    'admin_tenant_id' => $currentUser->tenant_id,
                    'target_user_id' => $user->id,
                    'target_tenant_id' => $user->tenant_id,
                ]);
                
                return $this->errorResponse(
                    'Cannot inspect user from different tenant',
                    403,
                    null,
                    'TENANT_ISOLATION_VIOLATION'
                );
            }

            // Build roles array with permissions
            $rolesData = [];
            $effectivePermissions = []; // permission => [roles that grant it]
            
            foreach ($user->roles as $role) {
                $rolePermissions = $this->getRolePermissionCodes($role);
                $rolesData[] = [
                    'name' => $role->name,
                    'permissions' => $rolePermissions,
                ];
                
                // Build effective permission map
                foreach ($rolePermissions as $perm) {
                    if (!isset($effectivePermissions[$perm])) {
                        $effectivePermissions[$perm] = [];
                    }
                    $effectivePermissions[$perm][] = $role->name;
                }
            }

            // Get all permissions from config catalog
            $allPermissions = $this->getAllValidPermissions();
            
            // Build permissions array with granted status and sources
            $permissionsData = [];
            foreach ($allPermissions as $permKey) {
                // Apply filter if provided
                if ($filter && !$this->matchesFilter($permKey, $filter)) {
                    continue;
                }
                
                $granted = isset($effectivePermissions[$permKey]);
                $permissionsData[] = [
                    'key' => $permKey,
                    'granted' => $granted,
                    'sources' => $granted ? $effectivePermissions[$permKey] : [],
                ];
            }

            // Build missing permissions list
            $missingPermissions = [];
            foreach ($allPermissions as $permKey) {
                // Apply filter if provided
                if ($filter && !$this->matchesFilter($permKey, $filter)) {
                    continue;
                }
                
                if (!isset($effectivePermissions[$permKey])) {
                    $missingPermissions[] = $permKey;
                }
            }

            return $this->successResponse([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'roles' => $rolesData,
                'permissions' => $permissionsData,
                'missing_permissions' => $missingPermissions,
            ], 'Permission inspection completed successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'method' => 'inspect',
                'user_id' => $request->input('user_id'),
                'request_id' => $request->header('X-Request-Id'),
            ]);
            return $this->errorResponse('Failed to inspect permissions', 500, null, 'PERMISSION_INSPECTION_ERROR');
        }
    }

    /**
     * Get all valid permission keys from config
     */
    private function getAllValidPermissions(): array
    {
        $permissionsConfig = config('permissions', []);
        $allPermissions = [];
        
        // Collect from role definitions
        if (isset($permissionsConfig['roles'])) {
            foreach ($permissionsConfig['roles'] as $rolePermissions) {
                foreach ($rolePermissions as $perm) {
                    if ($perm !== '*' && is_string($perm)) {
                        $allPermissions[] = $perm;
                    }
                }
            }
        }
        
        // Collect from groups
        if (isset($permissionsConfig['groups'])) {
            foreach ($permissionsConfig['groups'] as $groupPermissions) {
                foreach ($groupPermissions as $perm) {
                    if (is_string($perm)) {
                        $allPermissions[] = $perm;
                    }
                }
            }
        }
        
        return array_unique($allPermissions);
    }

    /**
     * Check if permission matches filter
     */
    private function matchesFilter(string $permissionKey, string $filter): bool
    {
        $filterMap = [
            'cost' => 'cost',
            'document' => 'documents',
            'task' => 'tasks',
            'project' => 'projects',
            'user' => 'users',
            'system' => 'system',
        ];
        
        $filterPrefix = $filterMap[$filter] ?? $filter;
        
        return str_starts_with($permissionKey, $filterPrefix . '.');
    }

    /**
     * Normalize tenant ID for comparison
     */
    private function normalizeTenantId(mixed $tenantId): ?string
    {
        if (!$tenantId) {
            return null;
        }

        if (is_object($tenantId) && method_exists($tenantId, '__toString')) {
            return (string) $tenantId;
        }

        return (string) $tenantId;
    }

    /**
     * Get permissions codes for a role (handles attribute collisions)
     */
    private function getRolePermissionCodes(Role $role): array
    {
        $permissions = $role->getRelationValue('permissions');

        if (!$permissions instanceof Collection) {
            $permissions = $role->permissions()->get();
        }

        return $permissions->pluck('code')->toArray();
    }
}
