<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\DesignItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class ProjectDesignSectionTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;
    private Project $project;
    private DesignItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser($this->tenant, [], ['admin'], ['project.view', 'design-item.view', 'task.view']);
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);

        $this->item = DesignItem::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'name' => 'Concept mặt đứng chính',
            'review_status' => DesignItem::STATUS_SENT_TO_CLIENT,
            'assigned_to' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);
        $this->item->forceFill([
            'revision_count' => 2,
            'blocked_at' => now(),
            'blocker_note' => 'Chờ khách duyệt concept',
            'blocked_by' => (string) $this->user->id,
        ])->save();

        Task::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'name' => 'Dựng phối cảnh sảnh',
            'title' => 'Dựng phối cảnh sảnh',
            'status' => 'in_progress',
            'progress_percent' => 40,
            'assignee_id' => (string) $this->user->id,
        ]);
    }

    public function test_project_page_shows_design_section_with_badges_and_blockers(): void
    {
        $response = $this->actingAs($this->user)->get(
            route('app.projects.show', $this->project->id),
            ['X-Tenant-ID' => (string) $this->tenant->id]
        );

        $response->assertOk()
            ->assertSee('Thiết kế &amp; tiến độ', false)
            ->assertSee('Concept mặt đứng chính')
            ->assertSee('Sửa lần 2')
            ->assertSee('Đang vướng')
            ->assertSee('Chờ khách duyệt concept')
            ->assertSee('Dựng phối cảnh sảnh')
            ->assertSee('40%');
    }
}
