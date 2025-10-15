<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Project;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, Project $project): bool
    {
        return $user->tenant_id === $project->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, Project $project): bool
    {
        return $user->tenant_id === $project->tenant_id;
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->tenant_id === $project->tenant_id;
    }
}
