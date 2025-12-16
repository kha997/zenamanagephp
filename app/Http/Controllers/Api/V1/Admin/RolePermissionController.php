<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Role Permission Controller
 * 
 * Admin API for managing roles and their permissions.
 * Round 233: Admin UI for Roles & Permissions
 */
class RolePermissionController extends BaseApiV1Controller
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
            
            // Check if user has users.manage_permissions
            if (!$this->hasPermission($user, 'users.manage_permissions')) {
                Log::warning('User attempted to access role permissions admin without permission', [
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
                        'details' => 'users.manage_permissions permission required'
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
     * List all roles with their assigned permissions
     * 
     * GET /api/v1/admin/roles
     */
    public function index(): JsonResponse
    {
        try {
            $roles = Role::with('permissions')
                ->orderBy('name')
                ->get()
                ->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'slug' => $this->roleNameToSlug($role->name),
                        'scope' => $role->scope ?? 'system',
                        'description' => $role->description,
                        'is_active' => $role->is_active ?? true,
                        'permissions' => $this->getRolePermissionCodes($role),
                    ];
                });

            return $this->successResponse($roles, 'Roles retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['method' => 'index']);
            return $this->errorResponse('Failed to retrieve roles', 500, null, 'ROLES_FETCH_ERROR');
        }
    }

    /**
     * Get all available permissions grouped by category
     * 
     * GET /api/v1/admin/permissions
     */
    public function permissions(): JsonResponse
    {
        try {
            $permissionsConfig = config('permissions', []);
            $groups = $permissionsConfig['groups'] ?? [];
            
            $result = [];
            
            foreach ($groups as $groupKey => $permissionKeys) {
                $groupLabel = $this->formatGroupLabel($groupKey);
                
                $permissions = [];
                foreach ($permissionKeys as $permissionKey) {
                    // Try to find permission in DB for description
                    $permission = Permission::where('code', $permissionKey)->first();
                    
                    $permissions[] = [
                        'key' => $permissionKey,
                        'label' => $this->formatPermissionLabel($permissionKey),
                        'description' => $permission->description ?? $this->generatePermissionDescription($permissionKey),
                    ];
                }
                
                $result[] = [
                    'key' => $groupKey,
                    'label' => $groupLabel,
                    'permissions' => $permissions,
                ];
            }
            
            return $this->successResponse(['groups' => $result], 'Permissions catalog retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['method' => 'permissions']);
            return $this->errorResponse('Failed to retrieve permissions catalog', 500, null, 'PERMISSIONS_FETCH_ERROR');
        }
    }

    /**
     * Update permissions for a role
     * 
     * PUT /api/v1/admin/roles/{role}/permissions
     */
    public function updatePermissions(Request $request, string $roleId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'permissions' => 'required|array',
                'permissions.*' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    'Validation failed',
                    422,
                    $validator->errors()->toArray(),
                    'VALIDATION_FAILED'
                );
            }

            $role = Role::find($roleId);
            
            if (!$role) {
                return $this->errorResponse('Role not found', 404, null, 'ROLE_NOT_FOUND');
            }

            $requestedPermissions = $request->input('permissions', []);
            
            // Get all valid permissions from config
            $allValidPermissions = $this->getAllValidPermissions();
            
            // Validate each permission exists in config
            $invalidPermissions = array_diff($requestedPermissions, $allValidPermissions);
            
            if (!empty($invalidPermissions)) {
                return $this->errorResponse(
                    'Invalid permissions: ' . implode(', ', $invalidPermissions),
                    422,
                    ['invalid_permissions' => $invalidPermissions],
                    'INVALID_PERMISSIONS'
                );
            }

            // Get or create permission records in DB
            $permissionIds = [];
            foreach ($requestedPermissions as $permissionCode) {
                $permission = Permission::firstOrCreate(
                    ['code' => $permissionCode],
                    [
                        'code' => $permissionCode,
                        'module' => $this->extractModule($permissionCode),
                        'action' => $this->extractAction($permissionCode),
                        'description' => $this->generatePermissionDescription($permissionCode),
                    ]
                );
                $permissionIds[] = $permission->id;
            }

            // Sync permissions to role using IDs
            DB::beginTransaction();
            try {
                // Use sync with IDs (not codes)
                $role->permissions()->sync($permissionIds);
                DB::commit();
                
                // Reload role with permissions
                $role->refresh();
                $role->load('permissions');
                
                return $this->successResponse([
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $this->roleNameToSlug($role->name),
                    'permissions' => $this->getRolePermissionCodes($role),
                ], 'Role permissions updated successfully');
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            $this->logError($e, [
                'method' => 'updatePermissions',
                'role_id' => $roleId,
                'request_id' => $request->header('X-Request-Id'),
            ]);
            return $this->errorResponse('Failed to update role permissions', 500, null, 'PERMISSIONS_UPDATE_ERROR');
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
     * Convert role name to slug
     */
    private function roleNameToSlug(string $name): string
    {
        return strtolower(str_replace([' ', '_'], '-', $name));
    }

    /**
     * Format group key to label
     */
    private function formatGroupLabel(string $key): string
    {
        $labels = [
            'user_management' => 'User Management',
            'project_management' => 'Project Management',
            'cost_management' => 'Cost Management',
            'task_management' => 'Task Management',
            'document_management' => 'Document Management',
            'client_management' => 'Client Management',
            'quote_management' => 'Quote Management',
            'template_management' => 'Template Management',
            'analytics' => 'Analytics & Reports',
            'notifications' => 'Notifications',
            'settings' => 'Settings',
        ];
        
        return $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    /**
     * Format permission key to label
     */
    private function formatPermissionLabel(string $key): string
    {
        // Convert "projects.cost.view" to "View Cost"
        $parts = explode('.', $key);
        $action = end($parts);
        $resource = $parts[count($parts) - 2] ?? $parts[0];
        
        return ucfirst($action) . ' ' . ucwords(str_replace('_', ' ', $resource));
    }

    /**
     * Generate permission description
     */
    private function generatePermissionDescription(string $key): ?string
    {
        $descriptions = [
            'users.manage_permissions' => 'Can manage role permissions and access control',
            'projects.cost.view' => 'Can view cost dashboards, contracts, COs, certificates, and payments',
            'projects.cost.edit' => 'Can create and edit cost-related data',
            'projects.cost.approve' => 'Can approve change orders and payment certificates',
            'projects.cost.export' => 'Can export cost reports and PDFs',
        ];
        
        return $descriptions[$key] ?? null;
    }

    /**
     * Extract module from permission code
     */
    private function extractModule(string $code): string
    {
        $parts = explode('.', $code);
        return $parts[0] ?? 'general';
    }

    /**
     * Extract action from permission code
     */
    private function extractAction(string $code): string
    {
        $parts = explode('.', $code);
        return end($parts) ?? 'view';
    }

    /**
     * Get permission codes for a role (handles attribute collisions)
     * 
     * The zena_roles table may have a JSON permissions attribute that masks
     * the permissions() relationship. This method safely retrieves permissions
     * from the relationship, never from the attribute.
     * 
     * @param Role $role
     * @return array<string>
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
