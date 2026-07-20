<?php declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkInstance;
use App\Models\WorkTemplate;
use App\Models\WorkTemplateChecklistItem;
use App\Models\WorkTemplatePhase;
use App\Models\WorkTemplateRequiredDocument;
use App\Models\WorkTemplateTask;
use App\Models\WorkTemplateTaskAssignment;
use App\Models\WorkTemplateTrigger;
use App\Models\WorkTemplateVersion;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ZenaPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Http\Middleware\VerifyCsrfToken;
use Tests\Feature\Api\Concerns\InteractsWithWorkTemplateV2;
use Tests\TestCase;

class ProjectWorkTemplateApplyTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithWorkTemplateV2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->setUpWorkTemplateV2Routes();

        // Seed roles + permissions so RBAC middleware and makeProjectManager() work.
        $this->seed(RoleSeeder::class);
        $this->seed(ZenaPermissionsSeeder::class);
    }

    private function makeProjectManager(Tenant $tenant, array $permissions): User
    {
        $user = User::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'is_active' => true,
        ]);

        $role = Role::factory()->create(['name' => 'Test PM Role ' . uniqid()]);
        $permissionModels = \App\Models\Permission::whereIn('code', $permissions)->get();
        $role->permissions()->sync($permissionModels->pluck('id'));

        \App\Models\UserRole::query()->create([
            'user_id' => (string) $user->id,
            'role_id' => (string) $role->id,
        ]);

        return $user;
    }

    private function publishedTemplate(Tenant $tenant, User $user, string $code): array
    {
        [$template, $version] = $this->seedV2Template($tenant, $user, $code);
        $version->update([
            'published_at' => now(),
            'is_immutable' => true,
            'published_by' => (string) $user->id,
        ]);

        return [$template, $version];
    }

    public function test_templates_list_only_returns_published_templates_for_current_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $user = $this->makeProjectManager($tenant, ['template.view', 'template.apply']);

        [$published] = $this->publishedTemplate($tenant, $user, 'WT-PUB-1');

        // Template draft-only (chưa publish) — không được xuất hiện.
        WorkTemplate::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'code' => 'WT-DRAFT-ONLY',
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        // Template đã publish nhưng thuộc tenant khác — không được xuất hiện.
        $otherUser = User::factory()->create(['tenant_id' => (string) $otherTenant->id]);
        $this->publishedTemplate($otherTenant, $otherUser, 'WT-OTHER-TENANT');

        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
            'start_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)->getJson("/app/projects/{$project->id}/work-templates");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('WT-PUB-1', $data[0]['code']);
    }

    public function test_preview_returns_summary_without_writing_database(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->makeProjectManager($tenant, ['template.view', 'template.apply']);
        [$template] = $this->publishedTemplate($tenant, $user, 'WT-PREVIEW-1');

        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
            'start_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)->postJson(
            "/app/projects/{$project->id}/work-templates/preview",
            ['work_template_id' => (string) $template->id]
        );

        $response->assertOk()
            ->assertJsonPath('data.dry_run', true)
            ->assertJsonPath('data.duplicate', false)
            ->assertJsonPath('data.summary.phases', 1)
            ->assertJsonPath('data.summary.tasks', 1)
            ->assertJsonPath('data.summary.checklists', 1)
            ->assertJsonPath('data.summary.docs', 1);

        $this->assertSame(0, WorkInstance::query()->where('project_id', (string) $project->id)->count());
        $this->assertSame(0, Task::query()->where('project_id', (string) $project->id)->count());
    }

    public function test_apply_creates_real_task_and_second_apply_is_duplicate(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->makeProjectManager($tenant, ['template.view', 'template.apply']);
        [$template] = $this->publishedTemplate($tenant, $user, 'WT-APPLY-1');

        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
            'start_date' => now()->toDateString(),
        ]);

        $firstApply = $this->actingAs($user)->postJson(
            "/app/projects/{$project->id}/work-templates/apply",
            ['work_template_id' => (string) $template->id]
        );

        $firstApply->assertStatus(201)
            ->assertJsonPath('data.duplicate', false);

        $this->assertSame(1, Task::query()->where('project_id', (string) $project->id)->count());
        $this->assertSame(1, WorkInstance::query()->where('project_id', (string) $project->id)->count());

        $secondApply = $this->actingAs($user)->postJson(
            "/app/projects/{$project->id}/work-templates/apply",
            ['work_template_id' => (string) $template->id]
        );

        $secondApply->assertOk()
            ->assertJsonPath('data.duplicate', true);

        // Không tạo trùng.
        $this->assertSame(1, Task::query()->where('project_id', (string) $project->id)->count());
        $this->assertSame(1, WorkInstance::query()->where('project_id', (string) $project->id)->count());
    }

    public function test_user_without_template_apply_permission_gets_403_on_preview_and_apply(): void
    {
        $tenant = Tenant::factory()->create();
        $viewOnlyUser = $this->makeProjectManager($tenant, ['template.view']);
        $adminUser = User::factory()->create(['tenant_id' => (string) $tenant->id]);
        [$template] = $this->publishedTemplate($tenant, $adminUser, 'WT-NOPERM-1');

        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $viewOnlyUser->id,
            'pm_id' => (string) $viewOnlyUser->id,
            'start_date' => now()->toDateString(),
        ]);

        $this->actingAs($viewOnlyUser)->postJson(
            "/app/projects/{$project->id}/work-templates/preview",
            ['work_template_id' => (string) $template->id]
        )->assertStatus(403);

        $this->actingAs($viewOnlyUser)->postJson(
            "/app/projects/{$project->id}/work-templates/apply",
            ['work_template_id' => (string) $template->id]
        )->assertStatus(403);
    }

    public function test_user_without_template_view_permission_gets_403_on_list(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->makeProjectManager($tenant, []);
        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
            'start_date' => now()->toDateString(),
        ]);

        $this->actingAs($user)->getJson("/app/projects/{$project->id}/work-templates")
            ->assertStatus(403);
    }
}
