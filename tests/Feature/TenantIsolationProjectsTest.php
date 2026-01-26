<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantIsolationProjectsTest extends TestCase
{
    use RefreshDatabase;

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

        $token = $userB->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => $tenantB->id,
        ])->getJson('/api/projects');

        $response->assertOk();
        $data = $response->json('data', []);

        $this->assertCount(1, $data, 'Only the tenant B project should be visible.');
        $this->assertEquals($projectB->id, $data[0]['id']);
        $this->assertEquals($tenantB->id, $data[0]['tenant_id']);
        $this->assertNotEquals($projectA->id, $data[0]['id'], 'Tenant A project must not be returned.');
    }
}
