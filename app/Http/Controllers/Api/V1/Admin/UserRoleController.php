<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\TenancyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * User Role Controller
 * 
 * Admin API for managing user-role assignments.
 * Round 234: Admin RBAC - Roles CRUD + User-Role Assignment
 */
class UserRoleController extends BaseApiV1Controller
{
    /**
     * System roles that cannot be assigned via this endpoint
     */
    private const FORBIDDEN_SYSTEM_ROLES = ['owner']; // Only owner cannot be assigned via API

    private TenancyService $tenancyService;
    private AuditLogService $auditLogService;

    public function __construct(TenancyService $tenancyService, AuditLogService $auditLogService)
    {
        $this->tenancyService = $tenancyService;
        $this->auditLogService = $auditLogService;
        
        $this->middleware(function ($request, $next) {
            $user = Auth::user();
            
            if (!$user) {
                return $this->errorResponse('Unauthenticated', 401, null, 'UNAUTHENTICATED');
            }
            
            // Check if user has system.users.manage_roles permission
            if (!$this->hasPermission($user, 'system.users.manage_roles')) {
                Log::warning('User attempted to access user role management without permission', [
                    'user_id' => $user->id,
                    'role' => $user->role,
                    'url' => $request->url(),
                ]);
                
                return $this->errorResponse(
                    'Insufficient permissions',
                    403,
                    ['details' => 'system.users.manage_roles permission required'],
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
     * List all users with their roles
     * 
     * GET /api/v1/admin/users
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = User::with('roles')
                ->orderBy('name');

            // Optional search filter
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Optional tenant filter
            if ($request->has('tenant_id')) {
                $query->where('tenant_id', $request->input('tenant_id'));
            }

            $users = $query->get()->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'tenant_id' => $user->tenant_id,
                    'is_active' => $user->is_active,
                    'roles' => $user->roles->map(function ($role) {
                        return [
                            'id' => $role->id,
                            'name' => $role->name,
                            'slug' => $this->roleNameToSlug($role->name),
                        ];
                    })->toArray(),
                    'created_at' => $user->created_at?->toISOString(),
                ];
            });

            return $this->successResponse($users, 'Users retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['method' => 'index']);
            return $this->errorResponse('Failed to retrieve users', 500, null, 'USERS_FETCH_ERROR');
        }
    }

    /**
     * Assign roles to a user
     * 
     * PUT /api/v1/admin/users/{usr}/roles
     * 
     * Round 246: Changed parameter from {user} to {usr} to avoid route-model-binding
     */
    public function updateRoles(Request $request, string $usr): JsonResponse
    {
        try {
            // Check if roles field is present (required, but can be empty array)
            if (!$request->has('roles')) {
                // Return validation errors in Laravel standard format for assertJsonValidationErrors
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => [
                        'roles' => ['The roles field is required.'],
                    ],
                    'error' => [
                        'id' => 'VALIDATION_FAILED',
                    ],
                ], 422);
            }

            // Validate roles is an array and each element is a string
            $validator = Validator::make($request->all(), [
                'roles' => 'array', // Must be array (can be empty)
                'roles.*' => 'string', // Each element must be string (skipped if array is empty)
            ]);

            if ($validator->fails()) {
                // Return validation errors in Laravel standard format for assertJsonValidationErrors
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                    'error' => [
                        'id' => 'VALIDATION_FAILED',
                    ],
                ], 422);
            }

            // Round 246: Resolve user through service layer (no route model binding)
            $user = User::find($usr);
            
            if (!$user) {
                return $this->errorResponse('User not found', 404, null, 'USER_NOT_FOUND');
            }

            // Verify user belongs to a tenant
            if (!$user->tenant_id) {
                return $this->errorResponse(
                    'User must belong to a tenant',
                    422,
                    ['user_id' => ['User does not belong to any tenant']],
                    'USER_NO_TENANT'
                );
            }

            $requestedRoles = $request->input('roles', []);
            
            // Resolve role IDs from names or IDs
            $roleIds = [];
            foreach ($requestedRoles as $roleIdentifier) {
                // Try to find by ID first
                $role = Role::find($roleIdentifier);
                
                // If not found by ID, try by name
                if (!$role) {
                    $role = Role::where('name', $roleIdentifier)->first();
                }
                
                if (!$role) {
                    return $this->errorResponse(
                        "Role not found: {$roleIdentifier}",
                        404,
                        ['role' => $roleIdentifier],
                        'ROLE_NOT_FOUND'
                    );
                }

                // Check if role is forbidden
                if ($this->isForbiddenRole($role->name)) {
                    return $this->errorResponse(
                        "Cannot assign forbidden system role: {$role->name}",
                        403,
                        ['role' => $role->name],
                        'FORBIDDEN_ROLE'
                    );
                }

                $roleIds[] = $role->id;
            }

            DB::beginTransaction();
            try {
                // Capture before state
                $beforeRoles = $user->roles->pluck('name')->toArray();
                
                // Sync roles via pivot table
                $user->roles()->sync($roleIds);
                
                // Reload user with roles
                $user->refresh();
                $user->load('roles');
                
                // Capture after state
                $afterRoles = $user->roles->pluck('name')->toArray();
                
                // Round 235: Audit log
                // Round 246: Cast Auth::id() to string for audit service
                $this->auditLogService->record(
                    tenantId: $user->tenant_id,
                    userId: (string) Auth::id(),
                    action: 'user.roles_updated',
                    entityType: 'User',
                    entityId: $user->id,
                    before: ['roles' => $beforeRoles],
                    after: ['roles' => $afterRoles],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );
                
                DB::commit();

                return $this->successResponse([
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->map(function ($role) {
                        return [
                            'id' => $role->id,
                            'name' => $role->name,
                            'slug' => $this->roleNameToSlug($role->name),
                        ];
                    })->toArray(),
                ], 'User roles updated successfully');
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            $this->logError($e, [
                'method' => 'updateRoles',
                'user_id' => $usr,
                'request_id' => $request->header('X-Request-Id'),
            ]);
            return $this->errorResponse('Failed to update user roles', 500, null, 'USER_ROLES_UPDATE_ERROR');
        }
    }

    /**
     * Check if role name is forbidden
     */
    private function isForbiddenRole(string $roleName): bool
    {
        return in_array(strtolower($roleName), array_map('strtolower', self::FORBIDDEN_SYSTEM_ROLES));
    }

    /**
     * Convert role name to slug
     */
    private function roleNameToSlug(string $name): string
    {
        return \Illuminate\Support\Str::slug($name);
    }
}
