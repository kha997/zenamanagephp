<?php

declare(strict_types=1);

namespace Tests\Feature\ProjectHealth;

use App\Models\Baseline;
use App\Models\Component;
use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\ContractPayment;
use App\Models\DesignItem;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\ProjectDelayStatus;
use App\Services\ProjectHealth\ProjectHealthReadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-103 — shared project health read model (OWN-2026-009 §4): one progress definition with its basis, schedule state,
 * attention flags and separate money measures, each with an explicit availability; missing data is never 0 / on track.
 */
class ProjectHealthReadServiceTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 10:00:00');
        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser($this->tenant, [], ['admin'], ['project.view']);
        $this->project = $this->projectFor($this->tenant);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_progress_is_weighted_by_root_component_planned_cost(): void
    {
        $this->makeComponent(['progress_percent' => 100, 'planned_cost' => 300]);
        $this->makeComponent(['progress_percent' => 0, 'planned_cost' => 100]);
        $this->task(['progress_percent' => 10]);

        $progress = $this->health()['progress'];

        $this->assertSame('available', $progress['availability']);
        $this->assertSame('component_cost_weighted', $progress['basis']);
        $this->assertSame('normal', $progress['reliability']);
        $this->assertEqualsWithDelta(75.0, $progress['value'], 0.001);
    }

    public function test_progress_falls_back_to_active_task_average_with_limited_reliability(): void
    {
        $this->task(['progress_percent' => 40]);
        $this->task(['progress_percent' => 80]);
        $this->task(['progress_percent' => 0, 'status' => Task::STATUS_CANCELLED]);

        $progress = $this->health()['progress'];

        $this->assertSame('available', $progress['availability']);
        $this->assertSame('task_average', $progress['basis']);
        $this->assertSame('limited', $progress['reliability']);
        $this->assertEqualsWithDelta(60.0, $progress['value'], 0.001);
    }

    public function test_progress_without_components_or_tasks_is_no_data_not_zero(): void
    {
        $this->makeComponent(['progress_percent' => 50, 'planned_cost' => 0]);

        $progress = $this->health()['progress'];

        $this->assertSame('no_data', $progress['availability']);
        $this->assertNull($progress['value']);
        $this->assertNull($progress['basis']);
    }

    public function test_schedule_reuses_delay_status_and_counts_milestones(): void
    {
        $this->assertSame(ProjectDelayStatus::STATE_NO_BASELINE, $this->health()['schedule']['delay']['state']);
        $this->assertSame('no_data', $this->health()['schedule']['milestones']['availability']);

        Baseline::query()->create([
            'project_id' => (string) $this->project->id,
            'type' => 'execution',
            'start_date' => '2026-01-01',
            'end_date' => '2026-10-31',
            'version' => 1,
            'created_by' => (string) $this->user->id,
        ]);
        $this->milestone(['status' => ProjectMilestone::STATUS_COMPLETED, 'target_date' => '2026-09-01']);
        $this->milestone(['status' => ProjectMilestone::STATUS_PENDING, 'target_date' => '2026-10-01']);
        $this->milestone(['status' => ProjectMilestone::STATUS_PENDING, 'target_date' => '2026-12-01']);

        $schedule = $this->health()['schedule'];

        $this->assertSame(ProjectDelayStatus::STATE_LATE, $schedule['delay']['state']);
        $this->assertSame(3, $schedule['delay']['days_late']);
        $this->assertSame(['availability' => 'available', 'total' => 3, 'completed' => 1, 'overdue' => 1], $schedule['milestones']);
    }

    public function test_attention_counts_blocked_and_overdue_work(): void
    {
        $this->task(['blocked_at' => now()]);
        $this->task(['blocked_at' => now(), 'status' => Task::STATUS_COMPLETED]);
        $this->task(['end_date' => '2026-10-01']);
        $this->task(['end_date' => '2026-10-01', 'status' => Task::STATUS_CANCELLED]);
        $this->task(['end_date' => '2026-12-01']);
        DesignItem::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'created_by' => (string) $this->user->id,
            'blocked_at' => now(),
        ]);
        $this->milestone(['status' => ProjectMilestone::STATUS_PENDING, 'target_date' => '2026-10-01']);

        $attention = $this->health()['attention'];

        $this->assertSame(1, $attention['blocked_tasks']);
        $this->assertSame(1, $attention['blocked_design_items']);
        $this->assertSame(1, $attention['overdue_tasks']);
        $this->assertSame(1, $attention['overdue_milestones']);
        $this->assertFalse($attention['schedule_late']);
        $this->assertSame('not_applicable', $attention['treasury_alerts']['availability']);
    }

    public function test_money_measures_are_separate_and_missing_ones_are_no_data(): void
    {
        $finance = $this->health()['finance'];

        foreach (['budget', 'contract_value', 'cash_received', 'cost_incurred'] as $key) {
            $this->assertSame('no_data', $finance[$key]['availability'], $key);
            $this->assertNull($finance[$key]['value'], $key);
        }
        $this->assertSame('not_applicable', $finance['treasury']['availability']);
        $this->assertArrayNotHasKey('profit', $finance);

        $this->project->update(['budget_total' => 1000]);
        $contract = $this->contract(['total_value' => 900]);
        $this->contract(['total_value' => 500, 'status' => Contract::STATUS_CANCELLED]);
        $this->payment($contract, 300, ContractPayment::STATUS_PAID);
        $this->payment($contract, 200, ContractPayment::STATUS_PLANNED);
        ContractExpense::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $contract->id,
            'expense_date' => '2026-10-15',
            'amount' => 120,
            'category' => 'material',
            'description' => 'Thép',
            'recorded_by' => (string) $this->user->id,
        ]);
        TreasuryWallet::factory()->create(['project_id' => (string) $this->project->id]);

        $finance = $this->health()['finance'];

        $this->assertSame(['available', '1000.00'], [$finance['budget']['availability'], $finance['budget']['value']]);
        $this->assertSame(['available', '900.00'], [$finance['contract_value']['availability'], $finance['contract_value']['value']]);
        $this->assertSame(['available', '300.00'], [$finance['cash_received']['availability'], $finance['cash_received']['value']]);
        $this->assertSame(['available', '120.00'], [$finance['cost_incurred']['availability'], $finance['cost_incurred']['value']]);
        $this->assertSame('cost_recorded_not_cash_paid', $finance['cost_incurred']['basis']);
        $this->assertSame('available', $finance['treasury']['availability']);
        $this->assertSame('0.00', $finance['treasury']['held_total']);
        $this->assertSame('available', $this->health()['attention']['treasury_alerts']['availability']);
    }

    public function test_other_tenant_and_other_project_data_is_ignored(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = $this->projectFor($otherTenant);
        $sibling = $this->projectFor($this->tenant);

        Task::factory()->create(['tenant_id' => (string) $otherTenant->id, 'project_id' => (string) $foreign->id, 'progress_percent' => 90, 'blocked_at' => now()]);
        Task::factory()->create(['tenant_id' => (string) $this->tenant->id, 'project_id' => (string) $sibling->id, 'progress_percent' => 90, 'blocked_at' => now()]);
        Component::factory()->create(['tenant_id' => (string) $this->tenant->id, 'project_id' => (string) $sibling->id, 'planned_cost' => 100, 'progress_percent' => 90]);

        $health = $this->health();

        $this->assertSame('no_data', $health['progress']['availability']);
        $this->assertSame(0, $health['attention']['blocked_tasks']);
    }

    public function test_query_count_does_not_grow_with_task_count(): void
    {
        $this->task([]);
        $few = $this->countQueries();

        foreach (range(1, 50) as $i) {
            $this->task(['blocked_at' => $i % 2 === 0 ? now() : null, 'end_date' => '2026-10-01']);
        }
        $many = $this->countQueries();

        $this->assertSame($few, $many);
    }

    public function test_project_page_shows_no_data_instead_of_zero_percent(): void
    {
        $this->actingAs($this->user)
            ->get(route('app.projects.show', (string) $this->project->id), ['X-Tenant-ID' => (string) $this->tenant->id])
            ->assertOk()
            ->assertSeeInOrder(['Tiến độ', 'Chưa có dữ liệu', 'Quản lý dự án']);

        $this->task(['progress_percent' => 40]);

        $this->actingAs($this->user)
            ->get(route('app.projects.show', (string) $this->project->id), ['X-Tenant-ID' => (string) $this->tenant->id])
            ->assertOk()
            ->assertSeeInOrder(['Tiến độ', '40% (theo công việc)', 'Quản lý dự án']);
    }

    /**
     * @return array<string, mixed>
     */
    private function health(): array
    {
        return $this->app->make(ProjectHealthReadService::class)->forProject($this->project->fresh());
    }

    private function countQueries(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->health();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function projectFor(Tenant $tenant): Project
    {
        return Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'status' => 'active',
            'budget_total' => 0,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function makeComponent(array $attributes): Component
    {
        return Component::factory()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function task(array $attributes): Task
    {
        return Task::factory()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'status' => Task::STATUS_IN_PROGRESS,
            'progress_percent' => 0,
            'blocked_at' => null,
            'end_date' => null,
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function milestone(array $attributes): ProjectMilestone
    {
        return ProjectMilestone::query()->create(array_merge([
            'project_id' => (string) $this->project->id,
            'name' => 'Milestone',
            'created_by' => (string) $this->user->id,
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function contract(array $attributes): Contract
    {
        return Contract::factory()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'status' => Contract::STATUS_ACTIVE,
        ], $attributes));
    }

    private function payment(Contract $contract, int $amount, string $status): void
    {
        ContractPayment::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Đợt',
            'amount' => $amount,
            'due_date' => '2026-10-20',
            'status' => $status,
            'paid_at' => $status === ContractPayment::STATUS_PAID ? '2026-10-21' : null,
        ]);
    }
}
