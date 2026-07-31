<?php declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Tenant;
use App\Services\UpcomingMilestoneQuery;
use App\Support\Today\TodayMilestoneItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpcomingMilestoneQueryTest extends TestCase
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

    private function milestone(array $overrides): ProjectMilestone
    {
        return ProjectMilestone::create(array_merge([
            'project_id' => (string) $this->project->id,
            'name' => 'Milestone',
        ], $overrides));
    }

    public function test_pending_milestone_with_past_target_date_is_overdue(): void
    {
        $m = $this->milestone([
            'name' => 'Nghiệm thu điện',
            'target_date' => now()->subDays(3)->toDateString(),
            'status' => ProjectMilestone::STATUS_PENDING,
        ]);

        $result = (new UpcomingMilestoneQuery())->build((string) $this->tenant->id, 'actor', [(string) $this->project->id]);

        $item = collect($result->items)->firstWhere('milestoneId', (string) $m->id);
        $this->assertNotNull($item);
        $this->assertTrue($item->isOverdue);
    }

    public function test_already_overdue_status_milestone_with_past_target_date_is_still_overdue(): void
    {
        $m = $this->milestone([
            'name' => 'Nghiệm thu nước',
            'target_date' => now()->subDays(10)->toDateString(),
            'status' => ProjectMilestone::STATUS_OVERDUE,
        ]);

        $result = (new UpcomingMilestoneQuery())->build((string) $this->tenant->id, 'actor', [(string) $this->project->id]);

        $item = collect($result->items)->firstWhere('milestoneId', (string) $m->id);
        $this->assertNotNull($item);
        $this->assertTrue($item->isOverdue);
    }

    public function test_completed_milestone_with_past_target_date_is_excluded(): void
    {
        $m = $this->milestone([
            'name' => 'Đã hoàn thành',
            'target_date' => now()->subDays(10)->toDateString(),
            'completed_date' => now()->subDays(1)->toDateString(),
            'status' => ProjectMilestone::STATUS_COMPLETED,
        ]);

        $result = (new UpcomingMilestoneQuery())->build((string) $this->tenant->id, 'actor', [(string) $this->project->id]);

        $this->assertNull(collect($result->items)->firstWhere('milestoneId', (string) $m->id));
    }

    public function test_cancelled_milestone_with_past_target_date_is_excluded(): void
    {
        $m = $this->milestone([
            'name' => 'Đã huỷ',
            'target_date' => now()->subDays(10)->toDateString(),
            'status' => ProjectMilestone::STATUS_CANCELLED,
        ]);

        $result = (new UpcomingMilestoneQuery())->build((string) $this->tenant->id, 'actor', [(string) $this->project->id]);

        $this->assertNull(collect($result->items)->firstWhere('milestoneId', (string) $m->id));
    }

    public function test_future_milestone_is_upcoming_not_overdue(): void
    {
        $m = $this->milestone([
            'name' => 'Bàn giao móng',
            'target_date' => now()->addDays(5)->toDateString(),
            'status' => ProjectMilestone::STATUS_PENDING,
        ]);

        $result = (new UpcomingMilestoneQuery())->build((string) $this->tenant->id, 'actor', [(string) $this->project->id]);

        $item = collect($result->items)->firstWhere('milestoneId', (string) $m->id);
        $this->assertNotNull($item);
        $this->assertFalse($item->isOverdue);
    }

    public function test_null_target_date_is_excluded(): void
    {
        $m = $this->milestone([
            'name' => 'Chưa có ngày',
            'target_date' => null,
            'status' => ProjectMilestone::STATUS_PENDING,
        ]);

        $result = (new UpcomingMilestoneQuery())->build((string) $this->tenant->id, 'actor', [(string) $this->project->id]);

        $this->assertNull(collect($result->items)->firstWhere('milestoneId', (string) $m->id));
    }

    public function test_cross_tenant_project_milestone_is_excluded_even_with_matching_project_id_input(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherProject = Project::factory()->create(['tenant_id' => (string) $otherTenant->id]);
        ProjectMilestone::create([
            'project_id' => (string) $otherProject->id,
            'name' => 'Milestone tenant khác',
            'target_date' => now()->addDays(2)->toDateString(),
            'status' => ProjectMilestone::STATUS_PENDING,
        ]);

        // Cố tình truyền project_id của tenant KHÁC — query vẫn phải tự lọc
        // theo tenant thật qua join Project.tenant_id, không tin danh sách đầu vào.
        $result = (new UpcomingMilestoneQuery())->build((string) $this->tenant->id, 'actor', [(string) $otherProject->id]);

        $this->assertSame([], $result->items);
    }

    public function test_ordering_is_overdue_first_then_nearest_target_date_then_stable_id(): void
    {
        $farOverdue = $this->milestone([
            'name' => 'M-far-overdue', 'target_date' => now()->subDays(20)->toDateString(), 'status' => ProjectMilestone::STATUS_PENDING,
        ]);
        $nearOverdue = $this->milestone([
            'name' => 'M-near-overdue', 'target_date' => now()->subDays(1)->toDateString(), 'status' => ProjectMilestone::STATUS_PENDING,
        ]);
        $soonUpcoming = $this->milestone([
            'name' => 'M-soon', 'target_date' => now()->addDays(1)->toDateString(), 'status' => ProjectMilestone::STATUS_PENDING,
        ]);
        $laterUpcoming = $this->milestone([
            'name' => 'M-later', 'target_date' => now()->addDays(10)->toDateString(), 'status' => ProjectMilestone::STATUS_PENDING,
        ]);

        $result = (new UpcomingMilestoneQuery())->build((string) $this->tenant->id, 'actor', [(string) $this->project->id]);

        $ids = array_map(fn (TodayMilestoneItem $i) => $i->milestoneId, $result->items);
        $expectedOverdueFirst = [(string) $nearOverdue->id, (string) $farOverdue->id];
        // cả 2 overdue phải đứng trước cả 2 upcoming; giữa 2 overdue, target_date gần hơn (nearOverdue) đứng trước.
        $this->assertSame(array_slice($ids, 0, 2), $expectedOverdueFirst);
        $this->assertSame(array_slice($ids, 2, 2), [(string) $soonUpcoming->id, (string) $laterUpcoming->id]);
    }
}
