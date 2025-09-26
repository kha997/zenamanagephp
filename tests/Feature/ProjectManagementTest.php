<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{User, Project, Tenant};

class ProjectManagementTest extends TestCase
{
    public function test_user_can_create_project_in_their_tenant()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();
        
        $response = $this->actingAs($user)
            ->post('/api/projects', [
                'name' => 'Test Project',
                'description' => 'Test Description',
                'start_date' => now()->addDay(),
                'end_date' => now()->addMonth()
            ]);
            
        $response->assertStatus(201);
        $this->assertDatabaseHas('projects', [
            'name' => 'Test Project',
            'tenant_id' => $tenant->id
        ]);
    }
}