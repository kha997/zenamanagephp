<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\MaterialReceipt;
use App\Models\User;

class MaterialReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('material-receipt.view');
    }

    public function view(User $user, MaterialReceipt $receipt): bool
    {
        return $user->tenant_id === (string) $receipt->getAttribute('tenant_id')
            && $user->hasPermission('material-receipt.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('material-receipt.create');
    }

    public function update(User $user, MaterialReceipt $receipt): bool
    {
        return $user->tenant_id === (string) $receipt->getAttribute('tenant_id')
            && $user->hasPermission('material-receipt.create');
    }
}
