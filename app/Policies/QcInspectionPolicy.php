<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\QcInspection;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * QcInspectionPolicy
 * 
 * Authorization policy for QC Inspection model operations.
 * Ensures multi-tenant isolation and proper access control.
 */
class QcInspectionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any QC inspections.
     */
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can view the QC inspection.
     */
    public function view(User $user, QcInspection $qcInspection): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $qcInspection->tenant_id) {
            return false;
        }

        // Inspector can view
        if ($qcInspection->inspector_id === $user->id) {
            return true;
        }

        // Project members can view
        if ($qcInspection->project_id) {
            return $user->can('view', $qcInspection->project);
        }

        return false;
    }

    /**
     * Determine whether the user can create QC inspections.
     */
    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can update the QC inspection.
     */
    public function update(User $user, QcInspection $qcInspection): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $qcInspection->tenant_id) {
            return false;
        }

        // Inspector can update
        if ($qcInspection->inspector_id === $user->id) {
            return true;
        }

        // Project managers can update
        if ($qcInspection->project_id) {
            return $user->can('update', $qcInspection->project);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the QC inspection.
     */
    public function delete(User $user, QcInspection $qcInspection): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $qcInspection->tenant_id) {
            return false;
        }

        // Inspector can delete
        if ($qcInspection->inspector_id === $user->id) {
            return true;
        }

        // Project managers can delete
        if ($qcInspection->project_id) {
            return $user->can('delete', $qcInspection->project);
        }

        return false;
    }

    /**
     * Determine whether the user can restore the QC inspection.
     */
    public function restore(User $user, QcInspection $qcInspection): bool
    {
        return $this->update($user, $qcInspection);
    }

    /**
     * Determine whether the user can permanently delete the QC inspection.
     */
    public function forceDelete(User $user, QcInspection $qcInspection): bool
    {
        return $this->delete($user, $qcInspection);
    }

    /**
     * Determine whether the user can approve the QC inspection.
     */
    public function approve(User $user, QcInspection $qcInspection): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $qcInspection->tenant_id) {
            return false;
        }

        // Project managers can approve
        if ($qcInspection->project_id) {
            return $user->can('update', $qcInspection->project);
        }

        return false;
    }

    /**
     * Determine whether the user can reject the QC inspection.
     */
    public function reject(User $user, QcInspection $qcInspection): bool
    {
        return $this->approve($user, $qcInspection);
    }

    /**
     * Determine whether the user can schedule the QC inspection.
     */
    public function schedule(User $user, QcInspection $qcInspection): bool
    {
        return $this->create($user);
    }

    /**
     * Determine whether the user can complete the QC inspection.
     */
    public function complete(User $user, QcInspection $qcInspection): bool
    {
        return $this->update($user, $qcInspection);
    }
}