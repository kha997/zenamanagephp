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

class BlockerUiTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;
    private Project $project;
    private Task $task;
    private DesignItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser(
            $this->tenant,
            [],
            ['admin'],
            ['task.view', 'task.update', 'design-item.view', 'design-item.manage']
        );

        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);

        $this->task = Task::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'name' => 'Dựng mô hình 3D',
            'title' => 'Dựng mô hình 3D',
            'status' => 'pending',
        ]);

        $this->item = DesignItem::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'name' => 'Concept mặt đứng',
            'review_status' => DesignItem::STATUS_DRAFT,
            'created_by' => (string) $this->user->id,
        ]);

        $this->get('/login');
    }

    /** Design-item bị block → hiển thị note + nút "Gỡ vướng". */
    public function test_blocked_design_item_shows_unblock_form(): void
    {
        $this->item->forceFill([
            'blocked_at' => now(),
            'blocker_note' => 'Thiếu bản vẽ hiện trạng',
            'blocked_by' => (string) $this->user->id,
        ])->save();

        $response = $this->actingAs($this->user)
            ->get(route('operator.design-items.show', $this->item->id));

        $response->assertOk();
        $response->assertSee('Thiếu bản vẽ hiện trạng');
        $response->assertSee('Gỡ vướng');
        $response->assertDontSee('Báo vướng');
    }

    /** Design-item chưa block → hiển thị form "Báo vướng". */
    public function test_unblocked_design_item_shows_block_form(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('operator.design-items.show', $this->item->id));

        $response->assertOk();
        $response->assertSee('Báo vướng');
        $response->assertDontSee('Gỡ vướng');
    }

    /** User không có quyền → không thấy form block/unblock. */
    public function test_user_without_permission_sees_no_block_form(): void
    {
        $viewer = $this->createTenantUser(
            $this->tenant,
            [],
            ['viewer'],
            ['task.view', 'design-item.view']  // no manage/update permissions
        );

        $this->item->forceFill([
            'blocked_at' => now(),
            'blocker_note' => 'Chờ khách',
            'blocked_by' => (string) $this->user->id,
        ])->save();

        $response = $this->actingAs($viewer)
            ->get(route('operator.design-items.show', $this->item->id));

        $response->assertOk();
        $response->assertSee('Chờ khách'); // note still visible
        $response->assertDontSee('Gỡ vướng'); // but no unblock form
        $response->assertDontSee('Báo vướng'); // and no block form
    }

    /** Task bị block → hiển thị note + nút "Gỡ vướng". */
    public function test_blocked_task_shows_unblock_form(): void
    {
        $this->task->forceFill([
            'blocked_at' => now(),
            'blocker_note' => 'Chờ khách chốt vật liệu',
            'blocked_by' => (string) $this->user->id,
        ])->save();

        $response = $this->actingAs($this->user)
            ->get(route('app.tasks.show', $this->task->id));

        $response->assertOk();
        $response->assertSee('Chờ khách chốt vật liệu');
        $response->assertSee('Gỡ vướng');
        $response->assertDontSee('Báo vướng');
    }

    /** Task chưa block → hiển thị form "Báo vướng". */
    public function test_unblocked_task_shows_block_form(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('app.tasks.show', $this->task->id));

        $response->assertOk();
        $response->assertSee('Báo vướng');
        $response->assertDontSee('Gỡ vướng');
    }

    /** User không có quyền task.update → không thấy form block/unblock trên task. */
    public function test_user_without_task_update_sees_no_block_form(): void
    {
        $viewer = $this->createTenantUser(
            $this->tenant,
            [],
            ['task-viewer'],
            ['task.view']  // no task.update
        );

        $this->task->forceFill([
            'blocked_at' => now(),
            'blocker_note' => 'Chờ vật tư',
            'blocked_by' => (string) $this->user->id,
        ])->save();

        $response = $this->actingAs($viewer)
            ->get(route('app.tasks.show', $this->task->id));

        $response->assertOk();
        $response->assertSee('Chờ vật tư'); // note still visible
        $response->assertDontSee('Gỡ vướng'); // but no unblock form
        $response->assertDontSee('Báo vướng'); // and no block form
    }

    /** Design-item index shows "Vướng" badge for blocked items. */
    public function test_design_item_index_shows_blocker_badge(): void
    {
        $this->item->forceFill([
            'blocked_at' => now(),
            'blocker_note' => 'Test note',
            'blocked_by' => (string) $this->user->id,
        ])->save();

        $response = $this->actingAs($this->user)
            ->get(route('operator.design-items.index'));

        $response->assertOk();
        $response->assertSee('Vướng');
    }

    /** Design-item index does NOT show "Vướng" badge for unblocked items. */
    public function test_design_item_index_hides_blocker_badge_when_not_blocked(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('operator.design-items.index'));

        $response->assertOk();
        $response->assertDontSee('Vướng');
    }
}
