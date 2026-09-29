<?php

namespace Tests\Feature\GAP057;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use Tests\Traits\AuthenticationTrait;

/**
 * GAP-057: no web request may run migrations or rebuild compiled caches.
 * Artisan is mocked and records every command, so nothing really runs.
 */
final class LaunchEndpointsRunNoArtisanTest extends TestCase
{
    use RefreshDatabase, AuthenticationTrait;

    /** @var list<string> */
    private array $artisanCalls = [];

    private function superAdminRequest(string $method, string $uri)
    {
        $tenant = Tenant::factory()->create();
        $root = $this->createTenantUser($tenant, ['email' => 'root@gap057.example.com'], ['super_admin']);

        Artisan::shouldReceive('call')->andReturnUsing(function (string $command) {
            $this->artisanCalls[] = $command;

            return 0;
        });

        return $this->actingAs($root)
            ->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
            ->json($method, $uri, [], ['X-Tenant-ID' => (string) $tenant->id]);
    }

    public function test_launch_report_is_read_only_and_reports_readiness(): void
    {
        $response = $this->superAdminRequest('GET', '/api/v1/final-integration/launch-report');

        $response->assertOk();
        $this->assertSame([], $this->artisanCalls, 'GET launch-report must not run any Artisan command');
        $this->assertIsArray($response->json('pre_launch_readiness'));
        $this->assertNull($response->json('pre_launch_actions'));
    }

    public function test_pre_launch_actions_refuses_without_running_anything(): void
    {
        $response = $this->superAdminRequest('POST', '/api/v1/final-integration/pre-launch-actions');

        $response->assertStatus(409)->assertJson(['success' => false]);
        $this->assertSame([], $this->artisanCalls, 'pre-launch-actions must not run any Artisan command');
        $this->assertIsArray($response->json('error.details.data.readiness'));
    }
}
