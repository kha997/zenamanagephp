<?php

namespace App\Policies;

use App\Models\ReportSchedule;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReportSchedulePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any report schedules.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Determine whether the user can view the report schedule.
     */
    public function view(User $user, ReportSchedule $reportSchedule): bool
    {
        // Admin can view any schedule
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check tenant isolation
        if ($reportSchedule->tenant_id !== $user->tenant_id) {
            return false;
        }

        // Owner can view their schedules
        if ($reportSchedule->user_id === $user->id) {
            return true;
        }

        // Project managers can view schedules in their tenant
        if ($user->hasRole('project_manager')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create report schedules.
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
     * Determine whether the user can update the report schedule.
     */
    public function update(User $user, ReportSchedule $reportSchedule): bool
    {
        // Admin can update any schedule
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check tenant isolation
        if ($reportSchedule->tenant_id !== $user->tenant_id) {
            return false;
        }

        // Owner can update their schedules
        if ($reportSchedule->user_id === $user->id) {
            return true;
        }

        // Project managers can update schedules in their tenant
        if ($user->hasRole('project_manager')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the report schedule.
     */
    public function delete(User $user, ReportSchedule $reportSchedule): bool
    {
        // Admin can delete any schedule
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check tenant isolation
        if ($reportSchedule->tenant_id !== $user->tenant_id) {
            return false;
        }

        // Owner can delete their schedules
        if ($reportSchedule->user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the report schedule.
     */
    public function restore(User $user, ReportSchedule $reportSchedule): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the report schedule.
     */
    public function forceDelete(User $user, ReportSchedule $reportSchedule): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can execute report schedules.
     */
    public function execute(User $user, ReportSchedule $reportSchedule): bool
    {
        return $this->view($user, $reportSchedule);
    }
}
