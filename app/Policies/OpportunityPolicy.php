<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;

class OpportunityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('crm.view');
    }

    public function view(User $user, Opportunity $opportunity): bool
    {
        return $this->belongsToUserTenant($user, $opportunity)
            && $user->hasPermission('crm.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('crm.manage');
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        return $this->belongsToUserTenant($user, $opportunity)
            && $user->hasPermission('crm.manage');
    }

    public function convert(User $user, Opportunity $opportunity): bool
    {
        return $this->belongsToUserTenant($user, $opportunity)
            && $user->hasPermission('crm.convert');
    }

    private function belongsToUserTenant(User $user, Opportunity $opportunity): bool
    {
        return (string) $user->tenant_id === (string) $opportunity->tenant_id;
    }
}
