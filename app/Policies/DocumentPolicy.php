<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Document;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any documents.
     */
    public function viewAny(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    /**
     * Determine whether the user can view the document.
     */
    public function view(User $user, Document $document)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $document->tenant_id) {
            return false;
        }

        // Check role-based access
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    /**
     * Determine whether the user can create documents.
     */
    public function create(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer']);
    }

    /**
     * Determine whether the user can update the document.
     */
    public function update(User $user, Document $document)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $document->tenant_id) {
            return false;
        }

        // Check role-based access
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer']);
    }

    /**
     * Determine whether the user can delete the document.
     */
    public function delete(User $user, Document $document)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $document->tenant_id) {
            return false;
        }

        // Only super_admin and admin can delete
        return $user->hasRole(['super_admin', 'admin']);
    }

    /**
     * Determine whether the user can restore the document.
     */
    public function restore(User $user, Document $document)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $document->tenant_id) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin']);
    }

    /**
     * Determine whether the user can permanently delete the document.
     */
    public function forceDelete(User $user, Document $document)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $document->tenant_id) {
            return false;
        }

        return $user->hasRole(['super_admin']);
    }

    /**
     * Determine whether the user can download the document.
     */
    public function download(User $user, Document $document)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $document->tenant_id) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    /**
     * Determine whether the user can approve the document.
     */
    public function approve(User $user, Document $document)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $document->tenant_id) {
            return false;
        }

        return $user->hasPermission('document.approve');
    }

    /**
     * GAP-033 §6.2: only the document's project's own manager, or a tenant
     * Admin, may set or change the designated approver. Deliberately
     * narrower than update() — general editors/designers may not.
     */
    public function assignApprover(User $user, Document $document)
    {
        if ($user->tenant_id !== $document->tenant_id) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        return $document->project?->pm_id !== null && $user->id === $document->project?->pm_id;
    }
}
