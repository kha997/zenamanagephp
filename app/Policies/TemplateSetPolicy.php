<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\TemplateSet;
use Illuminate\Support\Facades\Gate;

/**
 * TemplateSet Policy
 * 
 * Authorization policy for TemplateSet model.
 * 
 * Rules:
 * - Only super-admin/system roles can manage template sets (create, update, delete, import, publish, export)
 * - Tenant isolation: user tenant_id must match when using tenant-scoped sets
 * - Global templates (tenant_id = null) accessible to all tenants but only manageable by super-admin
 * - Apply ability allowed for tenant users (they can use templates)
 */
class TemplateSetPolicy
{
    /**
     * Determine whether the user can view any template sets.
     */
    public function viewAny(User $user): bool
    {
        // Super-admin can view all
        if ($user->isSuperAdmin() || $user->can('admin.access')) {
            return true;
        }
        
        // Org Admin can view templates (with tenant scope)
        if ($user->can('admin.templates.manage')) {
            return true;
        }

        // Tenant users can view their tenant's templates and global templates
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can view the template set.
     */
    public function view(User $user, TemplateSet $templateSet): bool
    {
        // Super-admin can view all
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Global templates are accessible to all tenants
        if ($templateSet->is_global || $templateSet->tenant_id === null) {
            return true;
        }

        // Tenant users can view their tenant's templates
        return $user->tenant_id === $templateSet->tenant_id;
    }

    /**
     * Determine whether the user can create template sets.
     */
    public function create(User $user): bool
    {
        // Super Admin can create any template sets (including global)
        if ($user->isSuperAdmin() || $user->can('admin.access')) {
            return true;
        }
        
        // Org Admin can create tenant-specific template sets
        if ($user->can('admin.templates.manage')) {
            return $user->tenant_id !== null;
        }
        
        return false;
    }

    /**
     * Determine whether the user can update the template set.
     */
    public function update(User $user, TemplateSet $templateSet): bool
    {
        // Super Admin can update any template set
        if ($user->isSuperAdmin() || $user->can('admin.access')) {
            return true;
        }
        
        // Org Admin can only update tenant-specific templates from their tenant
        if ($user->can('admin.templates.manage')) {
            // Global templates cannot be updated by Org Admin
            if ($templateSet->is_global || $templateSet->tenant_id === null) {
                return false;
            }
            
            // Can only update templates from their own tenant
            return $templateSet->tenant_id === $user->tenant_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the template set.
     */
    public function delete(User $user, TemplateSet $templateSet): bool
    {
        // Super Admin can delete any template set
        if ($user->isSuperAdmin() || $user->can('admin.access')) {
            return true;
        }
        
        // Org Admin can only delete tenant-specific templates from their tenant
        if ($user->can('admin.templates.manage')) {
            // Global templates cannot be deleted by Org Admin
            if ($templateSet->is_global || $templateSet->tenant_id === null) {
                return false;
            }
            
            // Can only delete templates from their own tenant
            return $templateSet->tenant_id === $user->tenant_id;
        }

        return false;
    }

    /**
     * Determine whether the user can import template sets.
     */
    public function import(User $user): bool
    {
        // Only super-admin can import template sets
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can apply the template set to a project.
     */
    public function apply(User $user, TemplateSet $templateSet): bool
    {
        // Super-admin can apply any template
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Tenant users can apply global templates
        if ($templateSet->is_global || $templateSet->tenant_id === null) {
            return $user->tenant_id !== null;
        }

        // Tenant users can apply their tenant's templates
        return $user->tenant_id === $templateSet->tenant_id;
    }

    /**
     * Determine whether the user can publish a new version of the template set.
     */
    public function publish(User $user, TemplateSet $templateSet): bool
    {
        // Only super-admin can publish template sets
        return $this->update($user, $templateSet);
    }

    /**
     * Determine whether the user can export the template set.
     */
    public function export(User $user, TemplateSet $templateSet): bool
    {
        // Super-admin can export any template
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Tenant users can export their tenant's templates and global templates
        if ($templateSet->is_global || $templateSet->tenant_id === null) {
            return $user->tenant_id !== null;
        }

        return $user->tenant_id === $templateSet->tenant_id;
    }
}

