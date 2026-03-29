<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\Boq;
use App\Models\User;

class BoqPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('boq.view');
    }

    public function view(User $user, Boq $boq): bool
    {
        return $user->tenant_id === (string) $boq->getAttribute('tenant_id')
            && $user->hasPermission('boq.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('boq.create');
    }

    public function update(User $user, Boq $boq): bool
    {
        return $user->tenant_id === (string) $boq->getAttribute('tenant_id')
            && $user->hasPermission('boq.update');
    }

    public function delete(User $user, Boq $boq): bool
    {
        return $user->tenant_id === (string) $boq->getAttribute('tenant_id')
            && $user->hasPermission('boq.delete');
    }
}
