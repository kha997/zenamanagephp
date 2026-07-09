<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\DesignItem;
use App\Models\User;

class DesignItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('design-item.view');
    }

    public function view(User $user, DesignItem $designItem): bool
    {
        return $this->belongsToUserTenant($user, $designItem)
            && $user->hasPermission('design-item.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('design-item.manage');
    }

    public function update(User $user, DesignItem $designItem): bool
    {
        return $this->belongsToUserTenant($user, $designItem)
            && $user->hasPermission('design-item.manage');
    }

    private function belongsToUserTenant(User $user, DesignItem $designItem): bool
    {
        return (string) $user->tenant_id === (string) $designItem->tenant_id;
    }
}
