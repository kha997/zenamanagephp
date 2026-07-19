<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Account;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class AiOpportunitySummaryTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private Tenant $tenant;
    private User $user;
    private Opportunity $opportunity;

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
            ['crm.view', 'ai.suggest']
        );

        $account = Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'display_name' => 'Test Account',
        ]);

        $this->opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Test Opp',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);

        // Establish a real session so CSRF token resolution works for web POSTs
        // (TestCase deliberately refuses to fabricate sessions — 2026-07-15 regression note).
        $this->get('/login');
    }

    public function test_returns_summary_for_authorized_user(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'summarize_opportunity',
                    'input' => ['summary' => '- Cơ hội mới, chưa có báo giá'],
                ]],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)->post(
            route('operator.crm.opportunities.ai-summary', $this->opportunity->id)
        );

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.summary', '- Cơ hội mới, chưa có báo giá');
        $this->assertNotEmpty($response->json('data.generated_at'));
    }

    public function test_returns_503_when_ai_disabled(): void
    {
        config(['ai.anthropic_api_key' => '']);

        $response = $this->actingAs($this->user)->post(
            route('operator.crm.opportunities.ai-summary', $this->opportunity->id)
        );

        $response->assertStatus(503);
        $response->assertJsonPath('success', false);
    }

    public function test_requires_ai_suggest_permission(): void
    {
        $viewer = $this->createTenantUser($this->tenant, [], ['member'], ['crm.view']);

        $this->actingAs($viewer)->post(
            route('operator.crm.opportunities.ai-summary', $this->opportunity->id)
        )->assertStatus(403);
    }

    public function test_requires_crm_view_permission(): void
    {
        $noCrm = $this->createTenantUser($this->tenant, [], ['member'], ['ai.suggest']);

        $this->actingAs($noCrm)->post(
            route('operator.crm.opportunities.ai-summary', $this->opportunity->id)
        )->assertStatus(403);
    }

    public function test_returns_404_for_other_tenants_opportunity(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = $this->createTenantUser($otherTenant, [], ['admin'], ['crm.view', 'ai.suggest']);

        $this->actingAs($otherUser)->post(
            route('operator.crm.opportunities.ai-summary', $this->opportunity->id)
        )->assertStatus(404);
    }
}
