<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class AiSuggestRateLimitTest extends TestCase
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
            ['crm.view', 'crm.manage', 'ai.suggest', 'design-item.view', 'design-item.manage']
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

    public function test_both_ai_routes_declare_the_ai_suggest_throttle(): void
    {
        foreach (['operator.crm.leads.suggest-conversion', 'operator.design-items.suggest-description'] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route {$name} not found");
            $this->assertContains(
                'throttle:ai-suggest',
                $route->gatherMiddleware(),
                "Route {$name} is missing throttle:ai-suggest"
            );
        }
    }

    public function test_eleventh_request_within_a_minute_is_throttled(): void
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

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($this->user)
                ->post(route('operator.crm.leads.suggest-conversion', $this->lead->id), [], $headers)
                ->assertOk();
        }

        $this->actingAs($this->user)
            ->post(route('operator.crm.leads.suggest-conversion', $this->lead->id), [], $headers)
            ->assertStatus(429);
    }
}
