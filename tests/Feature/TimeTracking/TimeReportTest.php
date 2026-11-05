<?php

declare(strict_types=1);

namespace Tests\Feature\TimeTracking;

use App\Models\Payroll\PayrollPeriod;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\TimeTracking\TimeEntry;
use App\Models\User;
use App\Services\TimeTracking\TimeEntryService;
use App\Services\TimeTracking\TimeReportService;
use App\Services\TimeTracking\TimeReviewService;
use App\Services\TimeTracking\TimeRuleViolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-112 (Gate 2 P-1) — hours reports: by project × person and by person × project / category, approved vs pending,
 * scoped (payroll.manage: whole company; project.update: all projects; PM: own projects), CSV, fixed query count.
 */
class TimeReportTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;

    private Project $project;

    private Project $otherProject;

    private User $worker;

    private User $pm;

    private User $editor;

    private User $accountant;

    private User $owner;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 09:00:00');
        $this->tenant = Tenant::factory()->create();
        $this->worker = $this->createTenantUser($this->tenant, ['name' => 'Kỹ sư A'], ['time_worker'], ['task.view']);
        $this->pm = $this->createTenantUser($this->tenant, ['name' => 'PM B'], ['time_pm'], ['task.view']);
        $this->editor = $this->createTenantUser($this->tenant, ['name' => 'Giám đốc dự án'], ['time_editor'], ['task.view', 'project.update']);
        $this->accountant = $this->createTenantUser($this->tenant, ['name' => 'Kế toán'], ['time_accountant'], ['task.view', 'payroll.manage']);
        $this->owner = $this->createTenantUser($this->tenant, ['name' => 'Chủ'], ['time_owner'], ['task.view', 'payroll.manage', 'payroll.close', 'project.update']);
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'name' => 'Nhà phố Q7', 'pm_id' => (string) $this->pm->id]);
        $this->otherProject = Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'name' => 'Biệt thự Thảo Điền']);
        $this->task = $this->makeTask($this->project, ['name' => 'Vẽ mặt bằng', 'assigned_to' => (string) $this->worker->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_totals_scope_and_csv(): void
    {
        $entries = app(TimeEntryService::class);
        $approved = $entries->addManual($this->worker, ['task_id' => (string) $this->task->id, 'work_date' => '2026-11-03', 'minutes' => '240', 'note' => 'Vẽ']);
        $entries->addManual($this->worker, ['task_id' => (string) $this->task->id, 'work_date' => '2026-11-04', 'minutes' => '60', 'note' => 'Vẽ tiếp']);
        $entries->addManual($this->worker, ['category' => TimeEntry::CATEGORY_TRAINING, 'work_date' => '2026-11-04', 'minutes' => '120', 'note' => 'Học BIM']);
        $otherTask = $this->makeTask($this->otherProject, ['name' => 'Khảo sát', 'assigned_to' => (string) $this->pm->id]);
        $entries->addManual($this->pm, ['task_id' => (string) $otherTask->id, 'work_date' => '2026-11-04', 'minutes' => '180', 'note' => 'Khảo sát']);
        app(TimeReviewService::class)->approve($this->pm, [(string) $approved->id]);

        $reports = app(TimeReportService::class);
        $from = Carbon::parse('2026-11-01');
        $to = Carbon::parse('2026-11-30');

        $all = $reports->byProject($this->accountant, $from, $to);
        $row = collect($all['rows'])->first(fn ($r) => $r['project'] === 'Nhà phố Q7' && $r['person'] === 'Kỹ sư A');
        $this->assertSame(240, $row['approved_minutes']);
        $this->assertSame(60, $row['pending_minutes']);
        $this->assertTrue(collect($all['rows'])->contains(fn ($r) => $r['project'] === null && $r['category'] === TimeEntry::CATEGORY_TRAINING && $r['pending_minutes'] === 120));
        $this->assertSame(600, $all['total_minutes']);

        // PM sees only own project; project.update sees all projects but no non-project rows.
        $pmRows = $reports->byProject($this->pm, $from, $to)['rows'];
        $this->assertSame(['Nhà phố Q7'], array_values(array_unique(array_column($pmRows, 'project'))));
        $editorRows = $reports->byProject($this->editor, $from, $to)['rows'];
        $this->assertEqualsCanonicalizing(['Nhà phố Q7', 'Biệt thự Thảo Điền'], array_values(array_unique(array_column($editorRows, 'project'))));
        $this->assertFalse($reports->canView($this->worker));

        $person = $reports->byPerson($this->accountant, $from, $to);
        $worker = collect($person['rows'])->where('person', 'Kỹ sư A');
        $this->assertSame(420, $worker->sum(fn ($r) => $r['approved_minutes'] + $r['pending_minutes']));

        $csv = $reports->csv($this->accountant, $from, $to);
        $this->assertStringContainsString('Nhà phố Q7', $csv);
        $this->assertStringContainsString('Đào tạo', $csv);
    }

    public function test_report_page_permissions_and_query_count(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $entries = app(TimeEntryService::class);
        $entries->addManual($this->worker, ['task_id' => (string) $this->task->id, 'work_date' => '2026-11-03', 'minutes' => '60', 'note' => 'Vẽ']);
        $url = route('app.time.report', ['from' => '2026-11-01', 'to' => '2026-11-30']);
        $this->actingAs($this->accountant)->get($url, $headers)->assertOk()->assertSee('Báo cáo giờ')->assertSee('Nhà phố Q7')->assertSee('Kỹ sư A');
        $this->actingAs($this->worker)->get($url, $headers)->assertForbidden();
        $csv = $this->actingAs($this->accountant)->get($url . '&export=csv', $headers);
        $csv->assertOk();
        $this->assertStringContainsString('Kỹ sư A', $csv->streamedContent());

        $count = function () use ($url, $headers): int {
            $this->actingAs($this->accountant)->get($url, $headers)->assertOk();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->accountant)->get($url, $headers)->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };
        $few = $count();
        foreach (range(1, 6) as $i) {
            $task = $this->makeTask($this->project, ['name' => "Việc {$i}", 'assigned_to' => (string) $this->worker->id]);
            $entries->addManual($this->worker, ['task_id' => (string) $task->id, 'work_date' => '2026-11-0' . (($i % 4) + 1), 'minutes' => '15', 'note' => 'x']);
        }
        $this->assertSame($few, $count());
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function makeTask(Project $project, array $attributes = []): Task
    {
        if (isset($attributes['name'])) {
            $attributes['title'] = $attributes['name'];
        }

        return Task::factory()->create(array_merge([
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            'status' => Task::STATUS_IN_PROGRESS,
            'assigned_to' => null,
            'end_date' => null,
            'blocked_at' => null,
            'component_id' => null,
        ], $attributes));
    }

    private function closeMonth(string $period): void
    {
        PayrollPeriod::query()->create([
            'tenant_id' => (string) $this->tenant->id, 'period' => $period, 'working_days' => 26, 'hours_per_day' => '8',
            'status' => PayrollPeriod::STATUS_CLOSED, 'closed_at' => now(),
        ]);
    }

    private function assertRule(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail('Expected a TimeRuleViolation.');
        } catch (TimeRuleViolation $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }
}
