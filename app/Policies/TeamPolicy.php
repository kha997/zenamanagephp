<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Team;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * TeamPolicy
 * 
 * Authorization policy for Team model operations.
 * Ensures multi-tenant isolation and proper access control.
 */
class TeamPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any teams.
     */
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can view the team.
     */
    public function view(User $user, Team $team): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $team->tenant_id) {
            return false;
        }

        // Users with proper roles can view
        return $user->hasAnyRole(['super_admin', 'admin', 'project_manager', 'team_lead']);
    }

    /**
     * Determine whether the user can create teams.
     */
    public function create(User $user): bool
    {
        // Multi-tenant check
        if ($user->tenant_id === null) {
            return false;
        }

        // Users with proper roles can create
        return $user->hasAnyRole(['super_admin', 'admin', 'project_manager']);
    }

    /**
     * Determine whether the user can update the team.
     */
    public function update(User $user, Team $team): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $team->tenant_id) {
            return false;
        }

        // Team leader can update
        if ($team->leader_id === $user->id) {
            return true;
        }

        // Owner can update
        if ($team->created_by === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the team.
     */
    public function delete(User $user, Team $team): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $team->tenant_id) {
            return false;
        }

        // Team leader can delete
        if ($team->team_lead_id === $user->id) {
            return true;
        }

        // Owner can delete
        if ($team->created_by === $user->id) {
            return true;
        }

        // Users with admin roles can delete
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    /**
     * Determine whether the user can restore the team.
     */
    public function restore(User $user, Team $team): bool
    {
        return $this->update($user, $team);
    }

    /**
     * Determine whether the user can permanently delete the team.
     */
    public function forceDelete(User $user, Team $team): bool
    {
        return $this->delete($user, $team);
    }

    /**
     * Determine whether the user can add members to the team.
     */
    public function addMember(User $user, Team $team): bool
    {
        return $this->update($user, $team);
    }

    /**
     * Determine whether the user can remove members from the team.
     */
    public function removeMember(User $user, Team $team): bool
    {
        return $this->update($user, $team);
    }

    /**
     * Determine whether the user can assign projects to the team.
     */
    public function assignProject(User $user, Team $team): bool
    {
        return $this->update($user, $team);
    }

    /**
     * Determine whether the user can invite members to the team.
     */
    public function invite(User $user, Team $team): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $team->tenant_id) {
            return false;
        }

        // Users with proper roles can invite
        return $user->hasAnyRole(['super_admin', 'admin', 'project_manager', 'team_lead']);
    }
}