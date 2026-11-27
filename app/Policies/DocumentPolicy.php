<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Support\Rbac\RbacProtection;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * OWN-2026-024 (O-17h) H4 (Owner: "H3_ACCEPTED / H4_LOCAL_IMPLEMENTATION_APPROVED", 2026-11-25).
 *
 * Every ability is decided by canonical, revocable grants (User::hasPermission: user_roles -> role_permissions, durable
 * revocations excluded) plus the tenant boundary. No role NAME and no legacy `users.role` value grants anything here.
 *
 * Abilities that were deliberately NARROWER than their route gate keep their population, expressed canonically:
 *  - delete / restore / forceDelete were "super_admin (and admin)" only -> document.delete AND canonical Owner authority
 *    (the Owner role together with its revocable admin.company.manage grant; RbacProtection::hasOwnerAuthority). A role
 *    that merely holds document.delete (for example a seeded "System Admin") does not gain deletion.
 *  - assignApprover (GAP-033 §6.2) was "the project's own manager, or a tenant Admin" -> project PM, or canonical Owner
 *    authority.
 */
class DocumentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('document.view');
    }

    public function view(User $user, Document $document)
    {
        return $this->sameTenant($user, $document) && $user->hasPermission('document.view');
    }

    public function create(User $user)
    {
        return $user->hasPermission('document.create');
    }

    public function update(User $user, Document $document)
    {
        return $this->sameTenant($user, $document) && $user->hasPermission('document.update');
    }

    public function delete(User $user, Document $document)
    {
        return $this->mayRemove($user, $document);
    }

    public function restore(User $user, Document $document)
    {
        return $this->mayRemove($user, $document);
    }

    public function forceDelete(User $user, Document $document)
    {
        return $this->mayRemove($user, $document);
    }

    public function download(User $user, Document $document)
    {
        // Same canonical permission the download route gate requires.
        return $this->sameTenant($user, $document) && $user->hasPermission('document.view');
    }

    public function approve(User $user, Document $document)
    {
        return $this->sameTenant($user, $document) && $user->hasPermission('document.approve');
    }

    /**
     * GAP-033 §6.2: only the document's project's own manager, or the tenant Owner (canonical Owner authority), may set or
     * change the designated approver. Deliberately narrower than update() — general editors/designers may not.
     */
    public function assignApprover(User $user, Document $document)
    {
        if (!$this->sameTenant($user, $document)) {
            return false;
        }

        if (RbacProtection::hasOwnerAuthority($user)) {
            return true;
        }

        return $document->project?->pm_id !== null && (string) $user->id === (string) $document->project?->pm_id;
    }

    private function mayRemove(User $user, Document $document): bool
    {
        return $this->sameTenant($user, $document)
            && $user->hasPermission('document.delete')
            && RbacProtection::hasOwnerAuthority($user);
    }

    private function sameTenant(User $user, Document $document): bool
    {
        $tenantId = $user->getAttribute('tenant_id');

        return $tenantId !== null && (string) $tenantId === (string) $document->getAttribute('tenant_id');
    }
}
