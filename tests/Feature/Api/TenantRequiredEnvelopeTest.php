<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthenticationTrait;

class TenantRequiredEnvelopeTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticationTrait;

    public function test_missing_tenant_header_uses_token_tenant_context(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createRbacAdminUser($tenant);

        $token = $this->apiLoginToken($user, $tenant);

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/projects');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'status',
            'success',
            'data',
        ]);
    }
}
