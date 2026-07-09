<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\DesignItem;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class DesignItemApiTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private Project $projectA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->userA = $this->createTenantUser($this->tenantA, [], ['admin'], ['design-item.view', 'design-item.manage']);
        $this->userB = $this->createTenantUser($this->tenantB, [], ['admin'], ['design-item.view', 'design-item.manage']);

        $this->projectA = Project::factory()->create(['tenant_id' => (string) $this->tenantA->id]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson($this->route('index'), [
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $this->tenantA->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_index_denied_without_view_permission(): void
    {
        $noPerm = $this->createTenantUser($this->tenantA, [], ['no_perm'], []);

        $response = $this->getJson($this->route('index'), $this->headersFor($noPerm));

        $response->assertStatus(403);
    }

    public function test_can_create_and_list_design_items(): void
    {
        $response = $this->postJson($this->route('store'), [
            'project_id' => (string) $this->projectA->id,
            'name' => 'Phoi canh mat tien phuong an 2',
            'item_type' => 'concept',
        ], $this->headersFor($this->userA));

        $response->assertStatus(201)
            ->assertJsonPath('data.review_status', DesignItem::STATUS_DRAFT)
            ->assertJsonPath('data.item_type', 'concept');

        $this->assertDatabaseHas('design_items', [
            'name' => 'Phoi canh mat tien phuong an 2',
            'tenant_id' => (string) $this->tenantA->id,
        ]);

        $index = $this->getJson($this->route('index'), $this->headersFor($this->userA));
        $index->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_create_requires_manage_permission(): void
    {
        $viewOnly = $this->createTenantUser($this->tenantA, [], ['viewer'], ['design-item.view']);

        $response = $this->postJson($this->route('store'), [
            'project_id' => (string) $this->projectA->id,
            'name' => 'Should be denied',
        ], $this->headersFor($viewOnly));

        $response->assertStatus(403);
        $this->assertDatabaseMissing('design_items', ['name' => 'Should be denied']);
    }

    public function test_create_rejects_project_from_another_tenant(): void
    {
        $projectB = Project::factory()->create(['tenant_id' => (string) $this->tenantB->id]);

        $response = $this->postJson($this->route('store'), [
            'project_id' => (string) $projectB->id,
            'name' => 'Cross tenant project',
        ], $this->headersFor($this->userA));

        $response->assertStatus(422);
    }

    public function test_design_items_are_tenant_isolated(): void
    {
        DesignItem::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => (string) $this->projectA->id,
            'name' => 'Tenant A item',
            'item_type' => DesignItem::TYPE_OTHER,
            'review_status' => DesignItem::STATUS_DRAFT,
            'created_by' => (string) $this->userA->id,
        ]);

        $response = $this->getJson($this->route('index'), $this->headersFor($this->userB));

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    private function route(string $name, array $parameters = []): string
    {
        return route('api.zena.design-items.' . $name, $parameters, false);
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        $token = $user->createToken('design-item-api-test')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }
}
