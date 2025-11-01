<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Task;
use App\Models\Project;
use App\Models\User;
use App\Models\Tenant;
use App\Models\TaskDependency;
use App\Services\TaskDependencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaskDependencyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $user;
    protected $project;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        $this->service = new TaskDependencyService();
    }

    /** @test */
    public function it_can_add_task_dependency()
    {
        $task1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        $result = $this->service->addDependency($task1->id, $task2->id, $this->tenant->id);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('task_dependencies', [
            'task_id' => $task1->id,
            'dependency_id' => $task2->id,
            'tenant_id' => $this->tenant->id
        ]);
    }

    /** @test */
    public function it_prevents_self_dependency()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        $result = $this->service->addDependency($task->id, $task->id, $this->tenant->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('Task cannot depend on itself', $result['message']);
    }

    /** @test */
    public function it_prevents_simple_circular_dependency()
    {
        $task1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        // Add first dependency: task1 depends on task2
        $this->service->addDependency($task1->id, $task2->id, $this->tenant->id);

        // Try to add circular dependency: task2 depends on task1
        $result = $this->service->addDependency($task2->id, $task1->id, $this->tenant->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('Circular dependency detected', $result['message']);
    }

    /** @test */
    public function it_prevents_complex_circular_dependency()
    {
        $task1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        $task3 = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        // Create chain: task1 -> task2 -> task3
        $this->service->addDependency($task1->id, $task2->id, $this->tenant->id);
        $this->service->addDependency($task2->id, $task3->id, $this->tenant->id);

        // Try to create circular dependency: task3 -> task1
        $result = $this->service->addDependency($task3->id, $task1->id, $this->tenant->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('Circular dependency detected', $result['message']);
    }

    /** @test */
    public function it_allows_valid_dependency_chain()
    {
        $task1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        $task3 = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        // Create valid chain: task1 -> task2 -> task3
        $result1 = $this->service->addDependency($task1->id, $task2->id, $this->tenant->id);
        $result2 = $this->service->addDependency($task2->id, $task3->id, $this->tenant->id);

        $this->assertTrue($result1['success']);
        $this->assertTrue($result2['success']);
    }

    /** @test */
    public function it_can_remove_task_dependency()
    {
        $task1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        // Add dependency first
        $this->service->addDependency($task1->id, $task2->id, $this->tenant->id);

        // Remove dependency
        $result = $this->service->removeDependency($task1->id, $task2->id, $this->tenant->id);

        $this->assertTrue($result['success']);
        $this->assertDatabaseMissing('task_dependencies', [
            'task_id' => $task1->id,
            'dependency_id' => $task2->id,
            'tenant_id' => $this->tenant->id
        ]);
    }

    /** @test */
    public function it_can_get_task_dependencies()
    {
        $task1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        $task3 = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        // Add dependencies
        $this->service->addDependency($task1->id, $task2->id, $this->tenant->id);
        $this->service->addDependency($task1->id, $task3->id, $this->tenant->id);

        $dependencies = $this->service->getDependencies($task1->id, $this->tenant->id);

        $this->assertCount(2, $dependencies);
        $this->assertTrue($dependencies->contains($task2));
        $this->assertTrue($dependencies->contains($task3));
    }

    /** @test */
    public function it_can_get_task_dependents()
    {
        $task1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        $task3 = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        // Add dependencies
        $this->service->addDependency($task2->id, $task1->id, $this->tenant->id);
        $this->service->addDependency($task3->id, $task1->id, $this->tenant->id);

        $dependents = $this->service->getDependents($task1->id, $this->tenant->id);

        $this->assertCount(2, $dependents);
        $this->assertTrue($dependents->contains($task2));
        $this->assertTrue($dependents->contains($task3));
    }

    /** @test */
    public function it_can_validate_task_status_update()
    {
        $task1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'pending',
            'tenant_id' => $this->tenant->id
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'pending',
            'tenant_id' => $this->tenant->id
        ]);

        // Add dependency: task1 depends on task2
        $this->service->addDependency($task1->id, $task2->id, $this->tenant->id);

        // Try to complete task1 while task2 is still pending
        $result = $this->service->validateStatusUpdate($task1->id, 'completed', $this->tenant->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('Cannot complete task: dependencies are not completed', $result['message']);

        // Complete task2 first
        $task2->update(['status' => 'completed']);

        // Now task1 can be completed
        $result = $this->service->validateStatusUpdate($task1->id, 'completed', $this->tenant->id);

        $this->assertTrue($result['success']);
    }

    /** @test */
    public function it_can_get_blocked_tasks()
    {
        $task1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'pending',
            'tenant_id' => $this->tenant->id
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'pending',
            'tenant_id' => $this->tenant->id
        ]);

        $task3 = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'pending',
            'tenant_id' => $this->tenant->id
        ]);

        // Create dependencies: task2 and task3 depend on task1
        $this->service->addDependency($task2->id, $task1->id, $this->tenant->id);
        $this->service->addDependency($task3->id, $task1->id, $this->tenant->id);

        $blockedTasks = $this->service->getBlockedTasks($this->tenant->id);

        $this->assertCount(2, $blockedTasks);
        $this->assertTrue($blockedTasks->contains('id', $task2->id));
        $this->assertTrue($blockedTasks->contains('id', $task3->id));
    }

    /** @test */
    public function it_can_get_critical_path()
    {
        $task1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'estimated_hours' => 10,
            'tenant_id' => $this->tenant->id
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'estimated_hours' => 20,
            'tenant_id' => $this->tenant->id
        ]);

        $task3 = Task::factory()->create([
            'project_id' => $this->project->id,
            'estimated_hours' => 15,
            'tenant_id' => $this->tenant->id
        ]);

        // Create dependencies: task1 -> task2 -> task3
        $this->service->addDependency($task1->id, $task2->id, $this->tenant->id);
        $this->service->addDependency($task2->id, $task3->id, $this->tenant->id);

        $criticalPath = $this->service->getCriticalPath($this->project->id, $this->tenant->id);

        $this->assertCount(3, $criticalPath);
        // Critical path should be in topological order (dependencies first)
        $this->assertEquals($task3->id, $criticalPath[0]->id); // task3 has no dependencies
        $this->assertEquals($task2->id, $criticalPath[1]->id); // task2 depends on task3
        $this->assertEquals($task1->id, $criticalPath[2]->id); // task1 depends on task2
    }

    /** @test */
    public function it_handles_nonexistent_tasks()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        $result = $this->service->addDependency($task->id, 'nonexistent-id', $this->tenant->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('Dependent task not found', $result['message']);
    }

    /** @test */
    public function it_handles_cross_tenant_access()
    {
        $otherTenant = Tenant::factory()->create();
        
        $task1 = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        $task2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $otherTenant->id
        ]);

        $result = $this->service->addDependency($task1->id, $task2->id, $this->tenant->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('Cannot create dependency across tenants', $result['message']);
    }
}
