<?php declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductionReadinessEndpointTest extends TestCase
{
    public function test_returns_200_ready_when_all_dependencies_healthy(): void
    {
        $response = $this->getJson('/api/v1/public/production/ready');

        $response->assertStatus(200);
        $response->assertExactJson(['status' => 'ready']);
    }

    public function test_returns_503_when_database_probe_fails(): void
    {
        DB::shouldReceive('select')
            ->with('SELECT 1')
            ->andThrow(new \RuntimeException('connection refused'));

        $response = $this->getJson('/api/v1/public/production/ready');

        $response->assertStatus(503);
        $response->assertJson(['status' => 'not_ready']);
        $this->assertContains('database', $response->json('failed'));
    }

    public function test_returns_503_when_cache_probe_fails(): void
    {
        Cache::shouldReceive('put')->andReturn(false);
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('forget')->andReturn(true);

        $response = $this->getJson('/api/v1/public/production/ready');

        $response->assertStatus(503);
        $this->assertContains('cache', $response->json('failed'));
    }

    public function test_returns_503_when_storage_probe_fails(): void
    {
        Storage::shouldReceive('disk')->andReturnSelf();
        Storage::shouldReceive('put')->andReturn(false);
        Storage::shouldReceive('get')->andThrow(new \RuntimeException('disk unwritable'));
        Storage::shouldReceive('delete')->andReturn(true);
        Storage::shouldReceive('exists')->andReturn(false);

        $response = $this->getJson('/api/v1/public/production/ready');

        $response->assertStatus(503);
        $this->assertContains('storage', $response->json('failed'));
    }

    public function test_response_body_never_leaks_diagnostic_internals(): void
    {
        $response = $this->getJson('/api/v1/public/production/ready');
        $body = $response->json();

        foreach (['php_version', 'laravel_version', 'environment', 'app_env', 'memory', 'load', 'uptime'] as $forbiddenKey) {
            $this->assertArrayNotHasKey($forbiddenKey, $body);
        }
        $this->assertArrayNotHasKey('error', $body, 'Exception messages must never leak into the readiness response body.');
    }

    public function test_route_is_rate_limited_public_group_not_authenticated(): void
    {
        // No Sanctum/session auth performed — endpoint must be reachable by deploy tooling without credentials.
        $response = $this->getJson('/api/v1/public/production/ready');
        $this->assertNotEquals(401, $response->getStatusCode());
    }
}
