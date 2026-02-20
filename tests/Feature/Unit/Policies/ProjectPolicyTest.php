<?php declare(strict_types=1);

namespace Tests\Feature\Unit\Policies;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\ProjectPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ProjectPolicy $policy;
    private Tenant $tenant;
    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ProjectPolicy();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->project = Project::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_view_and_create_use_project_prefixed_permissions(): void
    {
        $this->grantPermission($this->user, 'project.view');
        $this->grantPermission($this->user, 'project.create');

        $this->assertTrue($this->policy->viewAny($this->user));
        $this->assertTrue($this->policy->view($this->user, $this->project));
        $this->assertTrue($this->policy->create($this->user));
    }

    public function test_legacy_projects_prefix_does_not_authorize_project_policy(): void
    {
        $this->grantPermission($this->user, 'projects.view');
        $this->grantPermission($this->user, 'projects.create');
        $this->grantPermission($this->user, 'projects.update');

        $this->assertFalse($this->policy->viewAny($this->user));
        $this->assertFalse($this->policy->view($this->user, $this->project));
        $this->assertFalse($this->policy->create($this->user));
        $this->assertFalse($this->policy->update($this->user, $this->project));
    }

    public function test_update_requires_project_update_and_same_tenant(): void
    {
        $this->grantPermission($this->user, 'project.update');

        $otherTenantProject = Project::factory()->create();

        $this->assertTrue($this->policy->update($this->user, $this->project));
        $this->assertFalse($this->policy->update($this->user, $otherTenantProject));
    }

    private function grantPermission(User $user, string $code): void
    {
        [$module, $action] = explode('.', $code, 2);

        $permission = Permission::firstOrCreate(
            ['code' => $code],
            [
                'name' => $code,
                'module' => $module,
                'action' => $action,
                'description' => 'Policy test permission ' . $code,
            ]
        );

        if ($permission->name !== $code) {
            $permission->forceFill(['name' => $code])->save();
        }

        $role = Role::firstOrCreate(
            ['name' => 'policy-project-role-' . $code],
            [
                'scope' => Role::SCOPE_SYSTEM,
                'is_active' => true,
            ]
        );

        $role->permissions()->syncWithoutDetaching($permission->id);
        $user->roles()->syncWithoutDetaching($role->id);
    }
}
