<?php declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectApplyWorkTemplateUiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithPermissions(Tenant $tenant, array $codes): User
    {
        $user = User::factory()->create(['tenant_id' => (string) $tenant->id]);
        $role = Role::factory()->create(['name' => 'Test Role ' . uniqid()]);

        $ids = [];
        foreach ($codes as $code) {
            $permission = Permission::where('code', $code)->first()
                ?? Permission::factory()->create(['code' => $code, 'name' => $code]);
            $ids[] = $permission->id;
        }
        $role->permissions()->sync($ids);
        UserRole::query()->create(['user_id' => (string) $user->id, 'role_id' => (string) $role->id]);

        return $user;
    }

    public function test_apply_template_card_visible_when_user_has_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->makeUserWithPermissions($tenant, ['project.view', 'template.apply']);
        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
        ]);

        $response = $this->actingAs($user)->get("/app/projects/{$project->id}");

        $response->assertOk();
        $response->assertSee('Áp dụng mẫu công việc');
    }

    public function test_apply_template_card_hidden_when_user_lacks_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->makeUserWithPermissions($tenant, ['project.view']);
        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
        ]);

        $response = $this->actingAs($user)->get("/app/projects/{$project->id}");

        $response->assertOk();
        $response->assertDontSee('Áp dụng mẫu công việc');
    }
}
