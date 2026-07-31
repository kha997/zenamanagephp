<?php declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OpenWorkReadQuery;
use App\Services\TeamExceptionQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeamExceptionQueryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private OpenWorkReadQuery $openWorkReadQuery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->openWorkReadQuery = app(OpenWorkReadQuery::class);
    }

    public function test_null_for_actor_with_no_managed_project_or_team(): void
    {
        $actor = User::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        $openWork = $this->openWorkReadQuery->collect((string) $this->tenant->id);

        $result = (new TeamExceptionQuery())->build((string) $this->tenant->id, (string) $actor->id, $openWork);

        $this->assertNull($result);
    }

    public function test_summarizes_open_work_for_pm_owned_project_members(): void
    {
        $pm = User::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        $member = User::factory()->create(['tenant_id' => (string) $this->tenant->id, 'name' => 'Thành viên A']);
        $project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'pm_id' => (string) $pm->id]);
        Task::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'assigned_to' => (string) $member->id,
            'status' => Task::STATUS_IN_PROGRESS,
            'name' => 'Việc thành viên',
            'title' => 'Việc thành viên',
        ]);

        $openWork = $this->openWorkReadQuery->collect((string) $this->tenant->id);
        $result = (new TeamExceptionQuery())->build((string) $this->tenant->id, (string) $pm->id, $openWork);

        $this->assertNotNull($result);
        $summary = collect($result->items)->firstWhere('userId', (string) $member->id);
        $this->assertSame(1, $summary->openCount);
    }

    public function test_summarizes_open_work_for_team_lead_members(): void
    {
        $lead = User::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        $member = User::factory()->create(['tenant_id' => (string) $this->tenant->id, 'name' => 'Thành viên B']);
        $team = Team::factory()->create(['tenant_id' => (string) $this->tenant->id, 'team_lead_id' => (string) $lead->id]);
        $team->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        $project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        Task::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'assigned_to' => (string) $member->id,
            'status' => Task::STATUS_PENDING,
            'name' => 'Việc team',
            'title' => 'Việc team',
        ]);

        $openWork = $this->openWorkReadQuery->collect((string) $this->tenant->id);
        $result = (new TeamExceptionQuery())->build((string) $this->tenant->id, (string) $lead->id, $openWork);

        $this->assertNotNull($result);
        $summary = collect($result->items)->firstWhere('userId', (string) $member->id);
        $this->assertSame(1, $summary->openCount);
    }

    public function test_never_computes_availability_or_capacity_percentage(): void
    {
        $pm = User::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        $member = User::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        $project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'pm_id' => (string) $pm->id]);
        Task::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'assigned_to' => (string) $member->id,
            'status' => Task::STATUS_IN_PROGRESS,
            'name' => 'Test task',
            'title' => 'Test task',
        ]);

        $openWork = $this->openWorkReadQuery->collect((string) $this->tenant->id);
        $result = (new TeamExceptionQuery())->build((string) $this->tenant->id, (string) $pm->id, $openWork);

        foreach ($result->items ?? [] as $summary) {
            $this->assertFalse(property_exists($summary, 'availabilityPercent'));
            $this->assertFalse(property_exists($summary, 'capacityPercent'));
        }
    }

    public function test_member_name_lookup_is_bounded_not_per_member(): void
    {
        $pm = User::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        $project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'pm_id' => (string) $pm->id]);
        // 5 thành viên khác nhau, không thuộc team nào (activeMembers không phủ được
        // tên của họ) — TeamExceptionQuery phải tra tên bằng 1 whereIn(), không phải
        // 1 find() cho mỗi người.
        $members = User::factory()->count(5)->create(['tenant_id' => (string) $this->tenant->id]);
        foreach ($members as $i => $member) {
            Task::factory()->create([
                'tenant_id' => (string) $this->tenant->id,
                'project_id' => (string) $project->id,
                'assigned_to' => (string) $member->id,
                'status' => Task::STATUS_PENDING,
                'name' => "Việc {$i}", 'title' => "Việc {$i}",
            ]);
        }

        $openWork = $this->openWorkReadQuery->collect((string) $this->tenant->id);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $result = (new TeamExceptionQuery())->build((string) $this->tenant->id, (string) $pm->id, $openWork);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertNotNull($result);
        $this->assertCount(5, $result->items);
        // Project lookup (1) + Team lookup (1, empty) + 1 bounded name lookup = 3.
        // Không được tăng theo số thành viên (nếu là N+1, con số này sẽ là 3 + 5 = 8).
        $this->assertLessThanOrEqual(3, $queryCount);
    }
}
