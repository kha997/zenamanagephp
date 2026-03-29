<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;

class VendorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('vendor.view');
    }

    public function view(User $user, Vendor $vendor): bool
    {
        return $user->tenant_id === (string) $vendor->getAttribute('tenant_id')
            && $user->hasPermission('vendor.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('vendor.create');
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $user->tenant_id === (string) $vendor->getAttribute('tenant_id')
            && $user->hasPermission('vendor.update');
    }

    public function delete(User $user, Vendor $vendor): bool
    {
        return $user->tenant_id === (string) $vendor->getAttribute('tenant_id')
            && $user->hasPermission('vendor.delete');
    }
}
