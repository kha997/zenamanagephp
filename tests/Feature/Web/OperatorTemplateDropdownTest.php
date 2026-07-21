<?php declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\DeliverableTemplate;
use App\Models\DeliverableTemplateVersion;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatorTemplateDropdownTest extends TestCase
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

    public function test_project_show_template_dropdown_is_details_based_and_alpine_free(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->makeUserWithPermissions($tenant, ['project.view']);
        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
        ]);
        // $projectTemplates trong ProjectController::show() lọc
        // `latestPublishedVersion !== null` — nên fixture PHẢI có version
        // với published_at, không chỉ template. (Pattern lấy từ
        // tests/Feature/Zena/DocumentTemplateRenderTest.php.)
        $template = DeliverableTemplate::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'context' => 'project',
            'name' => 'Biên bản bàn giao',
            'status' => 'published',
        ]);
        DeliverableTemplateVersion::create([
            'tenant_id' => (string) $tenant->id,
            'deliverable_template_id' => $template->id,
            'version' => '1.0.0',
            'semver' => '1.0.0',
            'storage_path' => 'deliverable-templates/' . $tenant->id . '/dropdown-test/render.html',
            'checksum_sha256' => hash('sha256', '<h1>x</h1>'),
            'mime' => 'text/html',
            'size' => 10,
            'placeholders_spec_json' => ['schema_version' => '1.0.0', 'placeholders' => []],
            'published_at' => now(),
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $response = $this->actingAs($user)->get("/app/projects/{$project->id}");

        $response->assertOk();
        $response->assertSee('data-template-dropdown', false);
        $response->assertSee('Biên bản bàn giao');
        $response->assertDontSee('x-data', false);
    }
}
