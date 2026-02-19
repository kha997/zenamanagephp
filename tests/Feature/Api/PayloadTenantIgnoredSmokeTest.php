<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthenticationTrait;
use Tests\Traits\RouteNameTrait;

class PayloadTenantIgnoredSmokeTest extends TestCase
{
    use RefreshDatabase, AuthenticationTrait, RouteNameTrait;

    public function test_payload_tenant_id_is_overridden_by_context(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $user = $this->createTenantUser(
            $tenantA,
            ['role' => 'admin'],
            ['admin'],
            ['submittal.create', 'submittal.view']
        );

        $project = Project::factory()->create([
            'tenant_id' => $tenantA->id,
            'created_by' => $user->id,
            'pm_id' => $user->id,
        ]);

        $token = $this->apiLoginToken($user, $tenantA);

        $payload = [
            'tenant_id' => $tenantB->id,
            'project_id' => $project->id,
            'title' => 'Tenant locking submittal',
            'description' => 'Ensure tenant payload ignored',
            'submittal_type' => 'shop_drawing',
        ];

        $headers = array_merge(
            $this->apiHeadersForTenant((string) $tenantA->id),
            ['Authorization' => 'Bearer ' . $token]
        );

        $response = $this->withHeaders($headers)->postJson($this->zena('submittals.store'), $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('data.tenant_id', $tenantA->id);

        $this->assertDatabaseHas('submittals', [
            'tenant_id' => $tenantA->id,
            'project_id' => $project->id,
            'title' => 'Tenant locking submittal',
        ]);
    }
}
