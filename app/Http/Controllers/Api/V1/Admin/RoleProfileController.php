<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Models\RoleProfile;
use App\Models\User;
use App\Services\RoleProfileService;
use App\Services\TenancyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * RoleProfileController
 * 
 * Round 244: Role Access Profiles
 * 
 * Admin API for managing role profiles (templates) and assigning them to users.
 */
class RoleProfileController extends BaseApiV1Controller
{
    private RoleProfileService $roleProfileService;
    private TenancyService $tenancyService;

    public function __construct(RoleProfileService $roleProfileService, TenancyService $tenancyService)
    {
        $this->roleProfileService = $roleProfileService;
        $this->tenancyService = $tenancyService;
        
        $this->middleware(function ($request, $next) {
            $user = Auth::user();
            
            if (!$user) {
                return $this->errorResponse('Unauthenticated', 401, null, 'UNAUTHENTICATED');
            }
            
            // Check if user has system.role_profiles.manage permission
            if (!$this->hasPermission($user, 'system.role_profiles.manage')) {
                Log::warning('User attempted to access role profile management without permission', [
                    'user_id' => $user->id,
                    'role' => $user->role,
                    'url' => $request->url(),
                ]);
                
                return $this->errorResponse(
                    'Insufficient permissions',
                    403,
                    ['details' => 'system.role_profiles.manage permission required'],
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
     * List all role profiles for the tenant
     * 
     * GET /api/v1/admin/role-profiles
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenancyService->resolveActiveTenant($user, $request);
            
            if (!$tenant) {
                return $this->errorResponse('Tenant not found', 404, null, 'TENANT_NOT_FOUND');
            }

            $profiles = $this->roleProfileService->listProfiles($tenant->id);

            $profilesData = $profiles->map(function ($profile) {
                $roleModels = $profile->getRoleModels();
                
                return [
                    'id' => $profile->id,
                    'name' => $profile->name,
                    'description' => $profile->description,
                    'roles' => $roleModels->map(function ($role) {
                        return [
                            'id' => $role->id,
                            'name' => $role->name,
                        ];
                    })->toArray(),
                    'role_ids' => $profile->roles, // Original IDs/slugs
                    'is_active' => $profile->is_active,
                    'tenant_id' => $profile->tenant_id,
                    'created_at' => $profile->created_at?->toISOString(),
                    'updated_at' => $profile->updated_at?->toISOString(),
                ];
            });

            return $this->successResponse($profilesData, 'Role profiles retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['method' => 'index']);
            return $this->errorResponse('Failed to retrieve role profiles', 500, null, 'PROFILES_FETCH_ERROR');
        }
    }

    /**
     * Get a single role profile
     * 
     * GET /api/v1/admin/role-profiles/{profile}
     */
    public function show(Request $request, string $profileId): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenancyService->resolveActiveTenant($user, $request);
            
            if (!$tenant) {
                return $this->errorResponse('Tenant not found', 404, null, 'TENANT_NOT_FOUND');
            }

            $profile = RoleProfile::forTenant($tenant->id)->find($profileId);
            
            if (!$profile) {
                return $this->errorResponse('Role profile not found', 404, null, 'PROFILE_NOT_FOUND');
            }

            $roleModels = $profile->getRoleModels();
            
            return $this->successResponse([
                'id' => $profile->id,
                'name' => $profile->name,
                'description' => $profile->description,
                'roles' => $roleModels->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                    ];
                })->toArray(),
                'role_ids' => $profile->roles,
                'is_active' => $profile->is_active,
                'tenant_id' => $profile->tenant_id,
                'created_at' => $profile->created_at?->toISOString(),
                'updated_at' => $profile->updated_at?->toISOString(),
            ], 'Role profile retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['method' => 'show', 'profile_id' => $profileId]);
            return $this->errorResponse('Failed to retrieve role profile', 500, null, 'PROFILE_FETCH_ERROR');
        }
    }

    /**
     * Create a new role profile
     * 
     * POST /api/v1/admin/role-profiles
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenancyService->resolveActiveTenant($user, $request);
            
            if (!$tenant) {
                return $this->errorResponse('Tenant not found', 404, null, 'TENANT_NOT_FOUND');
            }

            $profile = $this->roleProfileService->create($tenant->id, $request->all());

            $roleModels = $profile->getRoleModels();
            
            return $this->successResponse([
                'id' => $profile->id,
                'name' => $profile->name,
                'description' => $profile->description,
                'roles' => $roleModels->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                    ];
                })->toArray(),
                'role_ids' => $profile->roles,
                'is_active' => $profile->is_active,
                'tenant_id' => $profile->tenant_id,
                'created_at' => $profile->created_at?->toISOString(),
                'updated_at' => $profile->updated_at?->toISOString(),
            ], 'Role profile created successfully', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $e->errors(),
                'VALIDATION_FAILED'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422, null, 'INVALID_INPUT');
        } catch (\Exception $e) {
            $this->logError($e, ['method' => 'store']);
            return $this->errorResponse('Failed to create role profile', 500, null, 'PROFILE_CREATE_ERROR');
        }
    }

    /**
     * Update a role profile
     * 
     * PUT /api/v1/admin/role-profiles/{profile}
     */
    public function update(Request $request, string $profileId): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenancyService->resolveActiveTenant($user, $request);
            
