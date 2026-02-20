<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;

class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('contract.view');
    }

    public function view(User $user, Contract $contract): bool
    {
        return $user->tenant_id === (string) $contract->getAttribute('tenant_id')
            && $user->hasPermission('contract.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('contract.create');
    }

    public function update(User $user, Contract $contract): bool
    {
        return $user->tenant_id === (string) $contract->getAttribute('tenant_id')
            && $user->hasPermission('contract.update');
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $user->tenant_id === (string) $contract->getAttribute('tenant_id')
            && $user->hasPermission('contract.delete');
    }
}
