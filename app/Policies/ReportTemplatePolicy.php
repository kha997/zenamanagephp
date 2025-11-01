<?php

namespace App\Policies;

use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReportTemplatePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any report templates.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Determine whether the user can view the report template.
     */
    public function view(User $user, ReportTemplate $reportTemplate): bool
    {
        // Admin can view any template
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check tenant isolation
        if ($reportTemplate->tenant_id !== $user->tenant_id) {
            return false;
        }

        // Owner can view their templates
        if ($reportTemplate->user_id === $user->id) {
            return true;
        }

        // Public templates can be viewed by anyone in the tenant
        if ($reportTemplate->is_public) {
            return true;
        }

        // Project managers can view templates in their tenant
        if ($user->hasRole('project_manager')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create report templates.
     */
    public function create(User $user): bool
    {
        return $user->is_active && (
            $user->hasRole('admin') ||
            $user->hasRole('project_manager') ||
            $user->hasRole('member')
        );
    }

    /**
     * Determine whether the user can update the report template.
     */
    public function update(User $user, ReportTemplate $reportTemplate): bool
    {
        // Admin can update any template
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check tenant isolation
        if ($reportTemplate->tenant_id !== $user->tenant_id) {
            return false;
        }

        // Owner can update their templates
        if ($reportTemplate->user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the report template.
     */
    public function delete(User $user, ReportTemplate $reportTemplate): bool
    {
        // Admin can delete any template
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check tenant isolation
        if ($reportTemplate->tenant_id !== $user->tenant_id) {
            return false;
        }

        // Owner can delete their templates
        if ($reportTemplate->user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the report template.
     */
    public function restore(User $user, ReportTemplate $reportTemplate): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the report template.
     */
    public function forceDelete(User $user, ReportTemplate $reportTemplate): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can use the report template.
     */
    public function use(User $user, ReportTemplate $reportTemplate): bool
    {
        return $this->view($user, $reportTemplate);
    }

    /**
     * Determine whether the user can make template public.
     */
    public function makePublic(User $user, ReportTemplate $reportTemplate): bool
    {
        return $user->hasRole('admin') || (
            $reportTemplate->user_id === $user->id &&
            $user->hasRole('project_manager')
        );
    }
}
