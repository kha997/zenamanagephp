<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Jobs\DeliverWebhook;
use App\Models\EventRecord;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class OperatorPlatformUiTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser(
            $this->tenant,
            [],
            ['admin'],
            [
                'task.view',
                'report.view',
                'report.export',
                'webhook.view',
                'webhook.manage',
            ]
        );

        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Platform Project',
            'code' => 'PRJ-PL-001',
        ]);
    }

    public function test_schedule_page_renders_gantt_bars_for_dated_tasks(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        Task::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'name' => 'Đào móng khu A',
            'status' => 'in_progress',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-20',
            'progress_percent' => 40,
        ]);

        $this->actingAs($this->user)
            ->get(route('operator.schedule.index', ['project_id' => (string) $this->project->id]), $headers)
            ->assertOk()
            ->assertSee('Tiến độ dự án')
            ->assertSee('Đào móng khu A')
            ->assertSee('40%');
    }

    public function test_schedule_page_shows_empty_state_without_dated_tasks(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.schedule.index'), $headers)
            ->assertOk()
            ->assertSee('Chưa có công việc có ngày bắt đầu/kết thúc');
    }

    public function test_report_export_streams_tenant_scoped_csv(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $foreignTenant = Tenant::factory()->create();
        Project::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'name' => 'Foreign Secret Project',
        ]);

        $this->actingAs($this->user)
            ->get(route('operator.reports.index'), $headers)
            ->assertOk()
            ->assertSee('Xuất báo cáo');

        $response = $this->actingAs($this->user)
            ->withHeaders($headers)
            ->post(route('operator.reports.export'), ['dataset' => 'projects']);

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Platform Project', $csv);
        $this->assertStringNotContainsString('Foreign Secret Project', $csv);
    }

    public function test_webhook_crud_flow(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.webhooks.index'), $headers)
            ->assertOk()
            ->assertSee('Chưa có webhook');

        $this->actingAs($this->user)
            ->withHeaders($headers)
            ->post(route('operator.webhooks.store'), [
                'name' => 'ERP Sync',
                'url' => 'https://erp.example.com/hooks/zena',
                'event_keys' => 'material_request., task.',
            ])
            ->assertRedirect(route('operator.webhooks.index'));

        $endpoint = WebhookEndpoint::query()->first();
        $this->assertNotNull($endpoint);
        $this->assertTrue($endpoint->is_active);
        $this->assertSame(['material_request.', 'task.'], $endpoint->event_keys);

        $this->actingAs($this->user)
            ->withHeaders($headers)
            ->post(route('operator.webhooks.toggle', $endpoint->id))
            ->assertRedirect(route('operator.webhooks.index'));

        $this->assertFalse($endpoint->fresh()->is_active);

        $this->actingAs($this->user)
            ->withHeaders($headers)
            ->delete(route('operator.webhooks.destroy', $endpoint->id))
            ->assertRedirect(route('operator.webhooks.index'));

        $this->assertNull(WebhookEndpoint::query()->find($endpoint->id));
    }

    public function test_event_record_creation_dispatches_matching_webhooks_only(): void
    {
        Queue::fake();

        $matching = WebhookEndpoint::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Matching',
            'url' => 'https://a.example.com/hook',
            'secret' => 'secret-a',
            'event_keys' => ['material_request.'],
            'is_active' => true,
            'created_by' => (string) $this->user->id,
        ]);

        WebhookEndpoint::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Non-matching',
            'url' => 'https://b.example.com/hook',
            'secret' => 'secret-b',
            'event_keys' => ['task.'],
            'is_active' => true,
            'created_by' => (string) $this->user->id,
        ]);

        WebhookEndpoint::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Inactive wildcard',
            'url' => 'https://c.example.com/hook',
            'secret' => 'secret-c',
            'event_keys' => ['*'],
            'is_active' => false,
            'created_by' => (string) $this->user->id,
        ]);

        EventRecord::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'aggregate_type' => 'material_request',
            'aggregate_id' => 'MR-1',
            'event_key' => 'material_request.approved',
            'actor_user_id' => (string) $this->user->id,
            'payload' => ['status' => 'approved'],
            'occurred_at' => now(),
        ]);

        Queue::assertPushed(DeliverWebhook::class, 1);
        Queue::assertPushed(
            DeliverWebhook::class,
            fn (DeliverWebhook $job) => $job->endpointId === (string) $matching->id
                && $job->payload['event_key'] === 'material_request.approved'
        );
    }

    public function test_deliver_webhook_job_posts_signed_payload_and_records_delivery(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://a.example.com/*' => \Illuminate\Support\Facades\Http::response(['ok' => true], 200),
        ]);

        $endpoint = WebhookEndpoint::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Signed',
            'url' => 'https://a.example.com/hook',
            'secret' => 'test-secret',
            'event_keys' => ['*'],
            'is_active' => true,
            'created_by' => (string) $this->user->id,
        ]);

        $payload = ['event_key' => 'task.created', 'aggregate_id' => 'T-1'];

        (new DeliverWebhook((string) $endpoint->id, $payload))->handle();

        $expectedSignature = 'sha256=' . hash_hmac(
            'sha256',
            (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'test-secret'
        );

        \Illuminate\Support\Facades\Http::assertSent(
            fn ($request) => $request->url() === 'https://a.example.com/hook'
                && $request->header('X-Zena-Signature')[0] === $expectedSignature
                && $request->header('X-Zena-Event')[0] === 'task.created'
        );

        $endpoint->refresh();
        $this->assertNotNull($endpoint->last_delivered_at);
        $this->assertSame(0, $endpoint->failure_count);
    }

    public function test_api_token_create_and_revoke(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.api-tokens.index'), $headers)
            ->assertOk()
            ->assertSee('Chưa có token');

        $this->actingAs($this->user)
            ->withHeaders($headers)
            ->post(route('operator.api-tokens.store'), ['name' => 'ci-pipeline'])
            ->assertRedirect(route('operator.api-tokens.index'))
            ->assertSessionHas('success');

        $this->assertSame(1, $this->user->tokens()->count());

        $tokenId = (string) $this->user->tokens()->first()->id;

        $this->actingAs($this->user)
            ->withHeaders($headers)
            ->delete(route('operator.api-tokens.destroy', $tokenId))
            ->assertRedirect(route('operator.api-tokens.index'));

        $this->assertSame(0, $this->user->tokens()->count());
    }
}
