<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('crm.view');
    }

    public function view(User $user, Account $account): bool
    {
        return $this->belongsToUserTenant($user, $account)
            && $user->hasPermission('crm.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('crm.manage');
    }

    public function update(User $user, Account $account): bool
    {
        return $this->belongsToUserTenant($user, $account)
            && $user->hasPermission('crm.manage');
    }

    private function belongsToUserTenant(User $user, Account $account): bool
    {
        return (string) $user->tenant_id === (string) $account->tenant_id;
    }
}
