<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Project;

/**
 * Admin Project Policy
 * 
 * Handles authorization for admin project operations (read-only portfolio + force actions).
 */
class AdminProjectPolicy
{
    /**
     * Determine if user can view any projects in admin context
     */
    public function viewAny(User $user): bool
    {
        return $user->can('admin.projects.read') || $user->isSuperAdmin();
    }

    /**
     * Determine if user can view a project in admin context
     */
    public function view(User $user, Project $project): bool
    {
        if (!$this->viewAny($user)) {
            return false;
        }
        
        // Super Admin can view all projects
        if ($user->isSuperAdmin() || $user->can('admin.access')) {
            return true;
        }
        
        // Org Admin can only view projects from their tenant
        if ($user->can('admin.access.tenant')) {
            return $project->tenant_id === $user->tenant_id;
        }
        
        return false;
    }

    /**
     * Determine if user can freeze a project
     */
    public function freeze(User $user, Project $project): bool
    {
        if (!$user->can('admin.projects.force_ops')) {
            return false;
        }
        
        // Super Admin can freeze any project
        if ($user->isSuperAdmin() || $user->can('admin.access')) {
            return true;
        }
        
        // Org Admin can only freeze projects from their tenant
        if ($user->can('admin.access.tenant')) {
            return $project->tenant_id === $user->tenant_id;
        }
        
        return false;
    }

    /**
     * Determine if user can archive a project
     */
    public function archive(User $user, Project $project): bool
    {
        return $this->freeze($user, $project); // Same permission as freeze
    }

    /**
     * Determine if user can emergency suspend a project
     */
    public function emergencySuspend(User $user, Project $project): bool
    {
        return $this->freeze($user, $project); // Same permission as freeze
    }
}
