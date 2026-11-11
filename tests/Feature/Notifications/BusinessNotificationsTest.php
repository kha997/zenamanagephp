<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Notifications\SlackChannelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-124 (Gate 2 B-1, S-1, E-1, O-1) — business notifications.
 *
 * - B-1: bell with the unread count in the operator top bar, a notifications page, open (mark read + go) and read-all.
 * - S-1: the company's Slack incoming-webhook URL, stored encrypted, hooks.slack.com only, with a test send; managed with
 *   notification.channel.manage.
 * - E-1: selected business events post one Slack message per event (not per recipient); CRM events do not; a Slack
 *   failure never breaks the action.
 * - O-1: reminders:overdue-tasks — each PM gets one in-app notification per project with overdue tasks, the company channel
 *   one summary; idempotent per day.
 */
class BusinessNotificationsTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private const SLACK_URL = 'https://hooks.slack.com/services/T000/B000/abcdefSECRET';

    private Tenant $tenant;

    private User $owner;

    private User $pm;

    private User $worker;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        Carbon::setTestNow('2026-11-11 09:00:00');
        Http::fake(['hooks.slack.com/*' => Http::response('ok', 200)]);
        $this->tenant = Tenant::factory()->create(['name' => 'Công ty Zena']);
        $this->owner = $this->createTenantUser($this->tenant, ['name' => 'Chủ Dũng'], ['bn_owner'], ['task.view', 'notification.channel.manage']);
        $this->pm = $this->createTenantUser($this->tenant, ['name' => 'PM An'], ['bn_pm'], ['task.view', 'project.view']);
        $this->worker = $this->createTenantUser($this->tenant, ['name' => 'Kỹ sư Bình'], ['bn_worker'], ['task.view']);
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'name' => 'Nhà phố Quận 7', 'pm_id' => (string) $this->pm->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---- B-1: bell and notifications page ----

    public function test_bell_shows_the_unread_count_and_the_page_lists_only_my_notifications(): void
    {
        $this->notify($this->worker, 'submittal.submitted', 'Hồ sơ HS-01 đã nộp', '/app/projects/' . $this->project->id);
        $this->notify($this->worker, 'ncr.assigned', 'NCR được giao', '/app/projects/' . $this->project->id);
        $read = $this->notify($this->worker, 'crm.lead.captured', 'Khách mới', '/app/crm');
        $read->forceFill(['read_at' => now()])->save();
        $this->notify($this->pm, 'submittal.approved', 'Thông báo của PM', '/app/projects/' . $this->project->id);

        $page = $this->actingAs($this->worker)->get(route('app.notifications.index'))->assertOk();
        $page->assertSee('data-testid="notification-bell"', false)
            ->assertSee('data-testid="notification-unread-count">2<', false)
            ->assertSee('Hồ sơ HS-01 đã nộp')->assertSee('NCR được giao')->assertSee('Khách mới')
            ->assertDontSee('Thông báo của PM');

        $this->actingAs($this->worker)->get(route('app.notifications.index', ['unread' => 1]))->assertOk()
            ->assertSee('Hồ sơ HS-01 đã nộp')->assertDontSee('Khách mới');

        // No unread → no badge number.
        $this->actingAs($this->owner)->get(route('app.notifications.index'))->assertOk()
            ->assertSee('data-testid="notification-bell"', false)->assertDontSee('data-testid="notification-unread-count"', false);
    }

    public function test_open_marks_read_and_redirects_inside_the_app_only(): void
    {
        $inside = $this->notify($this->worker, 'submittal.submitted', 'Nội bộ', '/app/projects/' . $this->project->id);
        $outside = $this->notify($this->worker, 'submittal.submitted', 'Ngoài', 'https://evil.example/phish');
        $other = $this->notify($this->pm, 'submittal.submitted', 'Của người khác', '/app/projects/' . $this->project->id);

        $this->actingAs($this->worker)->get(route('app.notifications.open', $inside->id))->assertRedirect('/app/projects/' . $this->project->id);
        $this->assertNotNull($inside->fresh()?->read_at);

        $this->actingAs($this->worker)->get(route('app.notifications.open', $outside->id))->assertRedirect(route('app.notifications.index'));
        $this->assertNotNull($outside->fresh()?->read_at);

        $this->actingAs($this->worker)->get(route('app.notifications.open', $other->id))->assertNotFound();
        $this->assertNull($other->fresh()?->read_at);
    }

    public function test_read_all_marks_only_my_notifications(): void
    {
        $mine = $this->notify($this->worker, 'ncr.assigned', 'A', '/app');
        $theirs = $this->notify($this->pm, 'ncr.assigned', 'B', '/app');

        $this->actingAs($this->worker)->post(route('app.notifications.read-all'))->assertRedirect(route('app.notifications.index'));
        $this->assertNotNull($mine->fresh()?->read_at);
        $this->assertNull($theirs->fresh()?->read_at);
    }

    // ---- S-1: Slack settings ----

    public function test_only_the_permission_holder_manages_the_slack_url_which_is_validated_and_encrypted(): void
    {
        // PM lacks notification.channel.manage.
        $this->actingAs($this->pm)->post(route('app.settings.notifications.update'), ['slack_webhook_url' => self::SLACK_URL, 'slack_enabled' => '1'])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertNull(app(SlackChannelService::class)->webhookUrl((string) $this->tenant->id));

        $this->actingAs($this->owner)->get(route('app.settings.notifications'))->assertOk()->assertSee('Slack');

        foreach (['http://hooks.slack.com/services/x', 'https://evil.example/services/x', 'https://hooks.slack.com.evil.example/services/x', 'https://127.0.0.1/hook'] as $bad) {
            $this->actingAs($this->owner)->from(route('app.settings.notifications'))
                ->post(route('app.settings.notifications.update'), ['slack_webhook_url' => $bad, 'slack_enabled' => '1'])
                ->assertSessionHasErrors('slack_webhook_url');
        }

        $this->actingAs($this->owner)->post(route('app.settings.notifications.update'), ['slack_webhook_url' => self::SLACK_URL, 'slack_enabled' => '1'])
            ->assertRedirect(route('app.settings.notifications'))->assertSessionHasNoErrors();
        $raw = (string) DB::table('tenants')->where('id', (string) $this->tenant->id)->value('settings');
        $this->assertStringNotContainsString('abcdefSECRET', $raw, 'the webhook URL must be stored encrypted');
        $this->assertSame(self::SLACK_URL, app(SlackChannelService::class)->webhookUrl((string) $this->tenant->id));

        // The page never shows the full URL.
        $this->actingAs($this->owner)->get(route('app.settings.notifications'))->assertOk()
            ->assertDontSee('abcdefSECRET')->assertSee('…SECRET');

        // Test send posts to the URL.
        $this->actingAs($this->owner)->post(route('app.settings.notifications.test'))->assertRedirect()->assertSessionHas('success');
        Http::assertSent(fn (HttpRequest $r): bool => $r->url() === self::SLACK_URL && str_contains((string) $r['text'], 'Công ty Zena'));

        // Switching off keeps the saved URL; switching on again needs no re-paste; "remove" deletes it.
        $this->actingAs($this->owner)->post(route('app.settings.notifications.update'), ['slack_webhook_url' => '', 'slack_enabled' => '0'])->assertSessionHasNoErrors();
        $this->assertNull(app(SlackChannelService::class)->webhookUrl((string) $this->tenant->id));
        $this->assertSame(self::SLACK_URL, app(SlackChannelService::class)->storedUrl((string) $this->tenant->id));
        $this->actingAs($this->owner)->post(route('app.settings.notifications.update'), ['slack_webhook_url' => '', 'slack_enabled' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(self::SLACK_URL, app(SlackChannelService::class)->webhookUrl((string) $this->tenant->id));
        $this->actingAs($this->owner)->post(route('app.settings.notifications.update'), ['slack_webhook_url' => '', 'slack_remove' => '1'])->assertSessionHasNoErrors();
        $this->assertNull(app(SlackChannelService::class)->storedUrl((string) $this->tenant->id));
    }

    // ---- E-1: events to Slack ----

    public function test_selected_events_post_one_slack_message_per_event_and_crm_events_none(): void
    {
        $this->enableSlack();
        $link = '/app/projects/' . $this->project->id;
        foreach ([$this->owner, $this->pm, $this->worker] as $user) {
            $this->notify($user, 'submittal.submitted', 'Hồ sơ HS-02 đã nộp', $link);
        }
        $this->notify($this->pm, 'crm.lead.captured', 'Khách hàng mới', '/app/crm');
        $this->notify($this->pm, 'design_item.status_changed', 'Bản vẽ đổi trạng thái', $link);

        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $r): bool => $r->url() === self::SLACK_URL
            && str_contains((string) $r['text'], 'Hồ sơ HS-02 đã nộp')
            && str_contains((string) $r['text'], $link));

        // Each E-1 key goes out.
        foreach (['change_request.implemented', 'submittal.resubmitted', 'submittal.approved', 'submittal.rejected', 'ncr.assigned', 'payment_certificate.approved'] as $key) {
            $this->notify($this->pm, $key, 'Sự kiện ' . $key, $link);
        }
        Http::assertSentCount(7);

        // Month-end reminders: one message per company and day, even with different links per person.
        $this->notify($this->pm, 'month_end.time_review:2026-11:2026-11-11', 'Còn giờ chờ duyệt', '/app/time/review?week=2026-11-09');
        $this->notify($this->owner, 'month_end.time_review:2026-11:2026-11-11', 'Còn giờ chờ duyệt', '/app/time/review?week=2026-11-02');
        Http::assertSentCount(8);
    }

    public function test_slack_failures_never_break_the_action_and_other_companies_are_untouched(): void
    {
        $this->enableSlack();
        Http::fake(['hooks.slack.com/*' => Http::response('invalid_token', 403)]);

        $notification = $this->notify($this->pm, 'ncr.assigned', 'NCR mới', '/app');
        $this->assertTrue($notification->exists);

        $other = Tenant::factory()->create();
        $otherUser = $this->createTenantUser($other, [], ['bn_other'], ['task.view']);
        Http::fake(['hooks.slack.com/*' => Http::response('ok', 200)]);
        Notification::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $other->id, 'user_id' => (string) $otherUser->id, 'type' => 'ncr', 'priority' => 'normal',
            'title' => 'NCR công ty khác', 'body' => 'x', 'link_url' => '/app', 'channel' => 'inapp', 'event_key' => 'ncr.assigned',
        ]);
        Http::assertNothingSent();
    }

    // ---- O-1: overdue digest ----

    public function test_overdue_digest_notifies_each_pm_once_per_project_and_posts_one_slack_summary(): void
    {
        $this->enableSlack();
        $other = Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'name' => 'Biệt thự Thảo Điền', 'pm_id' => (string) $this->pm->id]);
        $this->task($this->project, 'Đổ bê tông móng', '2026-11-05', Task::STATUS_IN_PROGRESS);
        $this->task($this->project, 'Nghiệm thu thép', '2026-11-10', Task::STATUS_PENDING);
        $this->task($this->project, 'Đã xong', '2026-11-01', Task::STATUS_COMPLETED);
        $this->task($this->project, 'Đã huỷ', '2026-11-01', Task::STATUS_CANCELLED);
        $this->task($this->project, 'Hạn hôm nay', '2026-11-11', Task::STATUS_PENDING);
        $this->task($other, 'Chống thấm', '2026-11-09', Task::STATUS_ON_HOLD);

        $this->assertSame(0, Artisan::call('reminders:overdue-tasks', ['--dry-run' => true]));
        $this->assertStringContainsString('Nhà phố Quận 7: 2', Artisan::output());
        $this->assertSame(0, $this->digests());
        Http::assertNothingSent();

        Artisan::call('reminders:overdue-tasks');
        $rows = Notification::query()->withoutGlobalScopes()->where('event_key', 'like', 'tasks.overdue_digest:%')->get();
        $this->assertCount(2, $rows);
        $this->assertSame([(string) $this->pm->id], $rows->pluck('user_id')->map(fn ($id): string => (string) $id)->unique()->values()->all());
        $row = $rows->firstWhere('project_id', (string) $this->project->id);
        $this->assertNotNull($row);
        $this->assertStringContainsString('2 việc quá hạn', (string) $row->title);
        $this->assertSame(route('app.projects.show', $this->project->id, false), $row->link_url);

        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $r): bool => str_contains((string) $r['text'], 'Nhà phố Quận 7')
            && str_contains((string) $r['text'], 'Đổ bê tông móng')
            && str_contains((string) $r['text'], 'Chống thấm')
            && !str_contains((string) $r['text'], 'Đã xong'));

        // Second run the same day writes and posts nothing new.
        Artisan::call('reminders:overdue-tasks');
        $this->assertSame(2, $this->digests());
        Http::assertSentCount(1);
    }

    private function enableSlack(): void
    {
        app(SlackChannelService::class)->save((string) $this->tenant->id, self::SLACK_URL, true);
    }

    private function notify(User $user, string $eventKey, string $title, ?string $link): Notification
    {
        /** @var Notification $notification */
        $notification = Notification::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $this->tenant->id, 'user_id' => (string) $user->id, 'type' => explode('.', $eventKey)[0],
            'priority' => 'normal', 'title' => $title, 'body' => 'Nội dung ' . $title, 'link_url' => $link, 'channel' => 'inapp',
            'event_key' => $eventKey, 'project_id' => (string) $this->project->id,
        ]);

        return $notification;
    }

    private function task(Project $project, string $name, string $endDate, string $status): void
    {
        Task::factory()->create([
            'tenant_id' => (string) $this->tenant->id, 'project_id' => (string) $project->id, 'name' => $name, 'title' => $name,
            'status' => $status, 'end_date' => $endDate, 'start_date' => '2026-11-01', 'assigned_to' => (string) $this->worker->id,
        ]);
    }

    private function digests(): int
    {
        return Notification::query()->withoutGlobalScopes()->where('event_key', 'like', 'tasks.overdue_digest:%')->count();
    }
}
