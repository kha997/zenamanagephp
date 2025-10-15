<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;

/**
 * Simple Unit tests for basic functionality
 */
class BasicFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test basic user creation
     */
    public function test_user_creation(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        $this->assertNotNull($user->id);
        $this->assertEquals($tenant->id, $user->tenant_id);
    }

    /**
     * Test basic project creation
     */
    public function test_project_creation(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        // Create project with minimal required fields
        $project = Project::create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Project',
            'code' => 'PRJ-TEST-001',
            'status' => 'active',
            'owner_id' => $user->id,
        ]);
        
        $this->assertNotNull($project->id);
        $this->assertEquals($tenant->id, $project->tenant_id);
        $this->assertEquals('Test Project', $project->name);
    }

    /**
     * Test tenant isolation
     */
    public function test_tenant_isolation(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        
        $user1 = User::factory()->create(['tenant_id' => $tenant1->id]);
        $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);
        
        $project1 = Project::create([
            'tenant_id' => $tenant1->id,
            'name' => 'Project 1',
            'code' => 'PRJ-001',
            'status' => 'active',
            'owner_id' => $user1->id,
        ]);
        
        $project2 = Project::create([
            'tenant_id' => $tenant2->id,
            'name' => 'Project 2',
            'code' => 'PRJ-002',
            'status' => 'active',
            'owner_id' => $user2->id,
        ]);
        
        // User 1 should only see project 1
        $user1Projects = Project::where('tenant_id', $user1->tenant_id)->get();
        $this->assertCount(1, $user1Projects);
        $this->assertEquals('Project 1', $user1Projects->first()->name);
        
        // User 2 should only see project 2
        $user2Projects = Project::where('tenant_id', $user2->tenant_id)->get();
        $this->assertCount(1, $user2Projects);
        $this->assertEquals('Project 2', $user2Projects->first()->name);
    }

    /**
     * Test model relationships
     */
    public function test_model_relationships(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        $project = Project::create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Project',
            'code' => 'PRJ-REL-001',
            'status' => 'active',
            'owner_id' => $user->id,
        ]);
        
        // Test relationships
        $this->assertEquals($tenant->id, $project->tenant->id);
        $this->assertEquals($user->id, $project->owner->id);
    }
}
