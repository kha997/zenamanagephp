<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Role Management Controller
 * 
 * Admin API for managing roles (CRUD operations).
 * Round 234: Admin RBAC - Roles CRUD + User-Role Assignment
 */
class RoleManagementController extends BaseApiV1Controller
{
    /**
     * System roles that cannot be renamed or deleted
     */
    private const SYSTEM_ROLES = ['owner', 'admin', 'accountant', 'project_manager'];

    private AuditLogService $auditLogService;

    /**
     * Constructor - Check permission
     */
    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
        $this->middleware(function ($request, $next) {
            $user = Auth::user();
            
            if (!$user) {
                return $this->errorResponse('Unauthenticated', 401, null, 'UNAUTHENTICATED');
            }
            
            // Check if user has system.roles.manage permission
            if (!$this->hasPermission($user, 'system.roles.manage')) {
                Log::warning('User attempted to access role management without permission', [
                    'user_id' => $user->id,
                    'role' => $user->role,
                    'url' => $request->url(),
                ]);
                
                return $this->errorResponse(
                    'Insufficient permissions',
                    403,
                    ['details' => 'system.roles.manage permission required'],
                    'PERMISSION_DENIED'
                );
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
     * List all roles with their permissions
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
                        'is_system' => $this->isSystemRole($role->name),
                        'permissions' => $role->permissions ? $role->permissions->pluck('code')->toArray() : [],
                        'created_at' => $role->created_at?->toISOString(),
                        'updated_at' => $role->updated_at?->toISOString(),
                    ];
                });

