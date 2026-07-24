<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\Submittal;
use App\Models\User;

class SubmittalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('submittal.view');
    }

    public function view(User $user, Submittal $submittal): bool
    {
        return $this->sameTenant($user, $submittal) && $user->hasPermission('submittal.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('submittal.create');
    }

    public function update(User $user, Submittal $submittal): bool
    {
        return $this->sameTenant($user, $submittal) && $user->hasPermission('submittal.edit');
    }

    public function submit(User $user, Submittal $submittal): bool
    {
        return $this->sameTenant($user, $submittal) && $user->hasPermission('submittal.submit');
    }

    public function startRevision(User $user, Submittal $submittal): bool
    {
        return $this->sameTenant($user, $submittal) && $user->hasPermission('submittal.submit');
    }

    public function approve(User $user, Submittal $submittal): bool
    {
        return $this->sameTenant($user, $submittal) && $user->hasPermission('submittal.approve');
    }

    public function reject(User $user, Submittal $submittal): bool
    {
        return $this->sameTenant($user, $submittal) && $user->hasPermission('submittal.reject');
    }

    public function delete(User $user, Submittal $submittal): bool
    {
        return $this->sameTenant($user, $submittal) && $user->hasPermission('submittal.delete');
    }

    private function sameTenant(User $user, Submittal $submittal): bool
    {
        return (string) $user->tenant_id === (string) $submittal->tenant_id;
    }
}
