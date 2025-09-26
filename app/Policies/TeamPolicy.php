<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Team;
use Illuminate\Auth\Access\HandlesAuthorization;

class TeamPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any teams.
     */
    public function viewAny(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can view the team.
     */
    public function view(User $user, Team $team)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $team->tenant_id) {
            return false;
        }

        // Check if user is team member or has management role
        return $user->hasRole(['super_admin', 'admin', 'pm']) || 
               $team->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can create teams.
     */
    public function create(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can update the team.
     */
    public function update(User $user, Team $team)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $team->tenant_id) {
            return false;
        }

        // Check if user is team leader or has management role
        return $user->hasRole(['super_admin', 'admin', 'pm']) || 
               $team->members()->where('user_id', $user->id)->where('role', 'leader')->exists();
    }

    /**
     * Determine whether the user can delete the team.
     */
    public function delete(User $user, Team $team)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $team->tenant_id) {
            return false;
        }

        // Only super_admin and admin can delete teams
        return $user->hasRole(['super_admin', 'admin']);
    }

    /**
     * Determine whether the user can invite members to the team.
     */
    public function invite(User $user, Team $team)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $team->tenant_id) {
            return false;
        }

        // Check if user is team leader or has management role
        return $user->hasRole(['super_admin', 'admin', 'pm']) || 
               $team->members()->where('user_id', $user->id)->where('role', 'leader')->exists();
    }

    /**
     * Determine whether the user can remove members from the team.
     */
    public function removeMember(User $user, Team $team)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $team->tenant_id) {
            return false;
        }

        // Check if user is team leader or has management role
        return $user->hasRole(['super_admin', 'admin', 'pm']) || 
               $team->members()->where('user_id', $user->id)->where('role', 'leader')->exists();
    }

    /**
     * Determine whether the user can assign roles in the team.
     */
    public function assignRole(User $user, Team $team)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $team->tenant_id) {
            return false;
        }

        // Only team leaders and management can assign roles
        return $user->hasRole(['super_admin', 'admin', 'pm']) || 
               $team->members()->where('user_id', $user->id)->where('role', 'leader')->exists();
    }

    /**
     * Determine whether the user can leave the team.
     */
    public function leave(User $user, Team $team)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $team->tenant_id) {
            return false;
        }

        // Check if user is team member
        return $team->members()->where('user_id', $user->id)->exists();
    }
}
