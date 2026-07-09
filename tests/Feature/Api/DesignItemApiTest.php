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
