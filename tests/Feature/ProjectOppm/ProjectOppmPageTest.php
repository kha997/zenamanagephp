<?php

declare(strict_types=1);

namespace Tests\Feature\ProjectOppm;

use App\Models\Component;
use App\Models\Contract;
use App\Models\DesignItem;
use App\Models\Project;
use App\Models\ProjectServiceLine;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryDashboardService;
use App\Support\ServiceLine;
use App\Support\ServiceLineProvenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-104 — read-only one-page OPPM per project (Gate 2 V-1, W-A, T-1, R-1): consumes the GAP-103 health model, root
 * components as work groups, bounded attention lists, compact finance without profit, print layout, project.view gate.
 */
class ProjectOppmPageTest extends TestCase
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
        $this->user = $this->createTenantUser($this->tenant, [], ['member'], ['project.view']);
        $this->project = $this->projectFor($this->tenant, ['name' => 'Nhà phố Quận 7', 'code' => 'P-OPPM']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_requires_project_view_and_same_tenant(): void
    {
        $this->open()->assertOk()->assertSee('Nhà phố Quận 7')->assertSee('P-OPPM');

        $noPermission = $this->createTenantUser($this->tenant, [], ['oppm_no_access'], []);
        // The rbac middleware denies web requests by redirecting back with an error (existing behavior).
        $this->open($noPermission)->assertRedirect()->assertSessionHas('error')->assertDontSee('P-OPPM');

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->projectFor($otherTenant);
        $this->actingAs($this->user)
            ->get(route('app.projects.oppm', (string) $foreign->id), ['X-Tenant-ID' => (string) $this->tenant->id])
            ->assertNotFound();
    }

    public function test_empty_project_shows_no_data_states_not_zero(): void
    {
        $this->open()
            ->assertOk()
            ->assertSee('Chưa phân loại')
            ->assertSee('Chưa có nhóm công việc')
            ->assertSeeInOrder(['Tiến độ', 'Chưa có dữ liệu'])
            ->assertSeeInOrder(['Mốc', 'Chưa có dữ liệu'])
            ->assertViewHas('oppm', fn (array $oppm): bool => $oppm['health']['progress']['availability'] === 'no_data');
    }

    public function test_header_lists_service_lines_with_unconfirmed_marker(): void
    {
        ProjectServiceLine::query()->create([
            'project_id' => (string) $this->project->id,
            'service_line' => ServiceLine::DESIGN,
            'provenance' => ServiceLineProvenance::CONFIRMED,
        ]);
        ProjectServiceLine::query()->create([
            'project_id' => (string) $this->project->id,
            'service_line' => ServiceLine::CONSTRUCTION,
            'provenance' => ServiceLineProvenance::INFERRED,
        ]);

        $this->open()
            ->assertOk()
            ->assertSee('Thiết kế')
            ->assertSee('Thi công (chưa xác nhận)')
            ->assertDontSee('Chưa phân loại');
    }

    public function test_work_groups_are_root_components_with_rolled_up_task_counts(): void
    {
        $later = $this->makeComponent(['name' => 'Phần thân', 'start_date' => '2026-05-01', 'end_date' => '2026-08-31', 'progress_percent' => 20, 'planned_cost' => 300]);
        $first = $this->makeComponent(['name' => 'Phần móng', 'start_date' => '2026-02-01', 'end_date' => '2026-04-30', 'progress_percent' => 100, 'planned_cost' => 100]);
        $child = $this->makeComponent(['name' => 'Cọc khoan nhồi', 'parent_component_id' => (string) $first->id]);

        $this->task(['component_id' => (string) $child->id, 'blocked_at' => now()]);
        $this->task(['component_id' => (string) $first->id, 'end_date' => '2026-10-01']);
        $this->task(['component_id' => (string) $later->id]);

        $response = $this->open()->assertOk()->assertSeeInOrder(['Phần móng', 'Phần thân'])->assertDontSee('Cọc khoan nhồi');

        $groups = $response->viewData('oppm')['workstreams'];
        $this->assertCount(2, $groups);
        $this->assertSame('Phần móng', $groups[0]['name']);
        $this->assertSame(1, $groups[0]['blocked_tasks']);
        $this->assertSame(1, $groups[0]['overdue_tasks']);
        $this->assertSame(0, $groups[1]['blocked_tasks']);
        $this->assertSame(['offset' => 8.5, 'width' => 24.4], $groups[0]['timeline']);
    }

    public function test_work_groups_are_capped_at_twelve(): void
    {
        foreach (range(1, 14) as $i) {
            $this->makeComponent(['name' => sprintf('Hạng mục %02d', $i), 'start_date' => sprintf('2026-01-%02d', $i)]);
        }

        $oppm = $this->open()->assertOk()->viewData('oppm');

        $this->assertCount(12, $oppm['workstreams']);
        $this->assertSame(14, $oppm['workstream_total']);
    }

    public function test_without_components_shows_task_status_summary(): void
    {
        $this->task(['status' => Task::STATUS_IN_PROGRESS]);
        $this->task(['status' => Task::STATUS_IN_PROGRESS]);
        $this->task(['status' => Task::STATUS_COMPLETED]);

        $oppm = $this->open()->assertOk()->assertSee('Chưa có nhóm công việc')->viewData('oppm');

        $this->assertSame(2, $oppm['task_summary'][Task::STATUS_IN_PROGRESS]);
        $this->assertSame(1, $oppm['task_summary'][Task::STATUS_COMPLETED]);
    }

    public function test_attention_lists_are_capped_at_ten_with_totals(): void
    {
        $blocker = $this->createTenantUser($this->tenant, ['name' => 'Kỹ sư Bình'], ['site_engineer'], []);
        foreach (range(1, 12) as $i) {
            $this->task(['name' => "Việc chặn {$i}", 'blocked_at' => now(), 'blocker_note' => 'Chờ bản vẽ', 'blocked_by' => (string) $blocker->id]);
        }
        $this->task(['name' => 'Việc trễ', 'end_date' => '2026-10-01']);
        DesignItem::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'created_by' => (string) $this->user->id,
            'name' => 'Mặt bằng tầng 1',
            'blocked_at' => now(),
        ]);

        $response = $this->open()->assertOk()->assertSee('Chờ bản vẽ')->assertSee('Kỹ sư Bình')->assertSee('Việc trễ')->assertSee('Mặt bằng tầng 1');
        $oppm = $response->viewData('oppm');

        $this->assertCount(10, $oppm['blocked_tasks']['rows']);
        $this->assertSame(12, $oppm['blocked_tasks']['total']);
        $this->assertSame(1, $oppm['overdue_tasks']['total']);
        $this->assertSame(1, $oppm['blocked_design_items']['total']);
    }

    public function test_finance_block_has_no_profit_and_labels_cost_as_not_cash(): void
    {
        $this->project->update(['budget_total' => 1000]);
        Contract::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'status' => Contract::STATUS_ACTIVE,
            'total_value' => 900,
        ]);

        $this->open()
            ->assertOk()
            ->assertSee('không phải tiền đã chi')
            ->assertDontSee('Lãi')
            ->assertDontSee('Lợi nhuận')
            ->assertDontSee('Ngân quỹ dự án');
    }

    public function test_treasury_block_needs_treasury_permission_and_never_builds_the_dashboard(): void
    {
        TreasuryWallet::factory()->create(['project_id' => (string) $this->project->id]);
        $this->mock(TreasuryDashboardService::class, function ($mock): void {
            $mock->shouldNotReceive('dashboard');
        });

        $this->open()->assertOk()->assertDontSee('Ngân quỹ dự án');

        $treasuryUser = $this->createTenantUser($this->tenant, [], ['treasury_viewer'], ['project.view', 'treasury.view', 'treasury.all_projects']);
        $this->open($treasuryUser)->assertOk()->assertSee('Ngân quỹ dự án')->assertSee('Xem Ngân quỹ');
    }

    public function test_page_has_print_layout_and_project_page_links_to_it(): void
    {
        $this->open()->assertOk()->assertSee('@page', false)->assertSee('landscape', false);

        $this->actingAs($this->user)
            ->get(route('app.projects.show', (string) $this->project->id), ['X-Tenant-ID' => (string) $this->tenant->id])
            ->assertOk()
            ->assertSee(route('app.projects.oppm', (string) $this->project->id), false)
            ->assertSee('OPPM một trang');
    }

    public function test_other_project_data_is_ignored(): void
    {
        $sibling = $this->projectFor($this->tenant);
        Component::factory()->create(['tenant_id' => (string) $this->tenant->id, 'project_id' => (string) $sibling->id, 'name' => 'Hạng mục dự án khác']);
        Task::factory()->create(['tenant_id' => (string) $this->tenant->id, 'project_id' => (string) $sibling->id, 'title' => 'Việc dự án khác', 'blocked_at' => now()]);
        $this->task(['name' => 'Việc dự án này', 'blocked_at' => now()]);

        $this->open()->assertOk()->assertSee('Việc dự án này')->assertDontSee('Hạng mục dự án khác')->assertDontSee('Việc dự án khác');
    }

    public function test_query_count_does_not_grow_with_project_size(): void
    {
        $root = $this->makeComponent(['name' => 'Gốc']);
        $this->task(['component_id' => (string) $root->id]);
        $few = $this->countQueries();

        foreach (range(1, 20) as $i) {
            $component = $this->makeComponent(['name' => "Gốc {$i}", 'parent_component_id' => $i % 2 === 0 ? (string) $root->id : null]);
            foreach (range(1, 3) as $j) {
                $this->task(['component_id' => (string) $component->id, 'blocked_at' => $j === 1 ? now() : null, 'end_date' => '2026-10-01']);
            }
        }
        $many = $this->countQueries();

        $this->assertSame($few, $many);
    }

    private function open(?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->user)
            ->get(route('app.projects.oppm', (string) $this->project->id), ['X-Tenant-ID' => (string) $this->tenant->id]);
    }

    private function countQueries(): int
    {
        $this->open()->assertOk();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->open()->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function projectFor(Tenant $tenant, array $attributes = []): Project
    {
        return Project::factory()->create(array_merge([
            'tenant_id' => (string) $tenant->id,
            'status' => 'active',
            'budget_total' => 0,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function makeComponent(array $attributes): Component
    {
        return Component::factory()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'parent_component_id' => null,
            'progress_percent' => 0,
            'planned_cost' => 0,
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function task(array $attributes): Task
    {
        if (isset($attributes['name'])) {
            // Task::setTitleAttribute copies title into name, so the factory's title would overwrite a given name.
            $attributes['title'] = $attributes['name'];
        }

        return Task::factory()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'status' => Task::STATUS_IN_PROGRESS,
            'progress_percent' => 0,
            'blocked_at' => null,
            'end_date' => null,
            'component_id' => null,
        ], $attributes));
    }
}
