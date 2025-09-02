<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;
use Src\ChangeRequest\Models\ChangeRequest;
use Src\DocumentManagement\Models\Document;
use Illuminate\Support\Facades\Hash;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test complete project workflow
     */
    public function test_complete_project_workflow(): void
    {
        // 1. Setup tenant and user
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('password123')
        ]);
        
        // 2. Login
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123'
        ]);
        $token = $loginResponse->json('data.token');
        
        // 3. Create project
        $projectResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/projects', [
            'name' => 'Integration Test Project',
            'description' => 'Test project for integration testing',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonths(6)->format('Y-m-d'),
            'status' => 'planning'
        ]);
        
        $projectResponse->assertStatus(201);
        $projectId = $projectResponse->json('data.project.id');
        
        // 4. Create tasks
        $taskResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/tasks', [
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
        $crResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/change-requests', [
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
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        
        $otherLoginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $otherUser->email,
            'password' => 'password'
        ]);
        $otherToken = $otherLoginResponse->json('data.token');
        
        // Other user should not see the project
        $unauthorizedResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $otherToken
        ])->getJson("/api/v1/projects/{$projectId}");
        
        $unauthorizedResponse->assertStatus(404);
    }
}