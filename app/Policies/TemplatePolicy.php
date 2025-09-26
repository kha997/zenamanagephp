<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Template;
use Illuminate\Auth\Access\HandlesAuthorization;

class TemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer']);
    }

    public function view(User $user, Template $template)
    {
        if ($user->tenant_id !== $template->tenant_id) return false;
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer']);
    }

    public function create(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer']);
    }

    public function update(User $user, Template $template)
    {
        if ($user->tenant_id !== $template->tenant_id) return false;
        return $user->id === $template->created_by || $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    public function delete(User $user, Template $template)
    {
        if ($user->tenant_id !== $template->tenant_id) return false;
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function use(User $user, Template $template)
    {
        if ($user->tenant_id !== $template->tenant_id) return false;
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    public function publish(User $user, Template $template)
    {
        if ($user->tenant_id !== $template->tenant_id) return false;
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }
}
