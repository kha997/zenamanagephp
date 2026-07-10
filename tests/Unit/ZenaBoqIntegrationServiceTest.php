<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Tenant;
use App\Services\ZenaBoqIntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZenaBoqIntegrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ZenaBoqIntegrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ZenaBoqIntegrationService();
    }

    public function test_authorized_when_tenant_matches_configured_name(): void
    {
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);
        $tenant = Tenant::factory()->create(['name' => 'Z.E.N.A']);

        $this->assertTrue($this->service->isTenantAuthorized((string) $tenant->id));
    }

    public function test_denied_when_tenant_does_not_match(): void
    {
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);
        Tenant::factory()->create(['name' => 'Z.E.N.A']);
        $otherTenant = Tenant::factory()->create(['name' => 'Some Other Company']);

        $this->assertFalse($this->service->isTenantAuthorized((string) $otherTenant->id));
    }

    public function test_fail_closed_when_config_name_is_empty(): void
    {
        config(['zena_boq.integration_tenant_name' => null]);
        $tenant = Tenant::factory()->create(['name' => 'Z.E.N.A']);

        $this->assertFalse($this->service->isTenantAuthorized((string) $tenant->id));
    }

    public function test_fail_closed_when_config_name_matches_no_tenant(): void
    {
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);
        // Deliberately do not create any tenant named Z.E.N.A.
        $someTenant = Tenant::factory()->create(['name' => 'Random Co']);

        $this->assertFalse($this->service->isTenantAuthorized((string) $someTenant->id));
    }

    public function test_fetch_latest_quote_returns_shaped_array_on_success(): void
    {
        config(['zena_boq.base_url' => 'https://zena-boq.example', 'zena_boq.read_api_secret' => 'test-secret']);

        Http::fake([
            'https://zena-boq.example/api/external/projects/*' => Http::response(['id' => 'proj_1', 'code' => 'PRJ-001'], 200),
            'https://zena-boq.example/api/external/quotes/latest*' => Http::response([
                'id' => 'quote_1',
                'subtotal' => 100000000,
                'vatAmount' => 8000000,
                'total' => 108000000,
                'status' => 'ISSUED',
                'calibration' => 'UNCALIBRATED',
                'issuedAt' => '2026-07-10T00:00:00Z',
            ], 200),
        ]);

        $result = (new ZenaBoqIntegrationService())->fetchLatestQuote('PRJ-001');

        $this->assertNotNull($result);
        $this->assertSame('quote_1', $result['id']);
        $this->assertSame(108000000.0, $result['total']);
        $this->assertSame('UNCALIBRATED', $result['calibration']);
    }

    public function test_fetch_latest_quote_returns_null_on_project_not_found(): void
    {
        config(['zena_boq.base_url' => 'https://zena-boq.example', 'zena_boq.read_api_secret' => 'test-secret']);

        Http::fake([
            'https://zena-boq.example/api/external/projects/*' => Http::response(['message' => 'Not Found'], 404),
        ]);

        $this->assertNull((new ZenaBoqIntegrationService())->fetchLatestQuote('MISSING'));
    }

    public function test_fetch_latest_quote_returns_null_when_unreachable(): void
    {
        config(['zena_boq.base_url' => 'https://zena-boq.example', 'zena_boq.read_api_secret' => 'test-secret']);

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $this->assertNull((new ZenaBoqIntegrationService())->fetchLatestQuote('PRJ-001'));
    }

    public function test_fetch_latest_quote_returns_null_when_config_missing(): void
    {
        config(['zena_boq.base_url' => null, 'zena_boq.read_api_secret' => null]);

        $this->assertNull((new ZenaBoqIntegrationService())->fetchLatestQuote('PRJ-001'));
    }
}
