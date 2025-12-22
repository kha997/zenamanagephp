<?php declare(strict_types=1);

namespace App\Services;

use App\Models\RoleProfile;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Concerns\RecordsAuditLogs;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * RoleProfileService
 * 
 * Round 244: Role Access Profiles
 * 
 * Handles CRUD operations for role profiles and assignment to users.
 * Profiles are templates that contain multiple roles for quick user onboarding.
 */
class RoleProfileService
{
    use RecordsAuditLogs;

    private AuditLogService $auditLogService;
    private TenancyService $tenancyService;
    private NotificationService $notificationService;

    public function __construct(
        AuditLogService $auditLogService,
        TenancyService $tenancyService,
        NotificationService $notificationService
    ) {
        $this->auditLogService = $auditLogService;
        $this->tenancyService = $tenancyService;
        $this->notificationService = $notificationService;
    }

    /**
     * List all profiles for a tenant
     * 
     * @param string $tenantId Tenant ID
     * @return Collection<RoleProfile>
     */
    public function listProfiles(string $tenantId): Collection
    {
        return RoleProfile::forTenant($tenantId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a new role profile
     * 
     * @param string $tenantId Tenant ID
     * @param array $data Profile data (name, description, roles, is_active)
     * @return RoleProfile
     * @throws \Illuminate\Validation\ValidationException
     */
    public function create(string $tenantId, array $data): RoleProfile
    {
        // Validate input
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255|unique:role_profiles,name,NULL,id,tenant_id,' . $tenantId,
            'description' => 'nullable|string|max:1000',
            'roles' => 'required|array|min:1',
            'roles.*' => 'required|string', // Role IDs or names
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        // Validate that all roles exist
        $this->validateRoles($tenantId, $data['roles']);

        return DB::transaction(function () use ($tenantId, $data) {
            $profile = RoleProfile::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'roles' => $data['roles'],
                'is_active' => $data['is_active'] ?? true,
                'tenant_id' => $tenantId,
            ]);

            // Round 244: Audit log
            $this->auditLogService->record(
                tenantId: $tenantId,
                userId: (string) Auth::id(),
                action: 'profile.created',
                entityType: 'RoleProfile',
                entityId: $profile->id,
                before: null,
                after: [
                    'profile_id' => $profile->id,
                    'profile_name' => $profile->name,
                    'roles' => $profile->roles,
                ],
                ipAddress: request()?->ip(),
                userAgent: request()?->userAgent()
            );

            return $profile;
        });
    }

    /**
     * Update a role profile
     * 
     * @param RoleProfile $profile Profile to update
     * @param array $data Update data
     * @return RoleProfile
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(RoleProfile $profile, array $data): RoleProfile
    {
        // Validate input
        $validator = Validator::make($data, [
            'name' => 'sometimes|required|string|max:255|unique:role_profiles,name,' . $profile->id . ',id,tenant_id,' . $profile->tenant_id,
            'description' => 'nullable|string|max:1000',
            'roles' => 'sometimes|required|array|min:1',
            'roles.*' => 'required|string',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        // Validate roles if provided
        if (isset($data['roles'])) {
            $this->validateRoles($profile->tenant_id, $data['roles']);
        }

        return DB::transaction(function () use ($profile, $data) {
            // Capture before state
            $before = [
                'profile_id' => $profile->id,
                'profile_name' => $profile->name,
                'roles' => $profile->roles,
                'is_active' => $profile->is_active,
            ];

            $profile->fill($data);
            $profile->save();

            // Round 244: Audit log
            $this->auditLogService->record(
                tenantId: $profile->tenant_id,
                userId: (string) Auth::id(),
                action: 'profile.updated',
                entityType: 'RoleProfile',
                entityId: $profile->id,
                before: $before,
                after: [
                    'profile_id' => $profile->id,
                    'profile_name' => $profile->name,
                    'roles' => $profile->roles,
                    'is_active' => $profile->is_active,
                ],
                ipAddress: request()?->ip(),
                userAgent: request()?->userAgent()
            );

            return $profile;
        });
    }

    /**
     * Delete a role profile
     * 
     * @param RoleProfile $profile Profile to delete
     * @return bool
     */
    public function delete(RoleProfile $profile): bool
    {
        return DB::transaction(function () use ($profile) {
            $before = [
                'profile_id' => $profile->id,
                'profile_name' => $profile->name,
                'roles' => $profile->roles,
            ];

            $deleted = $profile->delete();

            // Round 244: Audit log
            $this->auditLogService->record(
                tenantId: $profile->tenant_id,
                userId: (string) Auth::id(),
                action: 'profile.deleted',
                entityType: 'RoleProfile',
                entityId: $profile->id,
                before: $before,
                after: null,
                ipAddress: request()?->ip(),
                userAgent: request()?->userAgent()
            );

            return $deleted;
        });
    }

