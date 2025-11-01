<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\QcPlan;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * QcPlanPolicy
 * 
 * Authorization policy for QC Plan model operations.
 * Ensures multi-tenant isolation and proper access control.
 */
class QcPlanPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any QC plans.
     */
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can view the QC plan.
     */
    public function view(User $user, QcPlan $qcPlan): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $qcPlan->tenant_id) {
            return false;
        }

        // Creator can view
        if ($qcPlan->created_by === $user->id) {
            return true;
        }

        // Project members can view
        if ($qcPlan->project_id) {
            return $user->can('view', $qcPlan->project);
        }

        return false;
    }

    /**
     * Determine whether the user can create QC plans.
     */
    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can update the QC plan.
     */
    public function update(User $user, QcPlan $qcPlan): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $qcPlan->tenant_id) {
            return false;
        }

        // Creator can update
        if ($qcPlan->created_by === $user->id) {
            return true;
        }

        // Project managers can update
        if ($qcPlan->project_id) {
            return $user->can('update', $qcPlan->project);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the QC plan.
     */
    public function delete(User $user, QcPlan $qcPlan): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $qcPlan->tenant_id) {
            return false;
        }

        // Creator can delete
        if ($qcPlan->created_by === $user->id) {
            return true;
        }

        // Project managers can delete
        if ($qcPlan->project_id) {
            return $user->can('delete', $qcPlan->project);
        }

        return false;
    }

    /**
     * Determine whether the user can restore the QC plan.
     */
    public function restore(User $user, QcPlan $qcPlan): bool
    {
        return $this->update($user, $qcPlan);
    }

    /**
     * Determine whether the user can permanently delete the QC plan.
     */
    public function forceDelete(User $user, QcPlan $qcPlan): bool
    {
        return $this->delete($user, $qcPlan);
    }

    /**
     * Determine whether the user can approve the QC plan.
     */
    public function approve(User $user, QcPlan $qcPlan): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $qcPlan->tenant_id) {
            return false;
        }

        // Project managers can approve
        if ($qcPlan->project_id) {
            return $user->can('update', $qcPlan->project);
        }

        return false;
    }

    /**
     * Determine whether the user can execute the QC plan.
     */
    public function execute(User $user, QcPlan $qcPlan): bool
    {
        return $this->view($user, $qcPlan);
    }

    /**
     * Determine whether the user can generate QC reports.
     */
    public function generateReport(User $user, QcPlan $qcPlan): bool
    {
        return $this->view($user, $qcPlan);
    }
}