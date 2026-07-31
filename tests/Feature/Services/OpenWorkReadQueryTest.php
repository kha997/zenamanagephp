<?php declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\DesignItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Services\OpenWorkReadQuery;
use App\Support\Work\OpenWorkItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenWorkReadQueryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);
    }

    public function test_collects_open_tasks_and_design_items_for_tenant(): void
    {
        $task = Task::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'status' => Task::STATUS_IN_PROGRESS,
            'name' => 'Việc mở',
            'title' => 'Việc mở',
            'priority' => Task::PRIORITY_HIGH,
            'blocker_note' => 'Chờ vật tư',
            'blocked_by' => 'Nhà cung cấp A',
        ]);
        $designItem = DesignItem::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'review_status' => DesignItem::STATUS_DRAFT,
        ]);

        $items = (new OpenWorkReadQuery())->collect((string) $this->tenant->id);

        $taskItem = $items->first(fn (OpenWorkItem $i) => $i->sourceId === (string) $task->id);
        $designItemRow = $items->first(fn (OpenWorkItem $i) => $i->sourceId === (string) $designItem->id);

        $this->assertInstanceOf(OpenWorkItem::class, $taskItem);
        $this->assertSame('task', $taskItem->sourceType);
        $this->assertSame((string) $this->project->id, $taskItem->projectId);
        $this->assertSame('Chờ vật tư', $taskItem->blockerNote);
        $this->assertSame('Nhà cung cấp A', $taskItem->blockedBy);
        $this->assertSame(Task::PRIORITY_HIGH, $taskItem->priority);
        $this->assertSame('design_item', $designItemRow->sourceType);
        $this->assertNull($designItemRow->priority);
    }

    public function test_excludes_closed_tasks_and_approved_design_items(): void
    {
        Task::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'status' => Task::STATUS_COMPLETED,
            'name' => 'Việc đã xong',
            'title' => 'Việc đã xong',
        ]);
        DesignItem::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'review_status' => DesignItem::STATUS_FINAL,
        ]);

        $items = (new OpenWorkReadQuery())->collect((string) $this->tenant->id);

        $this->assertCount(0, $items);
    }

    public function test_cross_tenant_items_never_returned(): void
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

        $items = (new OpenWorkReadQuery())->collect((string) $this->tenant->id);

        $this->assertTrue($items->first(fn (OpenWorkItem $i) => $i->name === 'Việc tenant khác') === null);
    }
}
