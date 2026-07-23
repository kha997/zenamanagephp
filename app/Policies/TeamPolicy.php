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
        return $user->hasPermission('team.view');
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

        // Check if user has view permission or is a team member
        return $user->hasPermission('team.view') ||
               $team->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can create teams.
     */
    public function create(User $user)
    {
        return $user->hasPermission('team.create');
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

        // Check if user has update permission or is team leader
        return $user->hasPermission('team.update') ||
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

        return $user->hasPermission('team.delete');
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

        // Check if user has member-management permission or is team leader
        return $user->hasPermission('team.member.add') ||
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

        // Check if user has member-management permission or is team leader
        return $user->hasPermission('team.member.remove') ||
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

        // Only permission holders and team leaders can assign roles
        return $user->hasPermission('team.member.update-role') ||
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
