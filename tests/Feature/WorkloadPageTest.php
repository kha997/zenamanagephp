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

class WorkloadPageTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $viewer;
    private User $worker;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->viewer = $this->createTenantUser($this->tenant, [], ['admin'], ['task.view']);
        $this->worker = $this->createTenantUser($this->tenant, ['name' => 'Kiến Trúc Sư A'], ['member'], []);

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
            'assigned_to' => (string) $this->worker->id,
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
            'assigned_to' => (string) $this->worker->id,
            'blocked_at' => null,
        ], $overrides));
    }

    public function test_shows_person_with_their_open_task_and_design_item(): void
    {
        $task = $this->openTask(['name' => 'Dựng mặt bằng tầng 1', 'title' => 'Dựng mặt bằng tầng 1']);
        $item = $this->openDesignItem(['name' => 'Concept nội thất phòng khách']);

        $response = $this->actingAs($this->viewer)->get(route('app.workload.index'));

        $response->assertOk();
        $response->assertSee('Khối lượng công việc');
        $response->assertSee('Kiến Trúc Sư A');
        $response->assertSee('Dựng mặt bằng tầng 1');
        $response->assertSee('Concept nội thất phòng khách');
        $response->assertSee('2 đang mở');
    }

    public function test_requires_task_view_permission(): void
    {
        $noPerm = $this->createTenantUser($this->tenant, [], ['member'], []);

        $this->actingAs($noPerm)->get(route('app.workload.index'))->assertStatus(302);
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
        ]);

        $response = $this->actingAs($this->viewer)->get(route('app.workload.index'));

        $response->assertOk();
        $response->assertDontSee('Việc tenant khác');
    }

    public function test_closed_items_do_not_appear(): void
    {
        $this->openTask(['status' => Task::STATUS_COMPLETED, 'name' => 'Task đã xong', 'title' => 'Task đã xong']);
        $this->openDesignItem(['review_status' => DesignItem::STATUS_FINAL, 'name' => 'Hạng mục đã chốt']);

        $response = $this->actingAs($this->viewer)->get(route('app.workload.index'));

        $response->assertOk();
        $response->assertDontSee('Task đã xong');
        $response->assertDontSee('Hạng mục đã chốt');
    }

    public function test_unassigned_open_item_appears_under_unassigned_block(): void
    {
        $this->openTask(['assigned_to' => null, 'name' => 'Việc chưa ai nhận', 'title' => 'Việc chưa ai nhận']);

        $response = $this->actingAs($this->viewer)->get(route('app.workload.index'));

        $response->assertOk();
        $response->assertSee('Chưa phân công');
        $response->assertSee('Việc chưa ai nhận');
    }

    public function test_overdue_task_counts_and_badges(): void
    {
        $this->openTask(['end_date' => now()->subDays(3)->toDateString()]);

        $response = $this->actingAs($this->viewer)->get(route('app.workload.index'));

        $response->assertOk();
        $response->assertSee('1 quá hạn');
        $response->assertSee('Quá hạn');
    }

    public function test_person_with_zero_items_still_renders(): void
    {
        $response = $this->actingAs($this->viewer)->get(route('app.workload.index'));

        $response->assertOk();
        $response->assertSee('Kiến Trúc Sư A');
        $response->assertSee('0 đang mở');
    }

    public function test_tasks_list_shows_assignee_column(): void
    {
        $this->openTask(['name' => 'Task có người nhận', 'title' => 'Task có người nhận']);

        $response = $this->actingAs($this->viewer)->get(route('app.tasks'));

        $response->assertOk();
        $response->assertSee('Người phụ trách');
        $response->assertSee('Kiến Trúc Sư A');
    }

    public function test_tasks_list_filters_by_assignee(): void
    {
        $other = $this->createTenantUser($this->tenant, ['name' => 'Người Khác B'], ['member'], []);
        $this->openTask(['name' => 'Việc của A', 'title' => 'Việc của A']);
        $this->openTask(['name' => 'Việc của B', 'title' => 'Việc của B', 'assigned_to' => (string) $other->id]);

        $response = $this->actingAs($this->viewer)->get(route('app.tasks', ['assigned_to' => (string) $this->worker->id]));

        $response->assertOk();
        $response->assertSee('Việc của A');
        $response->assertDontSee('Việc của B');
    }

    public function test_tasks_list_ignores_foreign_tenant_assignee_filter(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createTenantUser($otherTenant, [], ['member'], []);
        $this->openTask(['name' => 'Việc của A', 'title' => 'Việc của A']);

        $response = $this->actingAs($this->viewer)->get(route('app.tasks', ['assigned_to' => (string) $foreign->id]));

        $response->assertOk();
        $response->assertSee('Việc của A');
    }
}
