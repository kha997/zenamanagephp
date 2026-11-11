<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Boq;
use App\Models\BoqLineItem;
use App\Models\ChangeRequest;
use App\Models\Contract;
use App\Models\Notification;
use App\Models\PaymentCertificate;
use App\Models\Project;
use App\Models\QcInspection;
use App\Models\QcPlan;
use App\Models\Submittal;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Notifications\SlackChannelService;
use App\Services\SubmittalLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;

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
    use AuthenticationTestTrait;

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

        // Codex review: producers that store route() output (month-end reminders) give absolute URLs on the app's own
        // origin; those are followed as paths. Lookalike or foreign hosts are not.
        $sameOrigin = $this->notify($this->worker, 'month_end.time_review:2026-11:2026-11-11', 'Nhắc duyệt giờ', route('app.time.review', ['week' => '2026-11-09']));
        $this->actingAs($this->worker)->get(route('app.notifications.open', $sameOrigin->id))->assertRedirect('/app/time/review?week=2026-11-09');
        $lookalike = $this->notify($this->worker, 'submittal.submitted', 'Giả', rtrim((string) config('app.url'), '/') . '.evil.example/app');
        $this->actingAs($this->worker)->get(route('app.notifications.open', $lookalike->id))->assertRedirect(route('app.notifications.index'));

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

    public function test_submittal_lifecycle_posts_one_message_per_transition(): void
    {
        // Codex review (amendment 3): submittal events come from EventRecord, driven here through the real service.
        $this->enableSlack();
        $service = app(SubmittalLifecycleService::class);
        $submittal = Submittal::factory()->create([
            'tenant_id' => (string) $this->tenant->id, 'project_id' => (string) $this->project->id,
            'status' => Submittal::STATUS_DRAFT, 'title' => 'Bản vẽ kết cấu móng', 'submittal_number' => 'HS-007',
        ]);
        $context = ['actor_user_id' => (string) $this->pm->id];

        $service->updateContent($submittal, ['title' => 'Bản vẽ kết cấu móng'], $context);
        Http::assertNothingSent();

        $submittal = $service->submit($submittal->fresh(), $context);
        $submittal = $service->reject($submittal, $context + ['comments' => 'Thiếu mặt cắt']);
        $submittal = $service->startRevision($submittal, $context);
        $submittal = $service->submit($submittal, $context);
        $service->approve($submittal, $context);

        $texts = $this->slackTexts();
        $this->assertCount(4, $texts);
        $this->assertStringContainsString('Hồ sơ trình duyệt HS-007 – Bản vẽ kết cấu móng đã nộp', $texts[0]);
        $this->assertStringContainsString('bị từ chối', $texts[1]);
        $this->assertStringContainsString('đã nộp lại', $texts[2]);
        $this->assertStringContainsString('được duyệt', $texts[3]);
        $this->assertStringContainsString(route('operator.submittals.show', $submittal->id), $texts[0]);
    }

    public function test_change_request_apply_posts_once_per_change_request_even_with_two_recipients(): void
    {
        // Codex review (amendment 3): one message per change request, not per recipient; a second change request is
        // its own event.
        $this->enableSlack();
        $applier = $this->createTenantUser($this->tenant, [], ['bn_cr_applier'], ['change-request.view', 'change-request.apply']);
        $headers = $this->authHeadersForUser($applier, $this->apiLoginToken($applier, $this->tenant));
        $first = $this->approvedChangeRequest('CR-101', 'Đổi gạch lát sàn tầng 1');
        $second = $this->approvedChangeRequest('CR-102', 'Thêm ổ cắm phòng khách');

        $this->withHeaders($headers)->postJson(route('api.zena.change-requests.apply', ['id' => (string) $first->id], false))->assertOk();
        $this->assertSame(2, Notification::query()->withoutGlobalScopes()->where('event_key', 'change_request.implemented')->count());
        $this->withHeaders($headers)->postJson(route('api.zena.change-requests.apply', ['id' => (string) $second->id], false))->assertOk();

        $texts = $this->slackTexts();
        $this->assertCount(2, $texts);
        $this->assertStringContainsString('Yêu cầu thay đổi đã áp dụng: CR-101 – Đổi gạch lát sàn tầng 1', $texts[0]);
        $this->assertStringContainsString(route('operator.change-requests.show', $first->id), $texts[0]);
        $this->assertStringContainsString('CR-102', $texts[1]);
    }

    public function test_ncr_assigned_posts_once_with_the_assignee(): void
    {
        $this->enableSlack();
        $inspector = $this->createTenantUser($this->tenant, [], ['bn_inspector'], ['inspection.view', 'inspection.create', 'inspection.edit']);
        $headers = $this->authHeadersForUser($inspector, $this->apiLoginToken($inspector, $this->tenant));
        $plan = QcPlan::factory()->create(['tenant_id' => (string) $this->tenant->id, 'project_id' => (string) $this->project->id, 'created_by' => (string) $inspector->id]);
        $inspection = QcInspection::factory()->create([
            'tenant_id' => (string) $this->tenant->id, 'qc_plan_id' => (string) $plan->id, 'inspector_id' => (string) $inspector->id,
            'status' => 'failed', 'title' => 'Nghiệm thu cốt thép sàn tầng 2',
        ]);

        $ncrId = (string) $this->withHeaders($headers)->postJson(
            route('api.zena.inspections.ncrs.store', ['inspection' => (string) $inspection->id], false),
            ['title' => 'Thép chờ cột C3 thiếu chiều dài neo', 'description' => 'Thiếu 20 cm.', 'assigned_to' => (string) $this->worker->id],
        )->assertStatus(201)->json('data.id');

        $texts = $this->slackTexts();
        $this->assertCount(1, $texts);
        $this->assertStringContainsString('NCR mới:', $texts[0]);
        $this->assertStringContainsString('Thép chờ cột C3 thiếu chiều dài neo', $texts[0]);
        $this->assertStringContainsString('Giao cho: Kỹ sư Bình', $texts[0]);
        $this->assertStringContainsString(route('operator.inspections.ncrs.show', [$inspection->id, $ncrId]), $texts[0]);
    }

    public function test_payment_certificate_approval_posts_one_message(): void
    {
        $this->enableSlack();
        $accountant = $this->createTenantUser($this->tenant, [], ['bn_accountant'], [
            'contract.view', 'contract.update', 'payment_certificate.view', 'payment_certificate.create', 'payment_certificate.approve',
        ]);
        $contract = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id, 'project_id' => (string) $this->project->id, 'code' => 'HD-01',
            'title' => 'HĐ thi công phần thô', 'contract_type' => Contract::TYPE_CONSTRUCTION, 'created_by' => (string) $accountant->id,
        ]);
        $boq = Boq::query()->create(['tenant_id' => (string) $this->tenant->id, 'project_id' => (string) $this->project->id, 'contract_id' => (string) $contract->id, 'code' => 'BOQ-HD-01', 'name' => 'BOQ HĐ-01']);
        $line = BoqLineItem::query()->create(['tenant_id' => (string) $this->tenant->id, 'boq_id' => (string) $boq->id, 'code' => 'L001', 'name' => 'Bê tông', 'quantity' => 50, 'unit' => 'm3', 'unit_price' => 1000000]);
        $h = ['X-Tenant-ID' => (string) $this->tenant->id];
        $this->actingAs($accountant)->post(route('operator.contracts.certificates.store', $contract->id), ['period_from' => '2026-10-01', 'period_to' => '2026-10-31'], $h)->assertRedirect();
        /** @var PaymentCertificate $cert */
        $cert = PaymentCertificate::query()->withoutGlobalScopes()->where('contract_id', (string) $contract->id)->firstOrFail();
        $this->actingAs($accountant)->post(route('operator.contracts.certificates.lines.save', [$contract->id, $cert->id]), ['lines' => [(string) $line->id => 10]], $h)->assertRedirect();
        $this->actingAs($accountant)->post(route('operator.contracts.certificates.submit', [$contract->id, $cert->id]), [], $h)->assertRedirect();
        Http::assertNothingSent();

        $this->actingAs($accountant)->post(route('operator.contracts.certificates.approve', [$contract->id, $cert->id]), [], $h)->assertRedirect()->assertSessionHasNoErrors();

        $texts = $this->slackTexts();
        $this->assertCount(1, $texts);
        $this->assertStringContainsString('Chứng chỉ thanh toán kỳ 1 đã duyệt', $texts[0]);
        $this->assertStringContainsString('Thực trả:', $texts[0]);
        $this->assertStringContainsString(route('operator.contracts.show', $contract->id), $texts[0]);
    }

    public function test_crm_events_stay_in_app_and_month_end_posts_once_per_key(): void
    {
        $this->enableSlack();
        $this->notify($this->pm, 'crm.lead.captured', 'Khách hàng mới', '/app/crm');
        $this->notify($this->pm, 'design_item.status_changed', 'Bản vẽ đổi trạng thái', '/app');
        Http::assertNothingSent();

        $this->notify($this->pm, 'month_end.time_review:2026-11:2026-11-11', 'Còn giờ chờ duyệt', '/app/time/review?week=2026-11-09');
        $this->notify($this->owner, 'month_end.time_review:2026-11:2026-11-11', 'Còn giờ chờ duyệt', '/app/time/review?week=2026-11-02');
        $this->assertCount(1, $this->slackTexts());
    }

    public function test_slack_failures_never_break_the_action_and_other_companies_are_untouched(): void
    {
        $this->enableSlack();
        Http::fake(['hooks.slack.com/*' => Http::response('invalid_token', 403)]);
        $submittal = Submittal::factory()->create([
            'tenant_id' => (string) $this->tenant->id, 'project_id' => (string) $this->project->id,
            'status' => Submittal::STATUS_DRAFT, 'title' => 'Hồ sơ A', 'submittal_number' => 'HS-900',
        ]);
        $submitted = app(SubmittalLifecycleService::class)->submit($submittal, ['actor_user_id' => (string) $this->pm->id]);
        $this->assertSame(Submittal::STATUS_SUBMITTED, $submitted->status);

        // Another company without a Slack channel: nothing is posted.
        $other = Tenant::factory()->create();
        $otherProject = Project::factory()->create(['tenant_id' => (string) $other->id]);
        Http::fake(['hooks.slack.com/*' => Http::response('ok', 200)]);
        $foreign = Submittal::factory()->create([
            'tenant_id' => (string) $other->id, 'project_id' => (string) $otherProject->id,
            'status' => Submittal::STATUS_DRAFT, 'title' => 'Hồ sơ công ty khác', 'submittal_number' => 'HS-X',
        ]);
        app(SubmittalLifecycleService::class)->submit($foreign, ['actor_user_id' => (string) $this->pm->id]);
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

    /**
     * @return list<string>
     */
    private function slackTexts(): array
    {
        return array_values(array_map(
            static fn (array $pair): string => (string) $pair[0]['text'],
            Http::recorded(fn (HttpRequest $r): bool => str_starts_with($r->url(), 'https://hooks.slack.com/'))->all(),
        ));
    }

    private function approvedChangeRequest(string $number, string $title): ChangeRequest
    {
        /** @var ChangeRequest $cr */
        $cr = ChangeRequest::query()->create([
            'tenant_id' => (string) $this->tenant->id, 'project_id' => (string) $this->project->id, 'title' => $title,
            'description' => 'Theo yêu cầu chủ nhà.', 'change_type' => 'scope', 'priority' => 'medium',
            'status' => ChangeRequest::STATUS_APPROVED, 'requested_by' => (string) $this->worker->id,
            'assigned_to' => (string) $this->owner->id, 'approved_by' => (string) $this->owner->id,
            'change_number' => $number, 'requested_at' => now(),
        ]);

        return $cr;
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
