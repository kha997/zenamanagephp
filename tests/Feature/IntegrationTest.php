<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiActingAsTenantAdmin();
        $this->user = $this->apiFeatureUser;
        $this->tenantId = $this->apiFeatureTenant->id;
    }

    /**
     * Test complete project workflow
     */
    public function test_complete_project_workflow(): void
    {
        // 3. Create project
        $projectResponse = $this->apiPost('/api/v1/projects', [
            'name' => 'Integration Test Project',
            'description' => 'Test project for integration testing',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonths(6)->format('Y-m-d'),
            'status' => 'planning'
        ]);
        
        $projectResponse->assertStatus(201);
        $projectId = $projectResponse->json('data.project.id');
        
        // 4. Create tasks
        $taskResponse = $this->apiPost('/api/v1/tasks', [
            'name' => 'Integration Test Task',
            'description' => 'Test task for integration testing',
            'project_id' => $projectId,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addWeeks(2)->format('Y-m-d'),
            'status' => 'pending'
        ]);
        
        $taskResponse->assertStatus(201);
        $taskId = $taskResponse->json('data.task.id');
        
        // 5. Create change request
        $crResponse = $this->apiPost('/api/v1/change-requests', [
            'title' => 'Integration Test CR',
            'description' => 'Test change request',
            'project_id' => $projectId,
            'impact_days' => 3,
            'impact_cost' => 10000
        ]);
        
        $crResponse->assertStatus(201);
        
        // 6. Verify data integrity
        $this->assertDatabaseHas('projects', ['id' => $projectId]);
        $this->assertDatabaseHas('tasks', ['id' => $taskId]);
        $this->assertDatabaseHas('change_requests', ['project_id' => $projectId]);
        
        // 7. Test tenant isolation
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'password' => Hash::make('password'),
            'is_active' => true,
            'role' => 'super_admin',
        ]);

        $robustRole = \App\Models\Role::firstOrCreate(
            ['name' => 'super_admin'],
            ['scope' => 'system', 'description' => 'Super Admin', 'is_active' => true]
        );
        $otherUser->roles()->syncWithoutDetaching($robustRole->id);
        $this->ensureRoleHasTenantPermissions('super_admin');
        $this->ensureRoleHasTenantPermissions('Admin');
        
        $otherLoginResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $otherTenant->id,
        ])->postJson('/api/auth/login', [
            'email' => $otherUser->email,
            'password' => 'password'
        ]);
        $otherLoginResponse->assertStatus(200);
        $otherToken = $this->extractLoginToken($otherLoginResponse);
        
        // Other user should not see the project
        $unauthorizedResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $otherTenant->id,
            'Authorization' => 'Bearer ' . $otherToken
        ])->getJson("/api/v1/projects/{$projectId}");
        
        $unauthorizedResponse->assertStatus(404);
    }
}
