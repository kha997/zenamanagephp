<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthenticationTrait;

class TenantRequiredEnvelopeTest extends TestCase
{
    use RefreshDatabase, AuthenticationTrait;

    public function test_missing_tenant_header_returns_error_envelope(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createRbacAdminUser($tenant);

        $token = $this->apiLoginToken($user, $tenant);

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/projects');

        $response->assertStatus(400);
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'TENANT_REQUIRED');
        $response->assertJsonPath('error.message', 'X-Tenant-ID header is required');
        $this->assertNotEmpty($response->json('error.id'));
    }
}
