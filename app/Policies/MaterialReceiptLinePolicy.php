<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\MaterialReceipt;
use App\Models\MaterialReceiptLine;
use App\Models\User;

class MaterialReceiptLinePolicy
{
    public function viewAny(User $user, MaterialReceipt $receipt): bool
    {
        return $user->tenant_id === (string) $receipt->getAttribute('tenant_id')
            && $user->hasPermission('material-receipt-line.view');
    }

    public function create(User $user, MaterialReceipt $receipt): bool
    {
        return $user->tenant_id === (string) $receipt->getAttribute('tenant_id')
            && $user->hasPermission('material-receipt-line.create');
    }

    public function view(User $user, MaterialReceiptLine $line): bool
    {
        return $user->tenant_id === (string) $line->getAttribute('tenant_id')
            && $user->hasPermission('material-receipt-line.view');
    }

    public function update(User $user, MaterialReceiptLine $line, MaterialReceipt $receipt): bool
    {
        return $user->tenant_id === (string) $receipt->getAttribute('tenant_id')
            && $user->hasPermission('material-receipt-line.create');
    }
}
