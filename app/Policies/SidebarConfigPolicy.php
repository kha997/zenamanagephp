<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SidebarConfig;
use App\Models\User;
use App\Support\Rbac\RbacProtection;

/**
 * Sidebar config management (api/admin/sidebar-configs/*, behind only the parameterless `rbac` gate — this policy is the
 * real authorization).
 *
 * OWN-2026-024 (O-17h) H4: the former `before()` hook granted every ability to the role NAME `super_admin`, and the read
 * abilities also to the role name / legacy `users.role` value `admin`. Both are removed. The former population ("Super Admin
 * only", i.e. the company Owner) is kept, expressed canonically: the Owner role together with its revocable
 * admin.company.manage grant (RbacProtection::hasOwnerAuthority). Object abilities are additionally bound to the actor's own
 * tenant; a GLOBAL config (tenant_id NULL) affects every tenant and is therefore not managed by a tenant Owner here (who may
 * manage global sidebar configs is a separate Owner decision — see 105 §2).
 */
class SidebarConfigPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->tenantOwner($user);
    }

    public function view(User $user, SidebarConfig $sidebarConfig): bool
    {
        return $this->ownsTenantConfig($user, $sidebarConfig);
    }

    public function create(User $user): bool
    {
        return $this->tenantOwner($user);
    }

    public function update(User $user, ?SidebarConfig $sidebarConfig = null): bool
    {
        // Class-level 'update' (role reset) acts on the actor's own tenant (the controller resolves TenantContext).
        return $sidebarConfig === null ? $this->tenantOwner($user) : $this->ownsTenantConfig($user, $sidebarConfig);
    }

    public function delete(User $user, SidebarConfig $sidebarConfig): bool
    {
        return $this->ownsTenantConfig($user, $sidebarConfig);
    }

    public function restore(User $user, SidebarConfig $sidebarConfig): bool
    {
        return $this->ownsTenantConfig($user, $sidebarConfig);
    }

    public function forceDelete(User $user, SidebarConfig $sidebarConfig): bool
    {
        return $this->ownsTenantConfig($user, $sidebarConfig);
    }

    public function manage(User $user): bool
    {
        return $this->tenantOwner($user);
    }

    public function clone(User $user): bool
    {
        return $this->tenantOwner($user);
    }

    public function reset(User $user): bool
    {
        return $this->tenantOwner($user);
    }

    /**
     * Class-level abilities act on the actor's own tenant (the controller resolves TenantContext, which falls back to it); a
     * tenant-less account would make the controller fall back to a request-supplied tenant_id, so it is refused here (Gate 8).
     */
    private function tenantOwner(User $user): bool
    {
        return $user->getAttribute('tenant_id') !== null && RbacProtection::hasOwnerAuthority($user);
    }

    private function ownsTenantConfig(User $user, SidebarConfig $sidebarConfig): bool
    {
        $configTenant = $sidebarConfig->getAttribute('tenant_id');

        return $this->tenantOwner($user)
            && $configTenant !== null
            && (string) $configTenant === (string) $user->getAttribute('tenant_id');
    }
}
