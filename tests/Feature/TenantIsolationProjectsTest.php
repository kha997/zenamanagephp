<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthenticationTrait;

class TenantIsolationProjectsTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticationTrait;

    public function test_projects_list_isolation_by_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->forTenant((string) $tenantA->id)->create();
        $userB = User::factory()->forTenant((string) $tenantB->id)->create();

        $role = Role::factory()->create([
            'name' => 'team_member',
            'scope' => 'system',
            'is_active' => true,
        ]);

        $userB->roles()->attach($role->id);

        $projectA = Project::factory()->create([
            'tenant_id' => $tenantA->id,
            'created_by' => $userA->id,
            'pm_id' => $userA->id,
        ]);

        $projectB = Project::factory()->create([
            'tenant_id' => $tenantB->id,
            'created_by' => $userB->id,
            'pm_id' => $userB->id,
        ]);

        $this->actingAs($userB);
        $this->apiAs($userB, $tenantB);

        $response = $this
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Tenant-ID' => (string) $tenantB->id,
            ])
            ->getJson('/api/projects');

        $response->assertOk();
        $data = $response->json('data', []);

        $this->assertCount(1, $data, 'Only the tenant B project should be visible.');
        $this->assertEquals($projectB->id, $data[0]['id']);
        $this->assertEquals($tenantB->id, $data[0]['tenant_id']);
        $this->assertNotEquals($projectA->id, $data[0]['id'], 'Tenant A project must not be returned.');

        $this->apiHeaders = [];

        $missingTenantResponse = $this
            ->flushHeaders()
            ->getJson('/api/projects');

        $missingTenantResponse->assertOk();
        $dataWithoutHeader = $missingTenantResponse->json('data', []);

        $this->assertCount(
            1,
            $dataWithoutHeader,
            'Tenant fallback should still return the authenticated tenant project.'
        );
        $this->assertEquals(
            $projectB->id,
            $dataWithoutHeader[0]['id'],
            'Tenant B project must remain the only visible project even when the header is dropped.'
        );
    }
}
