<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Http\Middleware\TenantIsolationMiddleware;
use Tests\TestCase;

final class LegacyDeprecationHeadersTest extends TestCase
{
    private function disableAuthRelatedMiddleware(): void
    {
        $this->withoutMiddleware([
            Authenticate::class,
            TenantIsolationMiddleware::class,
            RoleBasedAccessControlMiddleware::class,
        ]);
    }

    public function test_legacy_project_route_returns_deprecation_headers(): void
    {
        $this->disableAuthRelatedMiddleware();
        $response = $this->get('/api/projects');

        $response->assertHeader('Deprecation', 'true');
        $response->assertHeader('X-API-Legacy', '1');
    }

    public function test_legacy_documents_route_returns_deprecation_headers(): void
    {
        $this->disableAuthRelatedMiddleware();

        $response = $this->get('/api/zena/documents');

        $response->assertHeader('Deprecation', 'true');
        $response->assertHeader('X-API-Legacy', '1');
    }

    public function test_legacy_inspections_route_returns_deprecation_headers(): void
    {
        putenv('API_CANONICAL_INSPECTIONS=0');
        $this->refreshApplication();
        $this->disableAuthRelatedMiddleware();

        $response = $this->get('/api/zena/inspections');

        $response->assertHeader('Deprecation', 'true');
        $response->assertHeader('X-API-Legacy', '1');
    }

    public function test_api_v1_projects_route_skips_deprecation_headers(): void
    {
        $this->disableAuthRelatedMiddleware();
        $response = $this->get('/api/v1/projects');

        $response->assertHeaderMissing('Deprecation');
        $response->assertHeaderMissing('X-API-Legacy');
    }

    public function test_api_v1_documents_route_skips_deprecation_headers(): void
    {
        $this->disableAuthRelatedMiddleware();
        $response = $this->get('/api/v1/documents');

        $response->assertHeaderMissing('Deprecation');
        $response->assertHeaderMissing('X-API-Legacy');
    }

    public function test_api_v1_inspections_route_skips_deprecation_headers(): void
    {
        $this->disableAuthRelatedMiddleware();
        $response = $this->get('/api/v1/inspections');

        $response->assertHeaderMissing('Deprecation');
        $response->assertHeaderMissing('X-API-Legacy');
    }
}
