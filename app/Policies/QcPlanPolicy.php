<?php

namespace App\Policies;

use App\Models\User;
use App\Models\QcPlan;
use Illuminate\Auth\Access\HandlesAuthorization;

class QcPlanPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    public function view(User $user, QcPlan $qcPlan)
    {
        if ($user->tenant_id !== $qcPlan->tenant_id) return false;
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    public function create(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer']);
    }

    public function update(User $user, QcPlan $qcPlan)
    {
        if ($user->tenant_id !== $qcPlan->tenant_id) return false;
        return $user->id === $qcPlan->created_by || $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    public function delete(User $user, QcPlan $qcPlan)
    {
        if ($user->tenant_id !== $qcPlan->tenant_id) return false;
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function approve(User $user, QcPlan $qcPlan)
    {
        if ($user->tenant_id !== $qcPlan->tenant_id) return false;
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    public function execute(User $user, QcPlan $qcPlan)
    {
        if ($user->tenant_id !== $qcPlan->tenant_id) return false;
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }
}
