<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * Regression test for the removal of the mock-data KPI endpoint
 * (Api\KpiController / App\Services\KpiService served 100% hardcoded
 * values behind a real RBAC gate — a data-integrity risk, not just dead
 * code, since callers had no way to know the numbers were fake).
 */
class MockKpiEndpointRemovedTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
    }

    public function test_mock_kpi_index_route_no_longer_exists(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], []);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/universal-frame/kpis', ['X-Tenant-ID' => (string) $tenant->id]);

        $response->assertNotFound();
    }

    public function test_mock_kpi_stats_route_no_longer_exists(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], []);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/universal-frame/kpis/stats', ['X-Tenant-ID' => (string) $tenant->id]);

        $response->assertNotFound();
    }

    public function test_route_name_no_longer_registered(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('api.kpis.index'),
            'api.kpis.index should no longer be a registered route name.'
        );
    }
}
