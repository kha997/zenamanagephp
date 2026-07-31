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

    public function test_build_returns_complete_view_model_with_single_open_work_fetch(): void
    {
        $this->taskFor($this->actor, ['name' => 'Việc build', 'title' => 'Việc build']);

        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $viewModel = $this->service->build($this->actor);
        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->assertInstanceOf(\App\Support\Today\TodayWorkspaceViewModel::class, $viewModel);
        $this->assertCount(1, $viewModel->personalOpenWork->items);
        $this->assertNull($viewModel->teamException);

        // OpenWorkReadQuery::collect() chạy đúng 1 lần (Task + eager-load project,
        // DesignItem + eager-load project = 4 query) — không phải 3-4 lần cho
        // personalOpenWork/inProgress/overdueAndBlocked/teamException riêng rẽ.
        $openWorkQueries = collect($queries)->filter(
            fn (array $q) => str_contains($q['query'], 'from "tasks"') || str_contains($q['query'], 'from "design_items"')
        );
        $this->assertLessThanOrEqual(4, $openWorkQueries->count());
    }

    public function test_one_section_failure_does_not_break_other_sections(): void
    {
        $this->taskFor($this->actor, ['name' => 'Việc sống sót', 'title' => 'Việc sống sót']);

        $failingService = new class(app(OpenWorkReadQuery::class)) extends TodayWorkspaceReadService {
            public function __construct(OpenWorkReadQuery $openWorkReadQuery)
            {
                parent::__construct(
                    $openWorkReadQuery,
                    app(\App\Services\UpcomingMilestoneQuery::class),
                    app(\App\Services\UnreadUpdateQuery::class),
                    app(\App\Services\TeamExceptionQuery::class),
                );
            }

            public function upcomingMilestones(string $tenantId, string $actorId, array $projectIds): \App\Support\Today\TodaySectionResult
            {
                throw new \RuntimeException('boom');
            }
        };

        $viewModel = $failingService->build($this->actor);

        $this->assertSame(\App\Support\Dashboard\Availability::ERROR, $viewModel->upcomingMilestones->availability);
        $this->assertCount(1, $viewModel->personalOpenWork->items);
    }

    public function test_open_work_fetch_failure_propagates_error_to_upcoming_milestones_and_team_exception(): void
    {
        $this->taskFor($this->actor, ['name' => 'Việc không liên quan', 'title' => 'Việc không liên quan']);

        $failingService = new class(app(OpenWorkReadQuery::class)) extends TodayWorkspaceReadService {
            public function __construct(OpenWorkReadQuery $openWorkReadQuery)
            {
                parent::__construct(
                    $openWorkReadQuery,
                    app(\App\Services\UpcomingMilestoneQuery::class),
                    app(\App\Services\UnreadUpdateQuery::class),
                    app(\App\Services\TeamExceptionQuery::class),
                );
            }

            protected function loadOpenWork(string $tenantId): array
            {
                report(new \RuntimeException('open work fetch boom'));

                return [collect(), true];
            }
        };

        $viewModel = $failingService->build($this->actor);

        $this->assertSame(Availability::ERROR, $viewModel->upcomingMilestones->availability);
        $this->assertSame(Availability::ERROR, $viewModel->personalOpenWork->availability);
        $this->assertNotNull($viewModel->teamException);
        $this->assertSame(Availability::ERROR, $viewModel->teamException->availability);

        // Unread Updates không phụ thuộc open work — không bị lây lỗi (NO_DATA, không phải ERROR).
        $this->assertNotSame(Availability::ERROR, $viewModel->unreadUpdates->availability);
    }
}
