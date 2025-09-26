<?php

namespace App\Policies;

use App\Models\User;
use App\Models\QcInspection;
use Illuminate\Auth\Access\HandlesAuthorization;

class QcInspectionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    public function view(User $user, QcInspection $qcInspection)
    {
        if ($user->tenant_id !== $qcInspection->tenant_id) return false;
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    public function create(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    public function update(User $user, QcInspection $qcInspection)
    {
        if ($user->tenant_id !== $qcInspection->tenant_id) return false;
        return $user->id === $qcInspection->inspector_id || $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    public function delete(User $user, QcInspection $qcInspection)
    {
        if ($user->tenant_id !== $qcInspection->tenant_id) return false;
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function complete(User $user, QcInspection $qcInspection)
    {
        if ($user->tenant_id !== $qcInspection->tenant_id) return false;
        return $user->id === $qcInspection->inspector_id || $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    public function approve(User $user, QcInspection $qcInspection)
    {
        if ($user->tenant_id !== $qcInspection->tenant_id) return false;
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }
}
