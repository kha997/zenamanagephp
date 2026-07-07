<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\MaterialReceipt;
use App\Models\MaterialReceiptChecklist;
use App\Models\User;

class MaterialReceiptChecklistPolicy
{
    public function create(User $user, MaterialReceipt $receipt): bool
    {
        return $user->tenant_id === (string) $receipt->getAttribute('tenant_id')
            && $user->hasPermission('material-receipt-checklist.create');
    }

    public function view(User $user, MaterialReceiptChecklist $checklist): bool
    {
        return $user->tenant_id === (string) $checklist->getAttribute('tenant_id')
            && $user->hasPermission('material-receipt-checklist.view');
    }
}
