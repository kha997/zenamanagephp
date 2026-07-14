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

class BlockerTest extends TestCase
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

        // Khởi tạo session để TestCase tự chèn được _token CSRF vào các POST form.
        $this->get('/login');
    }

    public function test_block_requires_note_and_sets_fields(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->post(route('app.tasks.block', $this->task->id), [], $headers)
            ->assertSessionHasErrors('blocker_note');

        $this->actingAs($this->user)
            ->post(route('app.tasks.block', $this->task->id), ['blocker_note' => 'Chờ khách chốt vật liệu'], $headers)
            ->assertRedirect();

        $this->task->refresh();
        $this->assertNotNull($this->task->blocked_at);
        $this->assertSame('Chờ khách chốt vật liệu', $this->task->blocker_note);
        $this->assertSame((string) $this->user->id, (string) $this->task->blocked_by);
    }

    public function test_unblock_clears_fields(): void
    {
        $this->task->forceFill([
            'blocked_at' => now(),
            'blocker_note' => 'x',
            'blocked_by' => (string) $this->user->id,
        ])->save();

        $this->actingAs($this->user)
            ->post(route('app.tasks.unblock', $this->task->id), [], ['X-Tenant-ID' => (string) $this->tenant->id])
            ->assertRedirect();

        $this->task->refresh();
        $this->assertNull($this->task->blocked_at);
        $this->assertNull($this->task->blocker_note);
        $this->assertNull($this->task->blocked_by);
    }

    public function test_cross_tenant_block_is_404(): void
    {
        $other = Tenant::factory()->create();
        $intruder = $this->createTenantUser($other, [], ['admin'], ['task.update', 'design-item.manage']);

        $this->actingAs($intruder)
            ->post(route('app.tasks.block', $this->task->id), ['blocker_note' => 'x'], ['X-Tenant-ID' => (string) $other->id])
            ->assertNotFound();

        $this->assertNull($this->task->fresh()->blocked_at);
    }

    public function test_design_item_block_and_unblock(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->post(route('operator.design-items.block', $this->item->id), ['blocker_note' => 'Thiếu bản vẽ hiện trạng'], $headers)
            ->assertRedirect();
        $this->assertNotNull($this->item->fresh()->blocked_at);

        $this->actingAs($this->user)
            ->post(route('operator.design-items.unblock', $this->item->id), [], $headers)
            ->assertRedirect();
        $this->assertNull($this->item->fresh()->blocked_at);
    }
}
