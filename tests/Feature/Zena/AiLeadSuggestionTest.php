<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class AiLeadSuggestionTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private Tenant $tenant;
    private User $user;
    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        config(['ai.anthropic_api_key' => 'test-key']);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser(
            $this->tenant,
            [],
            ['admin'],
            ['crm.view', 'crm.manage', 'ai.suggest']
        );

        $this->lead = Lead::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contact_hint' => 'Chi Lan - 090xxxxxxx',
            'project_description' => 'Can ho 2 phong ngu can thiet ke noi that',
            'source' => 'zalo',
            'status' => Lead::STATUS_NEW,
            'captured_by' => (string) $this->user->id,
        ]);
    }

    public function test_returns_suggestion_for_authorized_user(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_lead_conversion',
                    'input' => ['service_category' => 'interior', 'scope_summary' => 'Thiết kế nội thất căn hộ.'],
                ]],
            ], 200),
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)->get(route('operator.crm.leads'), $headers)->assertOk();

        $response = $this->actingAs($this->user)
            ->post(
                route('operator.crm.leads.suggest-conversion', $this->lead->id),
                [],
                $headers
            );

        $response->assertOk()->assertJson([
            'success' => true,
            'data' => ['service_category' => 'interior', 'scope_summary' => 'Thiết kế nội thất căn hộ.'],
        ]);
    }

    public function test_sends_only_project_description_to_anthropic(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_lead_conversion',
                    'input' => ['service_category' => 'interior', 'scope_summary' => 'Tóm tắt.'],
                ]],
            ], 200),
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)->get(route('operator.crm.leads'), $headers)->assertOk();

        $this->actingAs($this->user)
            ->post(
                route('operator.crm.leads.suggest-conversion', $this->lead->id),
                [],
                $headers
            );

        Http::assertSent(function ($request) {
            $this->assertSame('Can ho 2 phong ngu can thiet ke noi that', $request->data()['messages'][0]['content']);

            return true;
        });
    }

    public function test_returns_503_when_ai_service_unavailable(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['error' => 'down'], 500)]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)->get(route('operator.crm.leads'), $headers)->assertOk();

        $response = $this->actingAs($this->user)
            ->post(
                route('operator.crm.leads.suggest-conversion', $this->lead->id),
                [],
                $headers
            );

        $response->assertStatus(503)->assertJson(['success' => false]);
    }

    public function test_denied_without_ai_suggest_permission(): void
    {
        $salesUser = $this->createTenantUser($this->tenant, [], ['sales'], ['crm.view', 'crm.manage']);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($salesUser)->get(route('operator.crm.leads'), $headers)->assertOk();

        $response = $this->actingAs($salesUser)
            ->post(
                route('operator.crm.leads.suggest-conversion', $this->lead->id),
                [],
                $headers
            );

        $response->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_denied_without_crm_manage_permission(): void
    {
        $viewer = $this->createTenantUser($this->tenant, [], ['viewer'], ['crm.view', 'ai.suggest']);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($viewer)->get(route('operator.crm.leads'), $headers)->assertOk();

        $response = $this->actingAs($viewer)
            ->post(
                route('operator.crm.leads.suggest-conversion', $this->lead->id),
                [],
                $headers
            );

        $response->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_returns_404_for_lead_in_another_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = $this->createTenantUser($otherTenant, [], ['admin'], ['crm.view', 'crm.manage', 'ai.suggest']);
        $headers = ['X-Tenant-ID' => (string) $otherTenant->id];

        $this->actingAs($otherUser)->get(route('operator.crm.leads'), $headers)->assertOk();

        $response = $this->actingAs($otherUser)
            ->post(
                route('operator.crm.leads.suggest-conversion', $this->lead->id),
                [],
                $headers
            );

        $response->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_requires_authentication(): void
    {
        $this->get(route('login'))->assertOk();

        $this->post(route('operator.crm.leads.suggest-conversion', $this->lead->id))
            ->assertRedirect();
    }
}
