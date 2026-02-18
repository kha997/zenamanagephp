<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Project;
use Src\CoreProject\Models\Component;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\AuthenticationTrait;
use Tests\Traits\RouteNameTrait;
use Illuminate\Foundation\Testing\WithFaker;

/**
 * Feature tests cho Component API endpoints
 */
class ComponentApiTest extends TestCase
{
    use DatabaseTrait, AuthenticationTrait, RouteNameTrait, WithFaker;
    
    /**
     * Test get components for project
     */
    public function test_can_get_components_for_project(): void
    {
        $user = $this->actingAsUser();
        
        $project = Project::factory()->create([
            'tenant_id' => $user->tenant_id
        ]);
        
        // Tạo components cho project
        Component::factory()->count(3)->create([
            'project_id' => $project->id
        ]);
        
        $response = $this->getJson($this->v1('projects.components.index', ['projectId' => $project->id]));
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        '*' => [
                            'id',
                            'project_id',
                            'name',
                            'progress_percent',
                            'planned_cost',
                            'actual_cost'
                        ]
                    ]
                ])
                ->assertJson([
                    'status' => 'success'
                ]);
        
        $this->assertCount(3, $response->json('data'));
    }
    
    /**
     * Test create component for project
     */
    public function test_can_create_component_for_project(): void
    {
        $user = $this->actingAsUser();
        
        $project = Project::factory()->create([
            'tenant_id' => $user->tenant_id
        ]);
        
        $componentData = [
            'name' => 'New Component',
            'planned_cost' => 100000.00,
            'progress_percent' => 0
        ];
        
        $response = $this->postJson($this->v1('projects.components.store', ['projectId' => $project->id]), $componentData);

        $response->assertStatus(201)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'name' => 'New Component',
                        'project_id' => $project->id,
                        'planned_cost' => 100000.00
                    ]
                ]);
        
        $this->assertDatabaseHas('components', [
            'name' => 'New Component',
            'project_id' => $project->id
        ]);
    }
    
    /**
     * Test hierarchical component creation
     */
    public function test_can_create_hierarchical_components(): void
    {
        $user = $this->actingAsUser();
        
        $project = Project::factory()->create([
            'tenant_id' => $user->tenant_id
        ]);
        
        // Tạo parent component
        $parentComponent = Component::factory()->create([
            'project_id' => $project->id,
            'name' => 'Parent Component'
        ]);
        
        // Tạo child component
        $childData = [
            'name' => 'Child Component',
            'parent_component_id' => $parentComponent->id,
            'planned_cost' => 50000.00
        ];
        
        $response = $this->postJson($this->v1('projects.components.store', ['projectId' => $project->id]), $childData);

        $response->assertStatus(201)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'name' => 'Child Component',
                        'parent_component_id' => $parentComponent->id
                    ]
                ]);
    }
    
    /**
     * Test update component progress triggers event
     */
    public function test_update_component_progress_triggers_event(): void
    {
        $user = $this->actingAsUser();
        
        $project = Project::factory()->create([
            'tenant_id' => $user->tenant_id
        ]);
        
        $component = Component::factory()->create([
            'project_id' => $project->id,
            'progress_percent' => 0
        ]);
        
        // Mock event dispatcher để verify event được dispatch
        \Event::fake();
        
        $updateData = [
            'progress_percent' => 50
        ];
        
        $response = $this->patchJson($this->v1('components.update', ['id' => $component->id]), $updateData);
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'progress_percent' => 50
                    ]
                ]);
        
        // Verify ComponentProgressUpdated event được dispatch
        \Event::assertDispatched(\Src\CoreProject\Events\ComponentProgressUpdated::class);
    }
}
