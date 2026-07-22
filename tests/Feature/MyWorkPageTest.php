<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\DesignItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class MyWorkPageTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $viewer;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->viewer = $this->createTenantUser($this->tenant, ['name' => 'Kiến Trúc Sư A'], ['admin'], ['task.view']);

        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
        ]);
    }

    private function openTask(array $overrides = []): Task
    {
        return Task::factory()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'status' => Task::STATUS_IN_PROGRESS,
            'assigned_to' => (string) $this->viewer->id,
            'blocked_at' => null,
            'end_date' => now()->addDays(7)->toDateString(),
        ], $overrides));
    }

    private function openDesignItem(array $overrides = []): DesignItem
    {
        return DesignItem::factory()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'review_status' => DesignItem::STATUS_DRAFT,
            'assigned_to' => (string) $this->viewer->id,
            'blocked_at' => null,
        ], $overrides));
    }

    public function test_shows_only_my_own_open_task_and_design_item(): void
    {
        $this->openTask(['name' => 'Dựng mặt bằng tầng 1', 'title' => 'Dựng mặt bằng tầng 1']);
        $this->openDesignItem(['name' => 'Concept nội thất phòng khách']);

        $response = $this->actingAs($this->viewer)->get(route('app.my-work.index'));

        $response->assertOk();
        $response->assertSee('Việc của tôi');
        $response->assertSee('Dựng mặt bằng tầng 1');
        $response->assertSee('Concept nội thất phòng khách');
        $response->assertSee('2 đang mở');
    }

    public function test_coworkers_items_do_not_render(): void
    {
        $coworker = $this->createTenantUser($this->tenant, ['name' => 'Người Khác B'], ['member'], []);
        $this->openTask(['name' => 'Việc của B', 'title' => 'Việc của B', 'assigned_to' => (string) $coworker->id]);
        $this->openTask(['name' => 'Việc của tôi', 'title' => 'Việc của tôi']);

        $response = $this->actingAs($this->viewer)->get(route('app.my-work.index'));

        $response->assertOk();
        $response->assertSee('Việc của tôi');
        $response->assertDontSee('Việc của B');
    }

    public function test_unassigned_items_do_not_render(): void
    {
        $this->openTask(['name' => 'Việc chưa ai nhận', 'title' => 'Việc chưa ai nhận', 'assigned_to' => null]);
        $this->openTask(['name' => 'Việc của tôi', 'title' => 'Việc của tôi']);

        $response = $this->actingAs($this->viewer)->get(route('app.my-work.index'));

        $response->assertOk();
        $response->assertSee('Việc của tôi');
        $response->assertDontSee('Việc chưa ai nhận');
    }

    public function test_count_line_matches_overdue_and_blocked(): void
    {
        $this->openTask(['name' => 'Việc trễ', 'title' => 'Việc trễ', 'end_date' => now()->subDays(2)->toDateString()]);
        $this->openTask(['name' => 'Việc bị chặn', 'title' => 'Việc bị chặn', 'blocked_at' => now()]);

        $response = $this->actingAs($this->viewer)->get(route('app.my-work.index'));

        $response->assertOk();
        $response->assertSee('2 đang mở');
        $response->assertSee('1 quá hạn');
        $response->assertSee('1 bị chặn');
    }

    public function test_requires_task_view_permission(): void
    {
        $noPerm = $this->createTenantUser($this->tenant, [], ['member'], []);

        $this->actingAs($noPerm)->get(route('app.my-work.index'))->assertStatus(302);
    }

    public function test_cross_tenant_items_never_render(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherProject = Project::factory()->create(['tenant_id' => (string) $otherTenant->id]);
        Task::factory()->create([
            'tenant_id' => (string) $otherTenant->id,
            'project_id' => (string) $otherProject->id,
            'status' => Task::STATUS_IN_PROGRESS,
            'name' => 'Việc tenant khác',
            'title' => 'Việc tenant khác',
            'assigned_to' => (string) $this->viewer->id,
        ]);

        $response = $this->actingAs($this->viewer)->get(route('app.my-work.index'));

        $response->assertOk();
        $response->assertDontSee('Việc tenant khác');
    }

    public function test_empty_state_when_no_open_items(): void
    {
        $response = $this->actingAs($this->viewer)->get(route('app.my-work.index'));

        $response->assertOk();
        $response->assertSee('Bạn chưa có việc nào đang mở');
    }
}
