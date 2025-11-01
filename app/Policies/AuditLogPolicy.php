<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AuditLogPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any audit logs.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the audit log.
     */
    public function view(User $user, AuditLog $auditLog): bool
    {
        // Admin can view any audit log
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check tenant isolation
        if ($auditLog->tenant_id !== $user->tenant_id) {
            return false;
        }

        // Project managers can view audit logs for their tenant
        if ($user->hasRole('project_manager')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create audit logs.
     */
    public function create(User $user): bool
    {
        // Audit logs are typically created by the system, not users
        return false;
    }

    /**
     * Determine whether the user can update the audit log.
     */
    public function update(User $user, AuditLog $auditLog): bool
    {
        // Audit logs should not be modified
        return false;
    }

    /**
     * Determine whether the user can delete the audit log.
     */
    public function delete(User $user, AuditLog $auditLog): bool
    {
        // Only admin can delete audit logs
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the audit log.
     */
    public function restore(User $user, AuditLog $auditLog): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the audit log.
     */
    public function forceDelete(User $user, AuditLog $auditLog): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can export audit logs.
     */
    public function export(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('project_manager');
    }
}
