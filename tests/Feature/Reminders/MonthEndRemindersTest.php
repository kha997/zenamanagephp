<?php

declare(strict_types=1);

namespace Tests\Feature\Reminders;

use App\Models\Notification;
use App\Models\Payroll\OverheadPeriod;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\StaffMember;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\TimeTracking\TimeEntry;
use App\Models\User;
use App\Services\Reminders\MonthEndReminderService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-120 (Gate 2 W-1, T-1, C-1, R-1) — month-end reminders as in-app notifications.
 *
 * - (a) Submitted time waiting for review: to the project PM; own-PM entries and PM-less projects go to payroll.close
 *   holders; non-project entries to payroll.manage holders. Only from three days before month end to day 5 of the next
 *   month.
 * - (b) Previous month payroll not closed / (c) payroll closed but overhead not: to payroll.close holders, from day 1
 *   until closed, only for tenants that use payroll (have staff records).
 * - At most one per person, kind and month per day (event_key); tenant-isolated; reminders:month-end --dry-run writes
 *   nothing; scheduled daily at 08:00 Asia/Ho_Chi_Minh.
 */
class MonthEndRemindersTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;

    private User $pm;

    private User $worker;

    private User $accountant;

    private User $owner;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->pm = $this->createTenantUser($this->tenant, ['name' => 'PM An'], ['me_pm'], ['task.view']);
        $this->worker = $this->createTenantUser($this->tenant, ['name' => 'Kỹ sư Bình'], ['me_worker'], ['task.view']);
        $this->accountant = $this->createTenantUser($this->tenant, ['name' => 'Kế toán Chi'], ['me_accountant'], ['task.view', 'payroll.manage']);
        $this->owner = $this->createTenantUser($this->tenant, ['name' => 'Chủ Dũng'], ['me_owner'], ['task.view', 'payroll.manage', 'payroll.close']);
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'name' => 'Nhà phố Q7', 'pm_id' => (string) $this->pm->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_time_review_reminders_go_to_the_right_reviewers(): void
    {
        $this->entry($this->worker, $this->project, '2026-11-20');
        $this->entry($this->worker, $this->project, '2026-11-24');
        $this->entry($this->pm, $this->project, '2026-11-25');            // PM's own → payroll.close
        $noPm = Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'pm_id' => null]);
        $this->entry($this->worker, $noPm, '2026-11-26');                 // no PM → payroll.close
        $this->entry($this->worker, null, '2026-11-27');                   // non-project → payroll.manage
        $this->entry($this->worker, $this->project, '2026-11-21', TimeEntry::STATUS_APPROVED); // not waiting

        $sent = $this->send('2026-11-28');

        $pmNote = $this->sole($this->pm, 'month_end.time_review');
        $this->assertStringContainsString('2 dòng giờ', $pmNote->body);
        $this->assertStringContainsString('20/11', $pmNote->body);
        $this->assertSame(route('app.time.review', ['week' => '2026-11-16']), $pmNote->link_url);
        $this->assertSame('inapp', $pmNote->channel);

        // Owner: the PM's own entry and the PM-less project (payroll.close) plus the non-project entry (they also hold
        // payroll.manage) — one combined reminder.
        $ownerNote = $this->sole($this->owner, 'month_end.time_review');
        $this->assertStringContainsString('3 dòng giờ', $ownerNote->body);
        $this->assertStringContainsString('25/11', $ownerNote->body);

        // Non-project entries go to payroll.manage holders.
        $this->assertStringContainsString('1 dòng giờ', $this->sole($this->accountant, 'month_end.time_review')->body);
        $this->assertSame(0, $this->notesFor($this->worker));
        $this->assertSame(3, $sent);
    }

    public function test_time_review_window_and_nothing_when_nothing_waits(): void
    {
        $this->entry($this->worker, $this->project, '2026-11-10');

        $this->assertSame(0, $this->send('2026-11-27'));  // four days before month end: too early
        $this->assertSame(1, $this->send('2026-11-28'));
        $this->assertSame(1, $this->send('2026-12-05'));  // day 5 of the next month: still in
        $this->assertSame(0, $this->send('2026-12-06'));  // after day 5: out

        TimeEntry::query()->withoutGlobalScopes()->update(['status' => TimeEntry::STATUS_APPROVED]);
        $this->assertSame(0, $this->send('2026-11-29'));
    }

    public function test_payroll_and_overhead_close_reminders(): void
    {
        $this->staff();

        // December 2: November has no closed payroll → reminder (b) to payroll.close holders only.
        $this->assertSame(1, $this->send('2026-12-02'));
        $payroll = $this->sole($this->owner, 'month_end.payroll_close');
        $this->assertStringContainsString('11/2026', $payroll->title);
        $this->assertSame(route('operator.payroll.index'), $payroll->link_url);
        $this->assertSame(0, $this->notesFor($this->accountant));

        // Payroll closed, overhead not → reminder (c).
        PayrollPeriod::query()->withoutGlobalScopes()->create(['tenant_id' => (string) $this->tenant->id, 'period' => '2026-11', 'working_days' => 22, 'hours_per_day' => 8, 'status' => PayrollPeriod::STATUS_CLOSED, 'closed_at' => now()]);
        $this->assertSame(1, $this->send('2026-12-03'));
        $overhead = $this->sole($this->owner, 'month_end.overhead_close');
        $this->assertSame(route('operator.payroll.overhead', ['month' => '2026-11']), $overhead->link_url);

        // Both closed → nothing more.
        OverheadPeriod::query()->withoutGlobalScopes()->create(['tenant_id' => (string) $this->tenant->id, 'period' => '2026-11', 'status' => OverheadPeriod::STATUS_CLOSED, 'closed_at' => now()]);
        $this->assertSame(0, $this->send('2026-12-04'));
    }

    public function test_draft_payroll_period_links_to_its_page_and_tenants_without_payroll_get_nothing(): void
    {
        // No staff records yet: the tenant does not use payroll.
        $this->assertSame(0, $this->send('2026-12-02'));

        $this->staff();
        $period = PayrollPeriod::query()->withoutGlobalScopes()->create(['tenant_id' => (string) $this->tenant->id, 'period' => '2026-11', 'working_days' => 22, 'hours_per_day' => 8, 'status' => PayrollPeriod::STATUS_DRAFT]);
        $this->assertSame(1, $this->send('2026-12-02'));
        $this->assertSame(route('operator.payroll.periods.show', $period->id), $this->sole($this->owner, 'month_end.payroll_close')->link_url);
    }

    public function test_no_duplicates_on_the_same_day_and_again_the_next_day(): void
    {
        $this->staff();
        $this->entry($this->worker, $this->project, '2026-11-25');

        $this->assertSame(2, $this->send('2026-12-01'));
        $this->assertSame(0, $this->send('2026-12-01'));
        $this->assertSame(2, Notification::query()->count());

        $this->assertSame(2, $this->send('2026-12-02'));
        $this->assertSame(4, Notification::query()->count());
    }

    public function test_tenant_isolation(): void
    {
        $other = Tenant::factory()->create();
        $otherPm = $this->createTenantUser($other, ['name' => 'PM khác'], ['me_other_pm'], ['task.view']);
        $otherWorker = $this->createTenantUser($other, [], ['me_other_worker'], ['task.view']);
        $otherProject = Project::factory()->create(['tenant_id' => (string) $other->id, 'pm_id' => (string) $otherPm->id]);
        $this->entry($otherWorker, $otherProject, '2026-11-25');
        $this->entry($this->worker, $this->project, '2026-11-25');

        $this->assertSame(2, $this->send('2026-11-28'));
        $this->assertSame((string) $other->id, (string) $this->sole($otherPm, 'month_end.time_review')->tenant_id);
        $this->assertSame((string) $this->tenant->id, (string) $this->sole($this->pm, 'month_end.time_review')->tenant_id);
    }

    public function test_command_dry_run_writes_nothing_and_real_run_sends(): void
    {
        $this->entry($this->worker, $this->project, '2026-11-25');

        $this->artisan('reminders:month-end', ['--date' => '2026-11-28', '--dry-run' => true])
            ->expectsOutputToContain('PM An')
            ->assertExitCode(0);
        $this->assertSame(0, Notification::query()->count());

        $this->artisan('reminders:month-end', ['--date' => '2026-11-28'])->assertExitCode(0);
        $this->assertSame(1, Notification::query()->count());
    }

    public function test_command_is_scheduled_daily_at_eight_vietnam_time(): void
    {
        config(['app.enable_scheduler' => true]);
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'reminders:month-end'));

        $this->assertCount(1, $events);
        $event = $events->first();
        $this->assertSame('0 8 * * *', $event->expression);
        $this->assertSame('Asia/Ho_Chi_Minh', (string) $event->timezone);
    }

    private function send(string $date): int
    {
        Carbon::setTestNow($date . ' 08:00:00');

        return app(MonthEndReminderService::class)->send(Carbon::parse($date));
    }

    private function entry(User $user, ?Project $project, string $date, string $status = TimeEntry::STATUS_SUBMITTED): TimeEntry
    {
        return TimeEntry::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $user->tenant_id,
            'user_id' => (string) $user->id,
            'project_id' => $project?->id,
            'category' => $project === null ? TimeEntry::CATEGORY_MEETING : null,
            'work_date' => $date,
            'minutes' => 60,
            'source' => TimeEntry::SOURCE_MANUAL,
            'note' => 'test',
            'status' => $status,
        ]);
    }

    private function staff(): void
    {
        StaffMember::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $this->tenant->id, 'code' => 'NV01', 'name' => 'Kỹ sư Bình', 'staff_type' => 'employee',
            'is_overhead' => false, 'annual_bonus_estimate' => '0', 'start_date' => '2026-01-01', 'is_active' => true,
        ]);
    }

    private function sole(User $user, string $type): Notification
    {
        /** @var Notification $note */
        $note = Notification::query()->where('user_id', (string) $user->id)->where('type', $type)->latest('created_at')->firstOrFail();

        return $note;
    }

    private function notesFor(User $user): int
    {
        return Notification::query()->where('user_id', (string) $user->id)->count();
    }
}
