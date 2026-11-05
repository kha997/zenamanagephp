<?php

declare(strict_types=1);

namespace Tests\Feature\TimeTracking;

use App\Models\AuditLog;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\TimeTracking\TimeEntry;
use App\Models\User;
use App\Models\UserRoleProject;
use App\Services\TimeTracking\TimeEntryService;
use App\Services\TimeTracking\TimeRuleViolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-112 (Gate 2 T-1, M-1, C-1, L-1, P-1) — time entries: one running timer per person, auto-stop at the earlier of
 * start + 10 h and 23:59 (flagged), manual entries with a reason, no overlap, 24 h per day, fixed non-project
 * categories, edit / delete before approval, scope (assigned task or project member / PM), payroll-month lock, audit.
 */
class TimeEntryTest extends TestCase
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

    public function test_timer_start_stop_switch_and_auto_stop(): void
    {
        $service = app(TimeEntryService::class);
        $running = $service->start($this->worker, $this->task);
        $this->assertSame(TimeEntry::STATUS_RUNNING, $running->status);
        $this->assertSame((string) $this->project->id, $running->project_id);

        Carbon::setTestNow('2026-11-05 10:30:00');
        // Starting another activity stops the running one.
        $service->start($this->worker, null, TimeEntry::CATEGORY_MEETING);
        $first = $running->fresh();
        $this->assertSame(TimeEntry::STATUS_SUBMITTED, $first->status);
        $this->assertSame(90, $first->minutes);
        $this->assertSame(1, TimeEntry::query()->where('user_id', (string) $this->worker->id)->where('status', TimeEntry::STATUS_RUNNING)->count());

        Carbon::setTestNow('2026-11-05 11:00:00');
        $stopped = $service->stop($this->worker);
        $this->assertSame(30, $stopped?->minutes);
        $this->assertNull($service->stop($this->worker));

        // Forgotten timer: started 14:00, seen next morning -> stopped at 23:59 (earlier than 14:00 + 10 h), flagged.
        Carbon::setTestNow('2026-11-05 14:00:00');
        $forgotten = $service->start($this->worker, $this->task);
        Carbon::setTestNow('2026-11-06 08:00:00');
        $this->assertNull($service->running($this->worker));
        $forgotten = $forgotten->fresh();
        $this->assertTrue($forgotten->auto_stopped);
        $this->assertSame('2026-11-05 23:59:00', $forgotten->ended_at?->format('Y-m-d H:i:s'));
        $this->assertSame(599, $forgotten->minutes);

        // Same-day overrun: started 06:00, stopped 17:30 -> capped at 16:00 (10 h), flagged.
        Carbon::setTestNow('2026-11-06 06:00:00');
        $long = $service->start($this->worker, $this->task);
        Carbon::setTestNow('2026-11-06 17:30:00');
        $service->stop($this->worker);
        $this->assertSame(600, $long->fresh()->minutes);
        $this->assertTrue($long->fresh()->auto_stopped);
        $this->assertGreaterThanOrEqual(4, AuditLog::query()->where('action', 'like', 'time.entry.%')->count());
    }

    public function test_manual_entries_rules_and_categories(): void
    {
        $service = app(TimeEntryService::class);
        $this->assertRule(fn () => $service->addManual($this->worker, ['task_id' => (string) $this->task->id, 'work_date' => '2026-11-04', 'start_time' => '08:00', 'end_time' => '12:00']), 'lý do');
        $entry = $service->addManual($this->worker, ['task_id' => (string) $this->task->id, 'work_date' => '2026-11-04', 'start_time' => '08:00', 'end_time' => '12:00', 'note' => 'Quên bấm giờ']);
        $this->assertSame(240, $entry->minutes);
        $this->assertSame(TimeEntry::SOURCE_MANUAL, $entry->source);
        $this->assertSame(TimeEntry::STATUS_SUBMITTED, $entry->status);

        $this->assertRule(fn () => $service->addManual($this->worker, ['task_id' => (string) $this->task->id, 'work_date' => '2026-11-04', 'start_time' => '11:00', 'end_time' => '13:00', 'note' => 'x']), 'chồng');
        $this->assertRule(fn () => $service->addManual($this->worker, ['task_id' => (string) $this->task->id, 'work_date' => '2026-11-04', 'start_time' => '13:00', 'end_time' => '12:00', 'note' => 'x']), 'kết thúc');
        $this->assertRule(fn () => $service->addManual($this->worker, ['category' => TimeEntry::CATEGORY_TRAINING, 'work_date' => '2026-11-04', 'minutes' => '1300', 'note' => 'x']), '24 giờ');
        $this->assertRule(fn () => $service->addManual($this->worker, ['category' => TimeEntry::CATEGORY_OTHER, 'work_date' => '2026-11-04', 'minutes' => '30', 'note' => ' ']), 'lý do');
        $this->assertRule(fn () => $service->addManual($this->worker, ['category' => 'party', 'work_date' => '2026-11-04', 'minutes' => '30', 'note' => 'x']), 'Loại việc');
        $this->assertRule(fn () => $service->addManual($this->worker, ['work_date' => '2026-11-04', 'minutes' => '30', 'note' => 'x']), 'công việc hoặc loại việc');
        $this->assertRule(fn () => $service->addManual($this->worker, ['category' => TimeEntry::CATEGORY_ADMIN, 'work_date' => '2026-11-07', 'minutes' => '30', 'note' => 'x']), 'tương lai');

        $admin = $service->addManual($this->worker, ['category' => TimeEntry::CATEGORY_ADMIN, 'work_date' => '2026-11-04', 'minutes' => '45', 'note' => 'Nộp hồ sơ']);
        $this->assertNull($admin->project_id);

        // Edit / delete own entries before approval.
        $service->update($this->worker, $entry, ['task_id' => (string) $this->task->id, 'work_date' => '2026-11-04', 'start_time' => '08:00', 'end_time' => '11:00', 'note' => 'Sửa giờ']);
        $this->assertSame(180, $entry->fresh()->minutes);
        $this->assertRule(fn () => $service->update($this->pm, $entry->fresh(), ['note' => 'x']), 'của mình');
        $entry->forceFill(['status' => TimeEntry::STATUS_APPROVED])->save();
        $this->assertRule(fn () => $service->update($this->worker, $entry->fresh(), ['task_id' => (string) $this->task->id, 'work_date' => '2026-11-04', 'minutes' => '60', 'note' => 'x']), 'đã duyệt');
        $this->assertRule(fn () => $service->delete($this->worker, $entry->fresh()), 'đã duyệt');
        $service->delete($this->worker, $admin);
        $this->assertNull(TimeEntry::query()->find($admin->id));
    }

    public function test_scope_lock_and_isolation(): void
    {
        $service = app(TimeEntryService::class);
        $foreignTask = $this->makeTask($this->otherProject, ['name' => 'Việc biệt thự']);
        $this->assertRule(fn () => $service->start($this->worker, $foreignTask), 'không được ghi giờ');

        // Member of the other project may log there; the PM may log on own project tasks.
        $role = Role::factory()->create(['name' => 'project-member-time']);
        UserRoleProject::query()->create(['project_id' => (string) $this->otherProject->id, 'user_id' => (string) $this->worker->id, 'role_id' => (string) $role->id]);
        $this->assertSame((string) $this->otherProject->id, $service->start($this->worker, $foreignTask)->project_id);
        $service->stop($this->worker);
        $unassigned = $this->makeTask($this->project, ['name' => 'Việc chưa giao']);
        $this->assertNotNull($service->start($this->pm, $unassigned));
        $service->stop($this->pm);

        $otherTenant = Tenant::factory()->create();
        $strangerProject = Project::factory()->create(['tenant_id' => (string) $otherTenant->id]);
        $strangerTask = $this->makeTask($strangerProject, ['assigned_to' => (string) $this->worker->id]);
        $this->assertRule(fn () => $service->start($this->worker, $strangerTask), 'không được ghi giờ');

        $this->closeMonth('2026-10');
        $this->assertRule(fn () => $service->addManual($this->worker, ['task_id' => (string) $this->task->id, 'work_date' => '2026-10-30', 'minutes' => '60', 'note' => 'Bổ sung']), 'đã chốt lương');
        $this->assertNotNull($service->addManual($this->worker, ['task_id' => (string) $this->task->id, 'work_date' => '2026-11-03', 'minutes' => '60', 'note' => 'Bổ sung']));
    }

    public function test_timer_respects_overlap_daily_limit_and_closed_month(): void
    {
        $service = app(TimeEntryService::class);
        // A later manual interval clips the timer at its start.
        $service->addManual($this->worker, ['task_id' => (string) $this->task->id, 'work_date' => '2026-11-05', 'start_time' => '10:00', 'end_time' => '11:00', 'note' => 'Họp khách']);
        Carbon::setTestNow('2026-11-05 09:30:00');
        $timer = $service->start($this->worker, $this->task);
        Carbon::setTestNow('2026-11-05 12:00:00');
        $service->stop($this->worker);
        $this->assertSame(30, $timer->fresh()->minutes);
        $this->assertSame('2026-11-05 10:00:00', $timer->fresh()->ended_at?->format('Y-m-d H:i:s'));
        $this->assertTrue($timer->fresh()->auto_stopped);

        // The day already holds 24 h: a timer adds nothing.
        $service->addManual($this->worker, ['category' => TimeEntry::CATEGORY_ADMIN, 'work_date' => '2026-11-04', 'minutes' => '1440', 'note' => 'Cả ngày']);
        Carbon::setTestNow('2026-11-04 13:00:00');
        $full = $service->start($this->worker, $this->task);
        Carbon::setTestNow('2026-11-04 15:00:00');
        $service->stop($this->worker);
        $this->assertSame(0, $full->fresh()->minutes);
        $this->assertSame(1440, (int) TimeEntry::query()->whereDate('work_date', '2026-11-04')->sum('minutes'));

        // A timer left running into a month that is then closed is voided, never counted, and does not block new work.
        Carbon::setTestNow('2026-10-31 22:00:00');
        $late = $service->start($this->worker, $this->task);
        $this->closeMonth('2026-10');
        Carbon::setTestNow('2026-11-05 13:00:00');
        $this->assertNull($service->running($this->worker));
        $late = $late->fresh();
        $this->assertSame(TimeEntry::STATUS_REJECTED, $late->status);
        $this->assertSame(0, $late->minutes);
        $this->assertSame(1, AuditLog::query()->where('action', 'time.entry.voided_closed_month')->count());
        $this->assertSame(TimeEntry::STATUS_RUNNING, $service->start($this->worker, $this->task)->status);
    }

    public function test_task_pickers_offer_the_whole_permitted_scope(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $unassigned = $this->makeTask($this->project, ['name' => 'Việc PM chưa giao']);
        $done = $this->makeTask($this->project, ['name' => 'Việc đã xong', 'assigned_to' => (string) $this->worker->id, 'status' => Task::STATUS_COMPLETED]);
        $foreign = $this->makeTask($this->otherProject, ['name' => 'Việc dự án khác']);

        $this->actingAs($this->pm)->get(route('app.time.index'), $headers)->assertOk()->assertSee('Việc PM chưa giao')->assertDontSee('Việc dự án khác');
        $this->actingAs($this->worker)->get(route('app.time.index'), $headers)->assertOk()->assertSee('Việc đã xong')->assertDontSee('Việc PM chưa giao');
        $this->assertNotNull($unassigned->id);
        $this->assertNotNull($done->id);
        $this->assertNotNull($foreign->id);
    }

    public function test_pages_timer_buttons_and_permissions(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $this->actingAs($this->worker)->get(route('app.my-work.index'), $headers)->assertOk()
            ->assertSee('Vẽ mặt bằng')->assertSee('time-start-form', false);
        $this->actingAs($this->worker)->post(route('app.time.start'), ['task_id' => (string) $this->task->id], $headers)->assertRedirect();
        $this->actingAs($this->worker)->get(route('app.my-work.index'), $headers)->assertOk()->assertSee('Đang ghi giờ')->assertSee('time-stop-form', false);
        Carbon::setTestNow('2026-11-05 10:00:00');
        $this->actingAs($this->worker)->post(route('app.time.stop'), [], $headers)->assertRedirect();
        $this->assertSame(60, TimeEntry::query()->sole()->minutes);

        $this->actingAs($this->worker)->post(route('app.time.entries.store'), [
            'category' => TimeEntry::CATEGORY_MEETING, 'work_date' => '2026-11-04', 'minutes' => '30', 'note' => 'Họp tuần',
        ], $headers)->assertRedirect();
        $this->actingAs($this->worker)->post(route('app.time.entries.store'), [
            'category' => TimeEntry::CATEGORY_MEETING, 'work_date' => '2026-11-04', 'minutes' => '30', 'note' => '',
        ], $headers)->assertSessionHasErrors();
        $this->actingAs($this->worker)->get(route('app.time.index', ['week' => '2026-11-02']), $headers)->assertOk()
            ->assertSee('Bảng giờ của tôi')->assertSee('Vẽ mặt bằng')->assertSee('Họp nội bộ')->assertSee('1:30')
            ->assertSee('time-manual-form', false)->assertDontSee('time-review-link', false);
        $this->actingAs($this->pm)->get(route('app.time.index'), $headers)->assertOk()->assertSee('time-review-link', false);

        // Another person's entry cannot be edited or deleted through the web.
        $entry = TimeEntry::query()->where('category', TimeEntry::CATEGORY_MEETING)->sole();
        $this->actingAs($this->pm)->post(route('app.time.entries.delete', $entry->id), [], $headers)->assertRedirect();
        $this->assertNotNull($entry->fresh());
        $otherTenant = Tenant::factory()->create();
        $stranger = $this->createTenantUser($otherTenant, [], ['time_stranger'], ['task.view']);
        $this->actingAs($stranger)->post(route('app.time.entries.delete', $entry->id), [], ['X-Tenant-ID' => (string) $otherTenant->id])->assertNotFound();
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
