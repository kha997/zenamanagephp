<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Organization;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrganizationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any organizations.
     */
    public function viewAny(User $user)
    {
        return $user->hasRole(['super_admin']);
    }

    /**
     * Determine whether the user can view the organization.
     */
    public function view(User $user, Organization $organization)
    {
        // Super admin can view all organizations
        if ($user->hasRole(['super_admin'])) {
            return true;
        }

        // Users can view their own organization
        return $user->organization_id === $organization->id;
    }

    /**
     * Determine whether the user can create organizations.
     */
    public function create(User $user)
    {
        return $user->hasRole(['super_admin']);
    }

    /**
     * Determine whether the user can update the organization.
     */
    public function update(User $user, Organization $organization)
    {
        // Super admin can update all organizations
        if ($user->hasRole(['super_admin'])) {
            return true;
        }

        // Organization admins can update their organization
        return $user->organization_id === $organization->id && 
               $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can delete the organization.
     */
    public function delete(User $user, Organization $organization)
    {
        return $user->hasRole(['super_admin']);
    }

    /**
     * Determine whether the user can manage organization settings.
     */
    public function manageSettings(User $user, Organization $organization)
    {
        // Super admin can manage all organization settings
        if ($user->hasRole(['super_admin'])) {
            return true;
        }

        // Organization admins can manage their organization settings
        return $user->organization_id === $organization->id && 
               $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can invite users to the organization.
     */
    public function inviteUsers(User $user, Organization $organization)
    {
        // Super admin can invite to any organization
        if ($user->hasRole(['super_admin'])) {
            return true;
        }

        // Organization admins can invite to their organization
        return $user->organization_id === $organization->id && 
               $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can manage organization billing.
     */
    public function manageBilling(User $user, Organization $organization)
    {
        // Super admin can manage all organization billing
        if ($user->hasRole(['super_admin'])) {
            return true;
        }

        // Organization admins can manage their organization billing
        return $user->organization_id === $organization->id && 
               $user->hasRole(['admin']);
    }
}
