<?php declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleProject;
use Database\Seeders\TreasuryRolePermissionSeeder;
use Database\Seeders\ZenaPermissionsSeeder;

/**
 * GAP-063: users whose treasury.* codes come from the real role-default
 * seeder, so tests exercise the shipped grants rather than hand-made ones.
 */
trait TreasuryAccessTestHelpers
{
    protected function seedTreasuryPermissions(): void
    {
        $this->seed(ZenaPermissionsSeeder::class);
    }

    protected function userWithRole(Tenant $tenant, string $roleName): User
    {
        $role = Role::query()->where('name', $roleName)->first()
            ?? Role::factory()->create(['name' => $roleName]);
        $this->seed(TreasuryRolePermissionSeeder::class);

        $user = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user->fresh() ?? $user;
    }

    protected function projectFor(Tenant $tenant): Project
    {
        return Project::factory()->create(['tenant_id' => $tenant->id]);
    }

    protected function addMember(Project $project, User $user): void
    {
        $role = Role::factory()->create(['name' => 'project-member-' . $user->id]);

        UserRoleProject::query()->create([
            'project_id' => (string) $project->id,
            'user_id' => (string) $user->id,
            'role_id' => (string) $role->id,
        ]);
    }
}
