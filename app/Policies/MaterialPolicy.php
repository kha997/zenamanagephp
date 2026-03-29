<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\Material;
use App\Models\User;

class MaterialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('material.view');
    }

    public function view(User $user, Material $material): bool
    {
        return $user->tenant_id === (string) $material->getAttribute('tenant_id')
            && $user->hasPermission('material.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('material.create');
    }

    public function update(User $user, Material $material): bool
    {
        return $user->tenant_id === (string) $material->getAttribute('tenant_id')
            && $user->hasPermission('material.update');
    }

    public function delete(User $user, Material $material): bool
    {
        return $user->tenant_id === (string) $material->getAttribute('tenant_id')
            && $user->hasPermission('material.delete');
    }
}
