<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\DashboardAlert;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;

class AlertTaxonomyApiTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticationTestTrait;

    protected Tenant $tenant;
    protected User $actor;
    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->actor = $this->createTenantUser($this->tenant, [], ['admin'], [
            'alert.view',
            'alert.read',
        ]);

        $token = $this->apiLoginToken($this->actor, $this->tenant);
        $this->headers = $this->authHeadersForUser($this->actor, $token);
    }

    public function test_alert_index_returns_only_actor_tenant_scoped_alerts(): void
    {
        $own = DashboardAlert::factory()->create([
            'user_id' => (string) $this->actor->id,
            'tenant_id' => (string) $this->tenant->id,
            'type' => 'task_overdue',
            'severity' => 'high',
            'message' => 'Actor high alert',
            'is_read' => false,
        ]);

        $otherUser = $this->createTenantUser($this->tenant, [], ['member'], []);
        DashboardAlert::factory()->create([
            'user_id' => (string) $otherUser->id,
            'tenant_id' => (string) $this->tenant->id,
            'type' => 'task_overdue',
            'severity' => 'low',
            'message' => 'Other user alert',
        ]);

        $foreignTenant = Tenant::factory()->create();
        $foreignActor = $this->createTenantUser($foreignTenant, [], ['admin'], []);
        DashboardAlert::factory()->create([
            'user_id' => (string) $foreignActor->id,
            'tenant_id' => (string) $foreignTenant->id,
            'type' => 'task_overdue',
            'severity' => 'critical',
            'message' => 'Foreign tenant alert',
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson(route('api.zena.alerts.index', [], false));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', (string) $own->id)
            ->assertJsonPath('data.items.0.type', 'task_overdue')
            ->assertJsonPath('data.items.0.severity', 'high')
            ->assertJsonPath('data.items.0.message', 'Actor high alert')
            ->assertJsonPath('data.items.0.is_read', false);
    }

    public function test_alert_index_filters_by_severity(): void
    {
        DashboardAlert::factory()->create([
            'user_id' => (string) $this->actor->id,
            'tenant_id' => (string) $this->tenant->id,
            'severity' => 'low',
            'message' => 'Low severity alert',
        ]);
        $high = DashboardAlert::factory()->create([
            'user_id' => (string) $this->actor->id,
            'tenant_id' => (string) $this->tenant->id,
            'severity' => 'high',
            'message' => 'High severity alert',
        ]);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.alerts.index', ['severity' => 'high'], false))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', (string) $high->id);
    }

    public function test_alert_index_filters_by_is_read(): void
    {
        $unread = DashboardAlert::factory()->create([
            'user_id' => (string) $this->actor->id,
            'tenant_id' => (string) $this->tenant->id,
            'is_read' => false,
            'message' => 'Unread alert',
        ]);
        DashboardAlert::factory()->create([
            'user_id' => (string) $this->actor->id,
            'tenant_id' => (string) $this->tenant->id,
            'is_read' => true,
            'message' => 'Read alert',
        ]);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.alerts.index', ['is_read' => '0'], false))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', (string) $unread->id);
    }

    public function test_alert_mark_as_read_flips_is_read_flag(): void
    {
        $alert = DashboardAlert::factory()->create([
            'user_id' => (string) $this->actor->id,
            'tenant_id' => (string) $this->tenant->id,
            'is_read' => false,
            'message' => 'Unread alert to mark',
        ]);

        $this->withHeaders($this->headers)
            ->putJson(route('api.zena.alerts.mark-read', ['id' => (string) $alert->id], false))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', (string) $alert->id)
            ->assertJsonPath('data.is_read', true);

        $this->assertTrue((bool) $alert->fresh()->is_read);
    }

    public function test_alert_mark_as_read_is_tenant_safe(): void
    {
        $foreignTenant = Tenant::factory()->create();
        $foreignActor = $this->createTenantUser($foreignTenant, [], ['admin'], []);
        $foreignAlert = DashboardAlert::factory()->create([
            'user_id' => (string) $foreignActor->id,
            'tenant_id' => (string) $foreignTenant->id,
            'is_read' => false,
            'message' => 'Foreign tenant alert',
        ]);

        $this->withHeaders($this->headers)
            ->putJson(route('api.zena.alerts.mark-read', ['id' => (string) $foreignAlert->id], false))
            ->assertNotFound();

        $this->assertFalse((bool) $foreignAlert->fresh()->is_read);
    }

    public function test_alert_index_enforces_rbac(): void
    {
        $restricted = $this->createTenantUser($this->tenant, [], ['member'], []);
        $token = $this->apiLoginToken($restricted, $this->tenant);
        $headers = $this->authHeadersForUser($restricted, $token);

        $this->withHeaders($headers)
            ->getJson(route('api.zena.alerts.index', [], false))
            ->assertForbidden();
    }

    public function test_alert_taxonomy_severity_values_are_canonical(): void
    {
        $this->assertSame(['low', 'medium', 'high', 'critical'], DashboardAlert::VALID_SEVERITIES);
    }
}
