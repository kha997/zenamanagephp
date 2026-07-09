<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('crm.view');
    }

    public function view(User $user, Lead $lead): bool
    {
        return $this->belongsToUserTenant($user, $lead)
            && $user->hasPermission('crm.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('crm.manage');
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->belongsToUserTenant($user, $lead)
            && $user->hasPermission('crm.manage');
    }

    public function convert(User $user, Lead $lead): bool
    {
        // Lead → Opportunity là thao tác thường ngày của sale (crm.manage);
        // crm.convert dành riêng cho Opportunity WON → Project.
        return $this->belongsToUserTenant($user, $lead)
            && $user->hasPermission('crm.manage');
    }

    private function belongsToUserTenant(User $user, Lead $lead): bool
    {
        return (string) $user->tenant_id === (string) $lead->tenant_id;
    }
}
