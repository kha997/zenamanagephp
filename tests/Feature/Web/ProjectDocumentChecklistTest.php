<?php declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Document;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkInstance;
use App\Models\WorkInstanceStep;
use App\Models\WorkTemplateStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class ProjectDocumentChecklistTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);

        $templateStep = WorkTemplateStep::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Issue Drawings',
            'required_document_types' => ['drawing', 'contract'],
        ]);

        $instance = WorkInstance::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'scope_type' => 'project',
        ]);

        WorkInstanceStep::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'work_instance_id' => (string) $instance->id,
            'work_template_step_id' => (string) $templateStep->id,
            'name' => 'Issue Drawings',
        ]);

        Document::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'document_type' => 'drawing',
        ]);
    }

    public function test_shows_checklist_card_with_missing_types_for_authorized_user(): void
    {
        $user = $this->createTenantUser($this->tenant, [], ['admin'], ['work.view']);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $response = $this->actingAs($user)
            ->get(route('app.projects.show', $this->project->id), $headers);

        $response->assertOk()
            ->assertSee('Checklist tài liệu')
            ->assertSee('Issue Drawings')
            ->assertSee('contract');
    }

    public function test_hides_checklist_card_without_work_view_permission(): void
    {
        $user = $this->createTenantUser($this->tenant, [], ['staff'], []);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $response = $this->actingAs($user)
            ->get(route('app.projects.show', $this->project->id), $headers);

        $response->assertOk()
            ->assertDontSee('Checklist tài liệu');
    }
}