            if (!$tenant) {
                return $this->errorResponse('Tenant not found', 404, null, 'TENANT_NOT_FOUND');
            }

            $profile = RoleProfile::forTenant($tenant->id)->find($profileId);
            
            if (!$profile) {
                return $this->errorResponse('Role profile not found', 404, null, 'PROFILE_NOT_FOUND');
            }

            $profile = $this->roleProfileService->update($profile, $request->all());

            $roleModels = $profile->getRoleModels();
            
            return $this->successResponse([
                'id' => $profile->id,
                'name' => $profile->name,
                'description' => $profile->description,
                'roles' => $roleModels->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                    ];
                })->toArray(),
                'role_ids' => $profile->roles,
                'is_active' => $profile->is_active,
                'tenant_id' => $profile->tenant_id,
                'created_at' => $profile->created_at?->toISOString(),
                'updated_at' => $profile->updated_at?->toISOString(),
            ], 'Role profile updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $e->errors(),
                'VALIDATION_FAILED'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422, null, 'INVALID_INPUT');
        } catch (\Exception $e) {
            $this->logError($e, ['method' => 'update', 'profile_id' => $profileId]);
            return $this->errorResponse('Failed to update role profile', 500, null, 'PROFILE_UPDATE_ERROR');
        }
    }

    /**
     * Delete a role profile
     * 
     * DELETE /api/v1/admin/role-profiles/{profile}
     */
    public function destroy(Request $request, string $profileId): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenancyService->resolveActiveTenant($user, $request);
            
            if (!$tenant) {
                return $this->errorResponse('Tenant not found', 404, null, 'TENANT_NOT_FOUND');
            }

            $profile = RoleProfile::forTenant($tenant->id)->find($profileId);
            
            if (!$profile) {
                return $this->errorResponse('Role profile not found', 404, null, 'PROFILE_NOT_FOUND');
            }

            $this->roleProfileService->delete($profile);

            return $this->successResponse(null, 'Role profile deleted successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['method' => 'destroy', 'profile_id' => $profileId]);
            return $this->errorResponse('Failed to delete role profile', 500, null, 'PROFILE_DELETE_ERROR');
        }
    }

    /**
     * Assign a profile to a user
     * 
     * PUT /api/v1/admin/users/{usr}/assign-profile
     * 
     * Round 246: Changed parameter from {user} to {usr} to avoid route-model-binding
     */
    public function assignProfileToUser(Request $request, string $usr): JsonResponse
    {
        try {
            // Check permission for user role management
            $authUser = Auth::user();
            if (!$this->hasPermission($authUser, 'system.users.manage_roles')) {
                return $this->errorResponse(
                    'Insufficient permissions',
                    403,
                    ['details' => 'system.users.manage_roles permission required'],
                    'PERMISSION_DENIED'
                );
            }

            $tenant = $this->tenancyService->resolveActiveTenant($authUser, $request);
            
            if (!$tenant) {
                return $this->errorResponse('Tenant not found', 404, null, 'TENANT_NOT_FOUND');
            }

            // Round 246: Manually resolve user with tenant isolation (avoid route-model-binding)
            $targetUser = User::where('tenant_id', $tenant->id)->find($usr);
            
            if (!$targetUser) {
                return $this->errorResponse('User not found', 404, null, 'USER_NOT_FOUND');
            }

            $request->validate([
                'profile_id' => 'required|string',
            ]);

            $profile = RoleProfile::forTenant($tenant->id)->find($request->input('profile_id'));
            
            if (!$profile) {
                return $this->errorResponse('Role profile not found', 404, null, 'PROFILE_NOT_FOUND');
            }

            $targetUser = $this->roleProfileService->assignProfileToUser($targetUser, $profile);

            return $this->successResponse([
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'roles' => $targetUser->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                    ];
                })->toArray(),
            ], 'Profile assigned to user successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $e->errors(),
                'VALIDATION_FAILED'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422, null, 'INVALID_INPUT');
        } catch (\Exception $e) {
            $this->logError($e, ['method' => 'assignProfileToUser', 'user_id' => $usr]);
            return $this->errorResponse('Failed to assign profile to user', 500, null, 'PROFILE_ASSIGN_ERROR');
        }
    }
}
