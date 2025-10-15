<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Document;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * DocumentPolicy
 * 
 * Authorization policy for Document model operations.
 * Ensures multi-tenant isolation and proper access control.
 */
class DocumentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any documents.
     */
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can view the document.
     */
    public function view(User $user, Document $document): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $document->tenant_id) {
            return false;
        }

        // Owner can always view
        if ($document->uploaded_by === $user->id) {
            return true;
        }

        // All tenant users can view documents
        return true;
    }

    /**
     * Determine whether the user can create documents.
     */
    public function create(User $user): bool
    {
        // Multi-tenant check
        if ($user->tenant_id === null) {
            return false;
        }

        // Role-based permissions
        return $user->hasAnyRole(['super_admin', 'admin', 'project_manager', 'designer', 'site_engineer', 'qc_engineer', 'procurement']);
    }

    /**
     * Determine whether the user can update the document.
     */
    public function update(User $user, Document $document): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $document->tenant_id) {
            return false;
        }

        // Owner can always update
        if ($document->uploaded_by === $user->id) {
            return true;
        }

        // Role-based permissions
        return $user->hasAnyRole(['super_admin', 'admin', 'project_manager', 'designer', 'site_engineer', 'qc_engineer']);
    }

    /**
     * Determine whether the user can delete the document.
     */
    public function delete(User $user, Document $document): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $document->tenant_id) {
            return false;
        }

        // Owner can always delete
        if ($document->uploaded_by === $user->id) {
            return true;
        }

        // Role-based permissions (only admins can delete others' documents)
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    /**
     * Determine whether the user can restore the document.
     */
    public function restore(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }

    /**
     * Determine whether the user can permanently delete the document.
     */
    public function forceDelete(User $user, Document $document): bool
    {
        return $this->delete($user, $document);
    }

    /**
     * Determine whether the user can download the document.
     */
    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    /**
     * Determine whether the user can share the document.
     */
    public function share(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }

    /**
     * Determine whether the user can approve the document.
     */
    public function approve(User $user, Document $document): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $document->tenant_id) {
            return false;
        }

        // Role-based permissions (only management roles can approve)
        return $user->hasAnyRole(['super_admin', 'admin', 'project_manager']);
    }
}