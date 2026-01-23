<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\RbacTestTrait;

class TenantIsolationProjectsTest extends TestCase
{
    use RefreshDatabase;
    use RbacTestTrait;

    public function test_projects_list_isolation_by_tenant(): void
    {
        $originalEnv = getenv('RBAC_BYPASS_TESTING');
        $originalConfig = config('rbac.bypass_testing');

        putenv('RBAC_BYPASS_TESTING=0');
        $_ENV['RBAC_BYPASS_TESTING'] = '0';
        $_SERVER['RBAC_BYPASS_TESTING'] = '0';
        config(['rbac.bypass_testing' => false]);

        try {
            $tenantA = Tenant::factory()->create();
            $tenantB = Tenant::factory()->create();

            $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
            $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

            $this->grantPermissionsByCode($userA, ['project.read']);
            $this->grantRole($userA, 'team_member');

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

            Sanctum::actingAs($userA);

            $response = $this->withHeaders([
                'X-Tenant-ID' => (string) $tenantA->id,
            ])->getJson('/api/projects');

            $response->assertOk();
            $data = $response->json('data', []);

            $this->assertCount(1, $data, 'Only the tenant A project should be visible.');
            $this->assertEquals($projectA->id, $data[0]['id']);
            $this->assertEquals($tenantA->id, $data[0]['tenant_id']);
            $this->assertNotEquals($projectB->id, $data[0]['id'], 'Tenant B project must not be returned.');
        } finally {
            if ($originalEnv === false) {
                putenv('RBAC_BYPASS_TESTING');
                unset($_ENV['RBAC_BYPASS_TESTING'], $_SERVER['RBAC_BYPASS_TESTING']);
            } else {
                putenv("RBAC_BYPASS_TESTING={$originalEnv}");
                $_ENV['RBAC_BYPASS_TESTING'] = $originalEnv;
                $_SERVER['RBAC_BYPASS_TESTING'] = $originalEnv;
            }

            config(['rbac.bypass_testing' => $originalConfig]);
        }
    }
}
