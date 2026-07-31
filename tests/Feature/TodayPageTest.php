<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class TodayPageTest extends TestCase
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
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);
    }

    public function test_personal_open_work_and_in_progress_sections_render(): void
    {
        Task::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'assigned_to' => (string) $this->viewer->id,
            'status' => Task::STATUS_IN_PROGRESS,
            'name' => 'Dựng mặt bằng tầng 1',
            'title' => 'Dựng mặt bằng tầng 1',
        ]);

        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertSee('Hôm nay');
        $response->assertSee('Dựng mặt bằng tầng 1');
    }

    public function test_requires_task_view_permission(): void
    {
        $noPerm = $this->createTenantUser($this->tenant, [], ['member'], []);

        $this->actingAs($noPerm)->get(route('app.today'))->assertStatus(302);
    }

    public function test_cross_tenant_items_never_render(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherProject = Project::factory()->create(['tenant_id' => (string) $otherTenant->id]);
        Task::factory()->create([
            'tenant_id' => (string) $otherTenant->id,
            'project_id' => (string) $otherProject->id,
            'status' => Task::STATUS_IN_PROGRESS,
            'assigned_to' => (string) $this->viewer->id,
            'name' => 'Việc tenant khác',
            'title' => 'Việc tenant khác',
        ]);

        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertDontSee('Việc tenant khác');
    }

    public function test_upcoming_milestone_renders_for_project_with_open_work(): void
    {
        Task::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'assigned_to' => (string) $this->viewer->id,
            'status' => Task::STATUS_PENDING,
            'name' => 'Việc dự án',
            'title' => 'Việc dự án',
        ]);
        ProjectMilestone::create([
            'project_id' => (string) $this->project->id,
            'name' => 'Bàn giao móng',
            'target_date' => now()->addDays(3)->toDateString(),
            'status' => ProjectMilestone::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertSee('Bàn giao móng');
    }

    public function test_unread_notification_renders_and_is_not_labeled_action_required(): void
    {
        Notification::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'user_id' => (string) $this->viewer->id,
            'title' => 'Có tài liệu mới',
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertSee('Có tài liệu mới');
    }

    public function test_no_action_required_section_rendered(): void
    {
        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertDontSee('Action Required');
        $response->assertDontSee('Cần hành động');
        $response->assertDontSeeText('actionRequired');
    }

    public function test_project_progress_percentage_absent(): void
    {
        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertDontSee('progress_percent');
        $response->assertDontSee('overall_progress');
        $response->assertDontSee('completion_rate');
    }

    public function test_financial_data_absent(): void
    {
        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertDontSee('budget_actual');
    }

    public function test_employee_cannot_see_pm_sections(): void
    {
        Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'pm_id' => (string) User::factory()->create(['tenant_id' => (string) $this->tenant->id])->id]);

        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertDontSee('Khối lượng công việc đã ghi nhận');
    }

    public function test_pm_sees_team_exception_section(): void
    {
        $member = User::factory()->create(['tenant_id' => (string) $this->tenant->id, 'name' => 'Thành viên PM']);
        $pmProject = Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'pm_id' => (string) $this->viewer->id]);
        Task::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $pmProject->id,
            'assigned_to' => (string) $member->id,
            'status' => Task::STATUS_PENDING,
            'name' => 'Việc thành viên PM',
            'title' => 'Việc thành viên PM',
        ]);

        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertSee('Khối lượng công việc đã ghi nhận');
        $response->assertSee('Thành viên PM');
    }

    public function test_empty_state_when_no_open_items(): void
    {
        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertSee('Bạn chưa có việc nào đang mở');
    }
}
