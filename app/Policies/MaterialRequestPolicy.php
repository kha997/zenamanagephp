<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\MaterialRequest;
use App\Models\User;

class MaterialRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('material.read');
    }

    public function view(User $user, MaterialRequest $materialRequest): bool
    {
        return $this->belongsToUserTenant($user, $materialRequest)
            && $user->hasPermission('material.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('material.request');
    }

    public function update(User $user, MaterialRequest $materialRequest): bool
    {
        return $this->belongsToUserTenant($user, $materialRequest)
            && $user->hasPermission('material.request');
    }

    public function submit(User $user, MaterialRequest $materialRequest): bool
    {
        return $this->belongsToUserTenant($user, $materialRequest)
            && $user->hasPermission('material.request');
    }

    public function approve(User $user, MaterialRequest $materialRequest): bool
    {
        return $this->belongsToUserTenant($user, $materialRequest)
            && $user->hasPermission('material.approve');
    }

    public function reject(User $user, MaterialRequest $materialRequest): bool
    {
        return $this->belongsToUserTenant($user, $materialRequest)
            && $user->hasPermission('material.approve');
    }

    public function fulfill(User $user, MaterialRequest $materialRequest): bool
    {
        return $this->belongsToUserTenant($user, $materialRequest)
            && $user->hasPermission('material.receive');
    }

    private function belongsToUserTenant(User $user, MaterialRequest $materialRequest): bool
    {
        return $user->tenant_id === (string) data_get($materialRequest->project, 'tenant_id');
    }
}
