<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('crm.view');
    }

    public function view(User $user, Quote $quote): bool
    {
        return $this->belongsToUserTenant($user, $quote)
            && $user->hasPermission('crm.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('crm.manage');
    }

    public function update(User $user, Quote $quote): bool
    {
        return $this->belongsToUserTenant($user, $quote)
            && $user->hasPermission('crm.manage');
    }

    private function belongsToUserTenant(User $user, Quote $quote): bool
    {
        return (string) $user->tenant_id === (string) $quote->tenant_id;
    }
}
