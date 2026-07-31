<?php declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OpenWorkReadQuery;
use App\Services\TodayWorkspaceReadService;
use App\Support\Dashboard\Availability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodayWorkspaceReadServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $actor;
    private Project $project;
    private TodayWorkspaceReadService $service;
    private OpenWorkReadQuery $openWorkReadQuery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->actor = User::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        $this->service = app(TodayWorkspaceReadService::class);
        $this->openWorkReadQuery = app(OpenWorkReadQuery::class);
    }

    private function taskFor(User $assignee, array $overrides = []): Task
    {
        return Task::factory()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'assigned_to' => (string) $assignee->id,
            'status' => Task::STATUS_PENDING,
            'name' => 'Việc',
            'title' => 'Việc',
        ], $overrides));
    }

    public function test_personal_open_work_contains_only_assigned_to_actor(): void
    {
        $coworker = User::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        $mine = $this->taskFor($this->actor, ['name' => 'Việc của tôi', 'title' => 'Việc của tôi']);
        $this->taskFor($coworker, ['name' => 'Việc của B', 'title' => 'Việc của B']);

        $openWork = $this->openWorkReadQuery->collect((string) $this->tenant->id);
        $result = $this->service->personalOpenWork($openWork, (string) $this->actor->id);

        $this->assertSame(Availability::AVAILABLE, $result->availability);
        $this->assertCount(1, $result->items);
        $this->assertSame((string) $mine->id, $result->items[0]->sourceId);
    }

    public function test_in_progress_shows_multiple_tasks_without_marking_one_primary(): void
    {
        $this->taskFor($this->actor, ['status' => Task::STATUS_IN_PROGRESS, 'name' => 'Việc 1', 'title' => 'Việc 1']);
        $this->taskFor($this->actor, ['status' => Task::STATUS_IN_PROGRESS, 'name' => 'Việc 2', 'title' => 'Việc 2']);
        $this->taskFor($this->actor, ['status' => Task::STATUS_PENDING, 'name' => 'Việc chưa bắt đầu', 'title' => 'Việc chưa bắt đầu']);

        $openWork = $this->openWorkReadQuery->collect((string) $this->tenant->id);
        $result = $this->service->inProgress($openWork, (string) $this->actor->id);

        $this->assertCount(2, $result->items);
        foreach ($result->items as $item) {
            $this->assertFalse(property_exists($item, 'isPrimary'));
            $this->assertFalse(property_exists($item, 'isCurrent'));
        }
    }

    public function test_overdue_and_blocked_includes_items_matching_either_condition(): void
    {
        $overdue = $this->taskFor($this->actor, [
            'name' => 'Việc trễ', 'title' => 'Việc trễ',
            'end_date' => now()->subDays(2)->toDateString(),
        ]);
        $blocked = $this->taskFor($this->actor, [
            'name' => 'Việc bị chặn', 'title' => 'Việc bị chặn',
            'blocked_at' => now(),
        ]);
        $this->taskFor($this->actor, ['name' => 'Việc bình thường', 'title' => 'Việc bình thường']);

        $openWork = $this->openWorkReadQuery->collect((string) $this->tenant->id);
        $result = $this->service->overdueAndBlocked($openWork, (string) $this->actor->id);

        $ids = array_map(fn ($item) => $item->sourceId, $result->items);
        $this->assertContains((string) $overdue->id, $ids);
        $this->assertContains((string) $blocked->id, $ids);
        $this->assertCount(2, $result->items);
    }
}
