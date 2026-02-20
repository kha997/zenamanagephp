<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\ContractPayment;
use App\Models\User;

class ContractPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('contract.payment.view');
    }

    public function view(User $user, ContractPayment $payment): bool
    {
        return $user->tenant_id === (string) $payment->getAttribute('tenant_id')
            && $user->hasPermission('contract.payment.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('contract.payment.create');
    }

    public function update(User $user, ContractPayment $payment): bool
    {
        return $user->tenant_id === (string) $payment->getAttribute('tenant_id')
            && $user->hasPermission('contract.payment.update');
    }

    public function delete(User $user, ContractPayment $payment): bool
    {
        return $user->tenant_id === (string) $payment->getAttribute('tenant_id')
            && $user->hasPermission('contract.payment.delete');
    }
}
