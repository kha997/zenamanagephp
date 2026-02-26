<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use Mockery;
use Tests\TestCase;

class PublicHealthEndpointsTest extends TestCase
{
    public function test_health_endpoint_returns_200_and_includes_status_and_timestamp(): void
    {
        $response = $this->getJson('/api/v1/public/health');

        $response->assertOk();
        $this->assertResponseHasStatusAndTimestamp($response->json());
    }

    public function test_health_liveness_endpoint_returns_200_and_includes_status_and_timestamp(): void
    {
        $response = $this->getJson('/api/v1/public/health/liveness');

        $response->assertOk();
        $this->assertResponseHasStatusAndTimestamp($response->json());
    }

    public function test_health_readiness_endpoint_returns_200_and_includes_status_and_timestamp(): void
    {
        $healthCheckService = Mockery::mock('alias:App\Services\HealthCheckService');
        $healthCheckService->shouldReceive('performHealthChecks')->once()->andReturn([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'checks' => [
                'database' => ['status' => 'healthy'],
                'cache' => ['status' => 'healthy'],
                'storage' => ['status' => 'healthy'],
            ],
        ]);

        $response = $this->getJson('/api/v1/public/health/readiness');

        $response->assertOk();
        $this->assertResponseHasStatusAndTimestamp($response->json());
    }

    private function assertResponseHasStatusAndTimestamp(array $payload): void
    {
        $this->assertArrayHasKey('status', $payload);
        $this->assertNotNull(data_get($payload, 'timestamp', data_get($payload, 'checks.timestamp')));
    }
}