    /**
     * Assign a profile to a user
     * 
     * This method adds the profile's roles to the user's existing roles.
     * It does NOT remove existing roles - profile only adds roles.
     * 
     * @param User $user User to assign profile to
     * @param RoleProfile $profile Profile to assign
     * @return User
     */
    public function assignProfileToUser(User $user, RoleProfile $profile): User
    {
        // Verify tenant match
        if ((string) $user->tenant_id !== (string) $profile->tenant_id) {
            throw new \InvalidArgumentException('User and profile must belong to the same tenant');
        }

        return DB::transaction(function () use ($user, $profile) {
            // Get role models from profile
            $profileRoles = $profile->getRoleModels();
            
            if ($profileRoles->isEmpty()) {
                throw new \InvalidArgumentException('Profile has no valid roles');
            }

            // Get current user roles
            $user->load('roles');
            $currentRoleIds = $user->roles->pluck('id')->toArray();
            
            // Get profile role IDs
            $profileRoleIds = $profileRoles->pluck('id')->toArray();
            
            // Merge roles (avoid duplicates)
            $newRoleIds = array_unique(array_merge($currentRoleIds, $profileRoleIds));
            
            // Capture before state
            $beforeRoles = $user->roles->pluck('name')->toArray();
            
            // Sync roles (this will add new ones, keep existing ones)
            $user->roles()->sync($newRoleIds);
            
            // Reload user with roles
            $user->refresh();
            $user->load('roles');
            
            // Capture after state
            $afterRoles = $user->roles->pluck('name')->toArray();
            
            // Round 244: Audit log
            $this->auditLogService->record(
                tenantId: $user->tenant_id,
                userId: (string) Auth::id(),
                action: 'user.profile_assigned',
                entityType: 'User',
                entityId: $user->id,
                before: ['roles' => $beforeRoles],
                after: [
                    'roles' => $afterRoles,
                    'profile_id' => $profile->id,
                    'profile_name' => $profile->name,
                ],
                ipAddress: request()?->ip(),
                userAgent: request()?->userAgent()
            );

            // Round 252: Notification for profile assignment
            try {
                $this->notificationService->notifyUser(
                    userId: (string) $user->id,
                    module: 'rbac',
                    type: 'user.profile_assigned',
                    title: 'Quyền truy cập của bạn đã được cập nhật',
                    message: sprintf("Bạn vừa được gán profile quyền \"%s\".", $profile->name),
                    entityType: 'role_profile',
                    entityId: $profile->id,
                    metadata: [
                        'profile_name' => $profile->name,
                    ],
                    tenantId: (string) $user->tenant_id
                );
            } catch (\Exception $e) {
                \Log::warning('Failed to create notification for profile assignment', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id,
                    'profile_id' => $profile->id,
                ]);
            }

            return $user;
        });
    }

    /**
     * Validate that all role identifiers exist in the roles table
     * 
     * @param string $tenantId Tenant ID
     * @param array $roleIdentifiers Array of role IDs or names
     * @throws \InvalidArgumentException
     */
    private function validateRoles(string $tenantId, array $roleIdentifiers): void
    {
        $validRoles = [];
        
        foreach ($roleIdentifiers as $identifier) {
            // Try to find by ID first
            $role = Role::find($identifier);
            
            // If not found by ID, try by name
            if (!$role) {
                $role = Role::where('name', $identifier)
                    ->where('tenant_id', $tenantId)
                    ->first();
            }
            
            if (!$role) {
                throw new \InvalidArgumentException("Role not found: {$identifier}");
            }
            
            // Verify role belongs to tenant (if tenant_id is set on role)
            if (isset($role->tenant_id) && $role->tenant_id !== $tenantId) {
                throw new \InvalidArgumentException("Role '{$identifier}' does not belong to tenant");
            }
            
            $validRoles[] = $role;
        }
        
        if (empty($validRoles)) {
            throw new \InvalidArgumentException('At least one valid role is required');
        }
    }
}
