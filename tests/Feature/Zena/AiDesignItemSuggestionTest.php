<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class AiDesignItemSuggestionTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private Tenant $tenant;
    private User $user;
    private Project $project;

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
            ['design-item.view', 'design-item.manage', 'ai.suggest']
        );
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);
    }

    public function test_returns_suggestion_for_authorized_user(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_design_item_description',
                    'input' => ['description' => 'Bản vẽ mặt bằng tầng 1.'],
                ]],
            ], 200),
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.design-items.create'), $headers)
            ->assertOk();

        $response = $this->actingAs($this->user)
            ->post(route('operator.design-items.suggest-description'), [
                'project_id' => (string) $this->project->id,
                'item_type' => 'concept',
            ], $headers);

        $response->assertOk()->assertJson([
            'success' => true,
            'data' => ['description' => 'Bản vẽ mặt bằng tầng 1.'],
        ]);
    }

    public function test_resolves_service_category_from_originating_opportunity(): void
    {
        Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => \App\Models\Account::query()->create([
                'tenant_id' => (string) $this->tenant->id,
                'account_type' => \App\Models\Account::TYPE_INDIVIDUAL,
                'display_name' => 'Khach hang',
                'status' => \App\Models\Account::STATUS_ACTIVE,
            ])->id,
            'opportunity_name' => 'Co hoi lien ket project',
            'service_category' => 'interior',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
            'converted_project_id' => (string) $this->project->id,
        ]);

        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_design_item_description',
                    'input' => ['description' => 'Mô tả nội thất.'],
                ]],
            ], 200),
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.design-items.create'), $headers)
            ->assertOk();

        $this->actingAs($this->user)
            ->post(route('operator.design-items.suggest-description'), [
                'project_id' => (string) $this->project->id,
                'item_type' => 'interior',
            ], $headers);

        Http::assertSent(function ($request) {
            $content = $request->data()['messages'][0]['content'];

            return str_contains($content, 'interior');
        });
    }

    public function test_returns_503_when_ai_service_unavailable(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['error' => 'down'], 500)]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.design-items.create'), $headers)
            ->assertOk();

        $response = $this->actingAs($this->user)
            ->post(route('operator.design-items.suggest-description'), [
                'project_id' => (string) $this->project->id,
                'item_type' => 'concept',
            ], $headers);

        $response->assertStatus(503)->assertJson(['success' => false]);
    }

    public function test_denied_without_ai_suggest_permission(): void
    {
        $staff = $this->createTenantUser($this->tenant, [], ['staff'], ['design-item.view', 'design-item.manage']);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($staff)
            ->get(route('operator.design-items.create'), $headers)
            ->assertOk();

        $response = $this->actingAs($staff)
            ->post(route('operator.design-items.suggest-description'), [
                'project_id' => (string) $this->project->id,
                'item_type' => 'concept',
            ], $headers);

        $response->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_denied_without_design_item_manage_permission(): void
    {
        $viewer = $this->createTenantUser($this->tenant, [], ['viewer'], ['design-item.view', 'ai.suggest']);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($viewer)
            ->get(route('operator.design-items.index'), $headers)
            ->assertOk();

        $response = $this->actingAs($viewer)
            ->post(route('operator.design-items.suggest-description'), [
                'project_id' => (string) $this->project->id,
                'item_type' => 'concept',
            ], $headers);

        $response->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_returns_422_for_project_in_another_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = $this->createTenantUser($otherTenant, [], ['admin'], ['design-item.view', 'design-item.manage', 'ai.suggest']);

        $headers = ['X-Tenant-ID' => (string) $otherTenant->id];

        $this->actingAs($otherUser)
            ->get(route('operator.design-items.create'), $headers)
            ->assertOk();

        $response = $this->actingAs($otherUser)
            ->post(route('operator.design-items.suggest-description'), [
                'project_id' => (string) $this->project->id,
                'item_type' => 'concept',
            ], $headers);

        $response->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_requires_authentication(): void
    {
        $this->get(route('login'))->assertOk();

        $this->post(route('operator.design-items.suggest-description'))
            ->assertRedirect();
    }
}
