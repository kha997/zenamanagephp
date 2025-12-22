<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\UserTenant;
use App\Jobs\SendTenantInvitationMail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * TenantMembersService
 * 
 * Encapsulates business logic for tenant membership and invitations management.
 * Handles tenant isolation, permission checks, and business rules.
 */
class TenantMembersService
{
    /**
     * List members for a tenant
     * 
     * @param string $tenantId
     * @param array $filters Optional filters (search, pagination)
     * @return LengthAwarePaginator|Collection
     */
    public function listMembersForTenant(string $tenantId, array $filters = []): LengthAwarePaginator|Collection
    {
        $query = User::whereHas('tenants', function ($q) use ($tenantId) {
            $q->where('tenants.id', $tenantId)
              ->whereNull('user_tenants.deleted_at');
        });

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apply role filter
        if (!empty($filters['role'])) {
            $query->whereHas('tenants', function ($q) use ($tenantId, $filters) {
                $q->where('tenants.id', $tenantId)
                  ->where('user_tenants.role', $filters['role']);
            });
        }

        // Get members with pivot data
        $members = $query->get()->map(function ($user) use ($tenantId) {
            $pivot = DB::table('user_tenants')
                ->where('user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->first();
            
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $pivot->role ?? null,
                'is_default' => (bool) ($pivot->is_default ?? false),
                'joined_at' => $pivot->created_at ?? null,
                'last_login_at' => $user->last_login_at,
            ];
        });

        // Apply pagination if requested
        if (isset($filters['per_page'])) {
            $perPage = min((int) $filters['per_page'], 100);
            $page = (int) ($filters['page'] ?? 1);
            $total = $members->count();
            $items = $members->forPage($page, $perPage)->values();
            
            return new LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        }

        // Convert Support Collection to Eloquent Collection to match return type
        return new \Illuminate\Database\Eloquent\Collection($members->all());
    }

    /**
     * Update member role
     * 
     * @param string $tenantId
     * @param int|string $memberUserId
     * @param string $newRole
     * @param User $actingUser
     * @return User
     * @throws ValidationException
     */
    public function updateMemberRole(
        string $tenantId,
        int|string $memberUserId,
        string $newRole,
        User $actingUser
    ): User {
        $this->assertCanModifyMembers($tenantId, $actingUser);

        // Validate role
        $validRoles = array_keys(config('permissions.tenant_roles', []));
        if (!in_array($newRole, $validRoles)) {
            throw ValidationException::withMessages([
                'role' => ['Invalid tenant role. Must be one of: ' . implode(', ', $validRoles)]
            ]);
        }

        // Get current membership
        $pivot = UserTenant::where('tenant_id', $tenantId)
            ->where('user_id', $memberUserId)
            ->whereNull('deleted_at')
            ->first();

        if (!$pivot) {
            throw ValidationException::withMessages([
                'member' => ['Member not found in this tenant']
            ]);
        }

        // Check last owner protection
        if ($pivot->role === 'owner' && $newRole !== 'owner') {
            $this->assertNotLastOwner($tenantId, $memberUserId);
        }

        // Update role
        $pivot->role = $newRole;
        $pivot->save();

        return User::findOrFail($memberUserId);
    }

    /**
     * Remove member from tenant
     * 
     * @param string $tenantId
     * @param int|string $memberUserId
     * @param User $actingUser
     * @return void
     * @throws ValidationException
     */
    public function removeMember(
        string $tenantId,
        int|string $memberUserId,
        User $actingUser
    ): void {
        $this->assertCanModifyMembers($tenantId, $actingUser);

        // Get current membership
        $pivot = UserTenant::where('tenant_id', $tenantId)
            ->where('user_id', $memberUserId)
            ->whereNull('deleted_at')
            ->first();

        if (!$pivot) {
            throw ValidationException::withMessages([
                'member' => ['Member not found in this tenant']
            ]);
        }

        // Check last owner protection
        if ($pivot->role === 'owner') {
            $this->assertNotLastOwner($tenantId, $memberUserId);
        }

        // Soft delete the membership
        $pivot->delete();
    }