            return $this->successResponse($roles, 'Roles retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['method' => 'index']);
            return $this->errorResponse('Failed to retrieve roles', 500, null, 'ROLES_FETCH_ERROR');
        }
    }

    /**
     * Create a new role
     * 
     * POST /api/v1/admin/roles
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Check if name matches system role (case-insensitive) BEFORE validation
            $nameLower = strtolower($request->input('name', ''));
            if (in_array($nameLower, array_map('strtolower', self::SYSTEM_ROLES))) {
                return $this->errorResponse(
                    'Cannot create role with system role name',
                    422,
                    ['name' => ['Role name conflicts with system role']],
                    'SYSTEM_ROLE_CONFLICT'
                );
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:zena_roles,name',
                'description' => 'nullable|string|max:1000',
                'scope' => 'nullable|string|in:system,custom,project',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    'Validation failed',
                    422,
                    $validator->errors()->toArray(),
                    'VALIDATION_FAILED'
                );
            }

            DB::beginTransaction();
            try {
                $role = Role::create([
                    'name' => $request->input('name'),
                    'description' => $request->input('description'),
                    'scope' => $request->input('scope', Role::SCOPE_CUSTOM),
                    'is_active' => true,
                ]);

                // Sync to legacy roles table is handled by Role model boot() method
                
                // Round 235: Audit log
                $this->auditLogService->record(
                    tenantId: Auth::user()?->tenant_id,
                    userId: Auth::id(),
                    action: 'role.created',
                    entityType: 'Role',
                    entityId: $role->id,
                    before: null,
                    after: [
                        'name' => $role->name,
                        'description' => $role->description,
                        'scope' => $role->scope,
                    ],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );
                
                DB::commit();

                $role->load('permissions');

                return $this->successResponse([
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $this->roleNameToSlug($role->name),
                    'scope' => $role->scope,
                    'description' => $role->description,
                    'is_active' => $role->is_active,
                    'is_system' => false,
                    'permissions' => $role->permissions ? $role->permissions->pluck('code')->toArray() : [],
                ], 'Role created successfully', 201);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            $this->logError($e, [
                'method' => 'store',
                'request_id' => $request->header('X-Request-Id'),
            ]);
            return $this->errorResponse('Failed to create role', 500, null, 'ROLE_CREATE_ERROR');
        }
    }

    /**
     * Update a role
     * 
     * PUT /api/v1/admin/roles/{role}
     */
    public function update(Request $request, string $roleId): JsonResponse
    {
        try {
            // Resolve role through service layer (no route model binding)
            $role = Role::find($roleId);
            
            if (!$role) {
                return $this->errorResponse('Role not found', 404, null, 'ROLE_NOT_FOUND');
            }

            // Check if it's a system role
            if ($this->isSystemRole($role->name)) {
                return $this->errorResponse(
                    'Cannot update system role',
                    403,
                    ['role' => ['System roles cannot be modified']],
                    'SYSTEM_ROLE_PROTECTED'
                );
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255|unique:zena_roles,name,' . $role->id,
                'description' => 'nullable|string|max:1000',
                'scope' => 'sometimes|string|in:system,custom,project',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    'Validation failed',
                    422,
                    $validator->errors()->toArray(),
                    'VALIDATION_FAILED'
                );
            }

            // Check if new name conflicts with system role
            if ($request->has('name')) {
                $nameLower = strtolower($request->input('name'));
                if (in_array($nameLower, array_map('strtolower', self::SYSTEM_ROLES))) {
                    return $this->errorResponse(
                        'Cannot rename role to system role name',
                        422,
                        ['name' => ['Role name conflicts with system role']],
                        'SYSTEM_ROLE_CONFLICT'
                    );
                }
            }

            DB::beginTransaction();
            try {
                // Capture before state
                $before = [
                    'name' => $role->name,
                    'description' => $role->description,
                    'scope' => $role->scope,
                ];
                
                $role->update($request->only(['name', 'description', 'scope']));

                // Sync to legacy roles table is handled by Role model boot() method
                
                // Round 235: Audit log
                $role->refresh();
                $this->auditLogService->record(
                    tenantId: Auth::user()?->tenant_id,
                    userId: Auth::id(),
                    action: 'role.updated',
                    entityType: 'Role',
                    entityId: $role->id,
                    before: $before,
                    after: [
                        'name' => $role->name,
                        'description' => $role->description,
                        'scope' => $role->scope,
                    ],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );
                
                DB::commit();

                $role->refresh();
                $role->load('permissions');

                return $this->successResponse([
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $this->roleNameToSlug($role->name),
                    'scope' => $role->scope,
                    'description' => $role->description,
                    'is_active' => $role->is_active,
                    'is_system' => $this->isSystemRole($role->name),
                    'permissions' => $role->permissions ? $role->permissions->pluck('code')->toArray() : [],
                ], 'Role updated successfully');
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            $this->logError($e, [
                'method' => 'update',
                'role_id' => $roleId,
                'request_id' => $request->header('X-Request-Id'),
            ]);
            return $this->errorResponse('Failed to update role', 500, null, 'ROLE_UPDATE_ERROR');
        }
    }

    /**
     * Delete a role
     * 
     * DELETE /api/v1/admin/roles/{role}
     */
    public function destroy(string $roleId): JsonResponse
    {
        try {
            // Resolve role through service layer (no route model binding)
            $role = Role::find($roleId);
            
            if (!$role) {
                return $this->errorResponse('Role not found', 404, null, 'ROLE_NOT_FOUND');
            }

            // Check if it's a system role
            if ($this->isSystemRole($role->name)) {
                return $this->errorResponse(
                    'Cannot delete system role',
                    403,
                    ['role' => ['System roles cannot be deleted']],
                    'SYSTEM_ROLE_PROTECTED'
                );
            }

            DB::beginTransaction();
            try {
                // Check if role is assigned to any users
                $userCount = $role->systemUsers()->count();
                if ($userCount > 0) {
                    return $this->errorResponse(
                        "Cannot delete role: {$userCount} user(s) are assigned to this role",
                        409,
                        ['user_count' => $userCount],
                        'ROLE_IN_USE'
                    );
                }

                // Capture before state
                $before = [
                    'name' => $role->name,
                    'description' => $role->description,
                    'scope' => $role->scope,
                ];
                
                $role->delete();

                // Sync to legacy roles table is handled by Role model boot() method
                
                // Round 235: Audit log
                $this->auditLogService->record(
                    tenantId: Auth::user()?->tenant_id,
                    userId: Auth::id(),
                    action: 'role.deleted',
                    entityType: 'Role',
                    entityId: $role->id,
                    before: $before,
                    after: null,
                    ipAddress: request()->ip(),
                    userAgent: request()->userAgent()
                );
                
                DB::commit();

                return $this->successResponse(null, 'Role deleted successfully');
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            $this->logError($e, [
                'method' => 'destroy',
                'role_id' => $roleId,
            ]);
            return $this->errorResponse('Failed to delete role', 500, null, 'ROLE_DELETE_ERROR');
        }
    }

    /**
     * Check if role name is a system role
     */
    private function isSystemRole(string $roleName): bool
    {
        return in_array(strtolower($roleName), array_map('strtolower', self::SYSTEM_ROLES));
    }

    /**
     * Convert role name to slug
     */
    private function roleNameToSlug(string $name): string
    {
        return Str::slug($name);
    }
}
