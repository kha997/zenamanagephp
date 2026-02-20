<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Project;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('project.view');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->tenant_id === $project->tenant_id && 
               $user->hasPermission('project.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('project.create');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->tenant_id === $project->tenant_id && 
               $user->hasPermission('project.update');
    }
}
