<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\UserTenant;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * TenantInvitationLifecycleService
 * 
 * Handles the lifecycle of tenant invitations from the invitee perspective:
 * - Preview invitation by token (public)
 * - Accept invitation (requires auth)
 * - Decline invitation (requires auth)
 */
class TenantInvitationLifecycleService
{
    /**
     * Get invitation by token
     * 
     * @param string $token
     * @return TenantInvitation
     * @throws ValidationException
     */
    public function getInvitationForToken(string $token): TenantInvitation
    {
        $invitation = TenantInvitation::where('token', $token)
            ->with('tenant:id,name')
            ->first();

        if (!$invitation) {
            throw ValidationException::withMessages([
                'token' => ['TENANT_INVITE_INVALID_TOKEN']
            ]);
        }

        return $invitation;
    }

    /**
     * Assert that invitation is usable for accept operation
     * 
     * @param TenantInvitation $invitation
     * @return void
     * @throws ValidationException
     */
    public function assertInvitationUsableForAccept(TenantInvitation $invitation): void
    {
        // Check if already accepted
        if ($invitation->status === TenantInvitation::STATUS_ACCEPTED) {
            throw ValidationException::withMessages([
                'invitation' => ['TENANT_INVITE_ALREADY_ACCEPTED']
            ]);
        }

        // Check if revoked
        if ($invitation->status === TenantInvitation::STATUS_REVOKED) {
            throw ValidationException::withMessages([
                'invitation' => ['TENANT_INVITE_ALREADY_REVOKED']
            ]);
        }

        // Check if declined
        if ($invitation->status === TenantInvitation::STATUS_DECLINED) {
            throw ValidationException::withMessages([
                'invitation' => ['TENANT_INVITE_ALREADY_DECLINED']
            ]);
        }

        // Check if expired
        if ($invitation->isExpired()) {
            // Auto-update status if pending but expired
            if ($invitation->status === TenantInvitation::STATUS_PENDING) {
                $invitation->status = TenantInvitation::STATUS_EXPIRED;
                $invitation->save();
            }
            
            throw ValidationException::withMessages([
                'invitation' => ['TENANT_INVITE_EXPIRED']
            ]);
        }

        // Must be pending
        if ($invitation->status !== TenantInvitation::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'invitation' => ['TENANT_INVITE_INVALID_STATE']
            ]);
        }
    }

    /**
     * Assert that invitation is usable for decline operation
     * 
     * @param TenantInvitation $invitation
     * @return void
     * @throws ValidationException
     */
    public function assertInvitationUsableForDecline(TenantInvitation $invitation): void
    {
        // Check if already accepted
        if ($invitation->status === TenantInvitation::STATUS_ACCEPTED) {
            throw ValidationException::withMessages([
                'invitation' => ['TENANT_INVITE_ALREADY_ACCEPTED']
            ]);
        }

        // Check if revoked
        if ($invitation->status === TenantInvitation::STATUS_REVOKED) {
            throw ValidationException::withMessages([
                'invitation' => ['TENANT_INVITE_ALREADY_REVOKED']
            ]);
        }

        // Check if declined
        if ($invitation->status === TenantInvitation::STATUS_DECLINED) {
            throw ValidationException::withMessages([
                'invitation' => ['TENANT_INVITE_ALREADY_DECLINED']
            ]);
        }

        // Check if expired
        if ($invitation->isExpired()) {
            throw ValidationException::withMessages([
                'invitation' => ['TENANT_INVITE_EXPIRED']
            ]);
        }

        // Must be pending
        if ($invitation->status !== TenantInvitation::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'invitation' => ['TENANT_INVITE_INVALID_STATE']
            ]);
        }
    }

    /**
     * Accept invitation by token
     * 
     * @param string $token
     * @param User $user
     * @return TenantInvitation
     * @throws ValidationException
     */
    public function acceptInvitationByToken(string $token, User $user): TenantInvitation
    {
        $invitation = $this->getInvitationForToken($token);
        
        // Assert invitation is usable
        $this->assertInvitationUsableForAccept($invitation);

        // Check email match
        if (Str::lower($user->email) !== Str::lower($invitation->email)) {
            throw ValidationException::withMessages([
                'email' => ['TENANT_INVITE_EMAIL_MISMATCH']
            ]);
        }

        // Check if user is already a member
        $existingMembership = UserTenant::where('tenant_id', $invitation->tenant_id)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->first();

        $alreadyMember = $existingMembership !== null;

        // If not a member, create membership
        if (!$alreadyMember) {
            // Check if user has any other tenants to determine is_default
            $hasOtherTenants = UserTenant::where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->exists();

            UserTenant::create([
                'user_id' => $user->id,
                'tenant_id' => $invitation->tenant_id,
                'role' => $invitation->role,
                'is_default' => !$hasOtherTenants, // Set as default if user has no other tenants
            ]);
        }

        // Update invitation status
        $invitation->status = TenantInvitation::STATUS_ACCEPTED;
        $invitation->accepted_at = now();
        $invitation->save();

        return $invitation;
    }

    /**
     * Decline invitation by token
     * 
     * @param string $token
     * @param User $user
     * @return TenantInvitation
     * @throws ValidationException
     */
    public function declineInvitationByToken(string $token, User $user): TenantInvitation
    {
        $invitation = $this->getInvitationForToken($token);
        
        // Assert invitation is usable
        $this->assertInvitationUsableForDecline($invitation);

        // Update invitation status
        $invitation->status = TenantInvitation::STATUS_DECLINED;
        $invitation->revoked_at = now(); // Reuse revoked_at field for declined_at
        $invitation->save();

        return $invitation;
    }
}