    /**
     * Promote member to owner (with optional self-demotion for transfer)
     * 
     * Allows owners to:
     * - Promote any member to owner (multi-owner support)
     * - Transfer ownership: promote target and demote self to admin
     * 
     * @param string $tenantId
     * @param string $targetUserId
     * @param User $actingUser
     * @param bool $demoteSelf Whether to demote acting user to admin (transfer ownership)
     * @return array Array with 'target_member' and 'acting_member' data
     * @throws ValidationException
     */
    public function promoteMemberToOwner(
        string $tenantId,
        string $targetUserId,
        User $actingUser,
        bool $demoteSelf = false
    ): array {
        // Step 1: Validate acting user membership and role
        $actingPivot = UserTenant::where('tenant_id', $tenantId)
            ->where('user_id', $actingUser->id)
            ->whereNull('deleted_at')
            ->first();

        if (!$actingPivot) {
            throw ValidationException::withMessages([
                'member' => ['Member not found in this tenant']
            ]);
        }

        // Only owners can change ownership roles
        if ($actingPivot->role !== 'owner') {
            throw ValidationException::withMessages([
                'permission' => ['Only owners can change ownership roles']
            ]);
        }

        // Step 2: Validate target member
        $targetPivot = UserTenant::where('tenant_id', $tenantId)
            ->where('user_id', $targetUserId)
            ->whereNull('deleted_at')
            ->first();

        if (!$targetPivot) {
            throw ValidationException::withMessages([
                'member' => ['Member not found in this tenant']
            ]);
        }

        // Cannot promote someone who is already an owner
        if ($targetPivot->role === 'owner') {
            throw ValidationException::withMessages([
                'owner' => ['Member is already an owner']
            ]);
        }

        // Cannot make owner or transfer to self (acting user is already owner)
        if ($targetUserId == $actingUser->id) {
            throw ValidationException::withMessages([
                'member' => ['You are already an owner']
            ]);
        }

        // Step 3: Promote / Transfer
        if ($demoteSelf) {
            // Transfer ownership: promote target, demote acting user to admin
            $targetPivot->role = 'owner';
            $targetPivot->save();

            $actingPivot->role = 'admin';
            $actingPivot->save();
        } else {
            // Add owner: promote target, keep acting user as owner
            $targetPivot->role = 'owner';
            $targetPivot->save();
        }

        // Step 4: Build return data (similar to listMembersForTenant format)
        $targetUser = User::findOrFail($targetUserId);
        $actingUserRefreshed = User::findOrFail($actingUser->id);

        // Get updated pivots for return data
        $targetPivotRefreshed = UserTenant::where('tenant_id', $tenantId)
            ->where('user_id', $targetUserId)
            ->whereNull('deleted_at')
            ->first();

        $actingPivotRefreshed = UserTenant::where('tenant_id', $tenantId)
            ->where('user_id', $actingUser->id)
            ->whereNull('deleted_at')
            ->first();

        return [
            'target_member' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'role' => $targetPivotRefreshed->role ?? null,
                'is_default' => (bool) ($targetPivotRefreshed->is_default ?? false),
                'joined_at' => $targetPivotRefreshed->created_at ?? null,
                'last_login_at' => $targetUser->last_login_at,
            ],
            'acting_member' => [
                'id' => $actingUserRefreshed->id,
                'name' => $actingUserRefreshed->name,
                'email' => $actingUserRefreshed->email,
                'role' => $actingPivotRefreshed->role ?? null,
                'is_default' => (bool) ($actingPivotRefreshed->is_default ?? false),
                'joined_at' => $actingPivotRefreshed->created_at ?? null,
                'last_login_at' => $actingUserRefreshed->last_login_at,
            ],
        ];
    }

    /**
     * Self-service: User leaves tenant themselves
     * 
     * Allows any member (regardless of role) to leave the tenant,
     * except if they are the last owner.
     * 
     * @param string $tenantId
     * @param User $actingUser
     * @return void
     * @throws ValidationException
     */
    public function selfLeaveTenant(string $tenantId, User $actingUser): void
    {
        // Get current membership
        $pivot = UserTenant::where('tenant_id', $tenantId)
            ->where('user_id', $actingUser->id)
            ->whereNull('deleted_at')
            ->first();

        if (!$pivot) {
            throw ValidationException::withMessages([
                'member' => ['Member not found in this tenant']
            ]);
        }

        // Check last owner protection
        if ($pivot->role === 'owner') {
            $this->assertNotLastOwner($tenantId, $actingUser->id);
        }

        // Handle default tenant reassignment if needed
        $wasDefault = (bool) $pivot->is_default;
        
        // Soft delete the membership
        $pivot->delete();

        // If this was the default tenant, reassign default to another tenant
        if ($wasDefault) {
            $otherTenantPivot = UserTenant::where('user_id', $actingUser->id)
                ->where('tenant_id', '!=', $tenantId)
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'asc')
                ->first();

            if ($otherTenantPivot) {
                // Set this tenant as the new default
                $otherTenantPivot->is_default = true;
                $otherTenantPivot->save();
            }
            // If no other tenant exists, user has no default tenant (acceptable state)
        }
    }

    /**
     * List invitations for a tenant
     * 
     * @param string $tenantId
     * @return Collection
     */
    public function listInvitationsForTenant(string $tenantId): Collection
    {
        return TenantInvitation::where('tenant_id', $tenantId)
            ->with('inviter:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                    'status' => $invitation->status,
                    'invited_by' => $invitation->inviter ? [
                        'id' => $invitation->inviter->id,
                        'name' => $invitation->inviter->name,
                    ] : null,
                    'created_at' => $invitation->created_at,
                    'expires_at' => $invitation->expires_at,
                ];
            });
    }

    /**
     * Create invitation
     * 
     * @param string $tenantId
     * @param string $email
     * @param string $role
     * @param User $actingUser
     * @return TenantInvitation
     * @throws ValidationException
     */
    public function createInvitation(
        string $tenantId,
        string $email,
        string $role,
        User $actingUser
    ): TenantInvitation {
        $this->assertCanModifyMembers($tenantId, $actingUser);

        // Normalize email
        $email = strtolower(trim($email));

        // Validate role
        $validRoles = array_keys(config('permissions.tenant_roles', []));
        if (!in_array($role, $validRoles)) {
            throw ValidationException::withMessages([
                'role' => ['TENANT_INVALID_ROLE']
            ]);
        }

        // Check if user is already a member
        $existingUser = User::where('email', $email)->first();
        if ($existingUser) {
            $existingMember = UserTenant::where('tenant_id', $tenantId)
                ->where('user_id', $existingUser->id)
                ->whereNull('deleted_at')
                ->exists();
            
            if ($existingMember) {
                throw ValidationException::withMessages([
                    'email' => ['TENANT_INVITE_ALREADY_MEMBER']
                ]);
            }
        }


        // Check for duplicate pending invitation
        $existingInvitation = TenantInvitation::where('tenant_id', $tenantId)
            ->where('email', $email)
            ->where('status', TenantInvitation::STATUS_PENDING)
            ->exists();

        if ($existingInvitation) {
            throw ValidationException::withMessages([
                'email' => ['TENANT_INVITE_ALREADY_PENDING']
            ]);
        }

        // Generate token
        $token = Str::ulid()->toBase32();

        // Create invitation
        $invitation = TenantInvitation::create([
            'tenant_id' => $tenantId,
            'email' => $email,
            'role' => $role,
            'token' => $token,
            'status' => TenantInvitation::STATUS_PENDING,
            'invited_by' => $actingUser->id,
            'expires_at' => now()->addDays(7), // TODO: Make configurable
        ]);

        // Dispatch email job
        SendTenantInvitationMail::dispatch($invitation->id);

        return $invitation;
    }

    /**
     * Revoke invitation
     * 
     * @param string $tenantId
     * @param string $invitationId
     * @param User $actingUser
     * @return void
     * @throws ValidationException
     */
    public function revokeInvitation(
        string $tenantId,
        string $invitationId,
        User $actingUser
    ): void {
        $this->assertCanModifyMembers($tenantId, $actingUser);

        $invitation = TenantInvitation::where('tenant_id', $tenantId)
            ->where('id', $invitationId)
            ->first();

        if (!$invitation) {
            throw ValidationException::withMessages([
                'invitation' => ['TENANT_INVITE_INVALID_TOKEN']
            ]);
        }

        if ($invitation->status !== TenantInvitation::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'invitation' => ['Only pending invitations can be revoked']
            ]);
        }

        $invitation->status = TenantInvitation::STATUS_REVOKED;
        $invitation->revoked_at = now();
        $invitation->save();
    }

    /**
     * Resend invitation email
     * 
     * @param string $tenantId
     * @param string $invitationId
     * @param User $actingUser
     * @return TenantInvitation
     * @throws ValidationException
     */
    public function resendInvitation(
        string $tenantId,
        string $invitationId,
        User $actingUser
    ): TenantInvitation {
        $this->assertCanModifyMembers($tenantId, $actingUser);

        $invitation = TenantInvitation::where('tenant_id', $tenantId)
            ->where('id', $invitationId)
            ->first();

        if (!$invitation) {
            throw ValidationException::withMessages([
                'invitation' => ['TENANT_INVITE_INVALID_TOKEN']
            ]);
        }

        // Check status
        if ($invitation->status === TenantInvitation::STATUS_ACCEPTED) {
            throw ValidationException::withMessages([
                'invitation' => ['TENANT_INVITE_ALREADY_ACCEPTED']
            ]);
        }

        if ($invitation->status === TenantInvitation::STATUS_DECLINED) {
            throw ValidationException::withMessages([
                'invitation' => ['TENANT_INVITE_ALREADY_DECLINED']
            ]);
        }

        if ($invitation->status === TenantInvitation::STATUS_REVOKED) {
            throw ValidationException::withMessages([
                'invitation' => ['TENANT_INVITE_ALREADY_REVOKED']
            ]);
        }

        // Check if expired
        if ($invitation->isExpired()) {
            // Mark as expired if not already
            if ($invitation->status !== TenantInvitation::STATUS_EXPIRED) {
                $invitation->status = TenantInvitation::STATUS_EXPIRED;
                $invitation->save();
            }
            throw ValidationException::withMessages([
                'invitation' => ['TENANT_INVITE_EXPIRED']
            ]);
        }

        // Only allow resending pending invitations
        if ($invitation->status !== TenantInvitation::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'invitation' => ['TENANT_INVITE_INVALID_TOKEN']
            ]);
        }

        // Dispatch email job
        SendTenantInvitationMail::dispatch($invitation->id);

        return $invitation;
    }

    /**
     * Assert that user can modify members
     * 
     * @param string $tenantId
     * @param User $actingUser
     * @return void
     * @throws ValidationException
     */
    protected function assertCanModifyMembers(string $tenantId, User $actingUser): void
    {
        $tenancyService = app(TenancyService::class);
        $permissions = $tenancyService->getCurrentTenantPermissions($actingUser);

        if (!in_array('tenant.manage_members', $permissions)) {
            throw ValidationException::withMessages([
                'permission' => ['You do not have permission to manage members']
            ]);
        }

        // Verify user is a member of this tenant
        $isMember = $actingUser->tenants()
            ->where('tenants.id', $tenantId)
            ->whereNull('user_tenants.deleted_at')
            ->exists();

        if (!$isMember) {
            throw ValidationException::withMessages([
                'tenant' => ['You are not a member of this tenant']
            ]);
        }
    }

    /**
     * Assert that removing/changing this member would not leave tenant with zero owners
     * 
     * @param string $tenantId
     * @param int|string $memberUserId
     * @return void
     * @throws ValidationException
     */
    protected function assertNotLastOwner(string $tenantId, int|string $memberUserId): void
    {
        $ownerCount = UserTenant::where('tenant_id', $tenantId)
            ->where('role', 'owner')
            ->whereNull('deleted_at')
            ->count();

        if ($ownerCount <= 1) {
            throw ValidationException::withMessages([
                'member' => ['Cannot remove or demote the last owner of the tenant']
            ]);
        }
    }
}

