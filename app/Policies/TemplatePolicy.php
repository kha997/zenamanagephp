<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Template;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * TemplatePolicy
 * 
 * Authorization policy for Template model operations.
 * Ensures multi-tenant isolation and proper access control.
 */
class TemplatePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any templates.
     */
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can view the template.
     */
    public function view(User $user, Template $template): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $template->tenant_id) {
            return false;
        }

        // Public templates can be viewed by anyone in the tenant
        if ($template->is_public) {
            return true;
        }

        // Creator can view
        if ($template->created_by === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create templates.
     */
    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can update the template.
     */
    public function update(User $user, Template $template): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $template->tenant_id) {
            return false;
        }

        // Creator can update
        if ($template->created_by === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the template.
     */
    public function delete(User $user, Template $template): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $template->tenant_id) {
            return false;
        }

        // Creator can delete
        if ($template->created_by === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the template.
     */
    public function restore(User $user, Template $template): bool
    {
        return $this->update($user, $template);
    }

    /**
     * Determine whether the user can permanently delete the template.
     */
    public function forceDelete(User $user, Template $template): bool
    {
        return $this->delete($user, $template);
    }

    /**
     * Determine whether the user can duplicate the template.
     */
    public function duplicate(User $user, Template $template): bool
    {
        return $this->view($user, $template);
    }

    /**
     * Determine whether the user can apply the template to a project.
     */
    public function applyToProject(User $user, Template $template): bool
    {
        return $this->view($user, $template);
    }

    /**
     * Determine whether the user can publish the template.
     */
    public function publish(User $user, Template $template): bool
    {
        return $this->update($user, $template);
    }

    /**
     * Determine whether the user can archive the template.
     */
    public function archive(User $user, Template $template): bool
    {
        return $this->update($user, $template);
    }

    /**
     * Determine whether the user can share the template.
     */
    public function share(User $user, Template $template): bool
    {
        return $this->update($user, $template);
    }

    /**
     * Determine whether the user can export the template.
     */
    public function export(User $user, Template $template): bool
    {
        return $this->view($user, $template);
    }

    /**
     * Determine whether the user can import templates.
     */
    public function import(User $user): bool
    {
        return $user->tenant_id !== null;
    }
}