<?php

declare(strict_types=1);

namespace Tests\Feature\TimeTracking;

use App\Models\AuditLog;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\TimeTracking\TimeEntry;
use App\Models\User;
use App\Services\TimeTracking\TimeEntryService;
use App\Services\TimeTracking\TimeReviewService;
use App\Services\TimeTracking\TimeRuleViolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-112 (Gate 2 R-1, L-1) — weekly review: project entries by the project PM or project.update holders, non-project
 * entries by payroll.manage holders, no self-approval (except payroll.close), rejection with a reason sends the entry
 * back for correction, closed payroll months are locked, audit.
 */
class TimeReviewTest extends TestCase
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

    public function test_scope_approve_reject_and_resubmit(): void
    {
        $entries = app(TimeEntryService::class);
        $review = app(TimeReviewService::class);
        $project = $entries->addManual($this->worker, ['task_id' => (string) $this->task->id, 'work_date' => '2026-11-03', 'minutes' => '240', 'note' => 'Vẽ']);
        $meeting = $entries->addManual($this->worker, ['category' => TimeEntry::CATEGORY_MEETING, 'work_date' => '2026-11-03', 'minutes' => '60', 'note' => 'Họp']);
        $pmOwn = $entries->addManual($this->pm, ['task_id' => (string) $this->task->id, 'work_date' => '2026-11-03', 'minutes' => '120', 'note' => 'Kiểm tra']);

        $week = Carbon::parse('2026-11-02');
        $this->assertEqualsCanonicalizing([(string) $project->id], $review->queue($this->pm, $week)->pluck('id')->map(fn ($id) => (string) $id)->all());
        $this->assertEqualsCanonicalizing([(string) $meeting->id], $review->queue($this->accountant, $week)->pluck('id')->map(fn ($id) => (string) $id)->all());
        $this->assertEqualsCanonicalizing([(string) $project->id, (string) $pmOwn->id], $review->queue($this->editor, $week)->pluck('id')->map(fn ($id) => (string) $id)->all());
        $this->assertCount(0, $review->queue($this->worker, $week));

        $this->assertRule(fn () => $review->approve($this->worker, [(string) $project->id]), 'quyền duyệt');
        $this->assertRule(fn () => $review->approve($this->pm, [(string) $pmOwn->id]), 'quyền duyệt');
        $this->assertRule(fn () => $review->approve($this->accountant, [(string) $project->id]), 'quyền duyệt');
        $review->approve($this->pm, [(string) $project->id]);
        $this->assertSame(TimeEntry::STATUS_APPROVED, $project->fresh()->status);
        $this->assertSame((string) $this->pm->id, $project->fresh()->reviewed_by);
        $this->assertRule(fn () => $review->approve($this->pm, [(string) $project->id]), 'chờ duyệt');
        $review->approve($this->editor, [(string) $pmOwn->id]);

        $this->assertRule(fn () => $review->reject($this->accountant, [(string) $meeting->id], ' '), 'lý do');
        $review->reject($this->accountant, [(string) $meeting->id], 'Họp không có trong lịch');
        $this->assertSame(TimeEntry::STATUS_REJECTED, $meeting->fresh()->status);
        $this->assertSame('Họp không có trong lịch', $meeting->fresh()->review_note);
        // The person corrects a rejected entry; it goes back to review.
        $entries->update($this->worker, $meeting->fresh(), ['category' => TimeEntry::CATEGORY_MEETING, 'work_date' => '2026-11-03', 'minutes' => '30', 'note' => 'Họp nhanh với PM']);
        $this->assertSame(TimeEntry::STATUS_SUBMITTED, $meeting->fresh()->status);

        // Owner (payroll.close) may approve own entries.
        $ownerEntry = $entries->addManual($this->owner, ['category' => TimeEntry::CATEGORY_ADMIN, 'work_date' => '2026-11-03', 'minutes' => '60', 'note' => 'Ký hồ sơ']);
        $review->approve($this->owner, [(string) $ownerEntry->id]);
        $this->assertSame(TimeEntry::STATUS_APPROVED, $ownerEntry->fresh()->status);

        $this->assertSame(3, AuditLog::query()->where('action', 'time.entry.approved')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'time.entry.rejected')->count());
    }

    public function test_closed_payroll_month_locks_review(): void
    {
        $entries = app(TimeEntryService::class);
        $entry = $entries->addManual($this->worker, ['task_id' => (string) $this->task->id, 'work_date' => '2026-10-30', 'minutes' => '60', 'note' => 'Cuối tháng']);
        $this->closeMonth('2026-10');
        $this->assertRule(fn () => app(TimeReviewService::class)->approve($this->pm, [(string) $entry->id]), 'đã chốt lương');
        $this->assertRule(fn () => $entries->delete($this->worker, $entry->fresh()), 'đã chốt lương');
    }

    public function test_review_page(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $entry = app(TimeEntryService::class)->addManual($this->worker, ['task_id' => (string) $this->task->id, 'work_date' => '2026-11-03', 'minutes' => '90', 'note' => 'Vẽ']);
        $this->actingAs($this->pm)->get(route('app.time.review', ['week' => '2026-11-02']), $headers)->assertOk()
            ->assertSee('Kỹ sư A')->assertSee('Vẽ mặt bằng')->assertSee('1:30')->assertSee('time-review-form', false);
        $this->actingAs($this->pm)->post(route('app.time.review.act'), ['ids' => [(string) $entry->id], 'action' => 'reject', 'reason' => ''], $headers)->assertSessionHasErrors();
        $this->actingAs($this->pm)->post(route('app.time.review.act'), ['ids' => [(string) $entry->id], 'action' => 'approve', 'week' => '2026-11-02'], $headers)->assertRedirect();
        $this->assertSame(TimeEntry::STATUS_APPROVED, $entry->fresh()->status);
        $this->actingAs($this->worker)->get(route('app.time.review'), $headers)->assertForbidden();
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
