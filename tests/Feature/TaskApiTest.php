<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthenticationTrait;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticationTrait;

    /** @test */
    public function test_api_tasks_index_requires_authentication(): void
    {
        $this->getJson('/api/tasks')->assertStatus(401);
    }

    /** @test */
    public function test_api_tasks_index_rejects_tenant_header_mismatch(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = $this->createRbacAdminUser($tenantA);

        $token = $this->apiLoginToken($userA, $tenantA);

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => (string) $tenantB->id,
        ])->getJson('/api/tasks');

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'TENANT_INVALID');
    }
}
