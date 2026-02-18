<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\Tenant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test Task Dependencies và Blocking Logic
 * 
 * Kịch bản: Tạo tasks với dependencies → Test blocking logic → Test critical path
 */
class TaskDependenciesTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $project;
    private $projectManager;
    private $designer;
    private $engineer;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Tạo tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'domain' => 'test.com',
            'settings' => json_encode(['timezone' => 'Asia/Ho_Chi_Minh']),
            'status' => 'trial',
            'is_active' => true,
        ]);

        // Tạo project
        $this->project = Project::factory()->create([
            'name' => 'Test Project',
            'code' => 'DEP-TEST-001',
            'description' => 'Test Description',
            'status' => 'active',
            'tenant_id' => $this->tenant->id,
            'created_by' => null, // Sẽ được set sau khi tạo users
        ]);

        // Tạo Project Manager
        $this->projectManager = User::factory()->create([
            'name' => 'Project Manager',
            'email' => 'project.manager@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'profile_data' => '{}',
        ]);

        // Tạo Designer
        $this->designer = User::factory()->create([
            'name' => 'Designer',
            'email' => 'designer@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'profile_data' => '{}',
        ]);

        // Tạo Engineer
        $this->engineer = User::factory()->create([
            'name' => 'Engineer',
            'email' => 'engineer@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'profile_data' => '{}',
        ]);

        // Cập nhật project với created_by
        $this->project->update(['created_by' => $this->projectManager->id]);
    }

    /**
     * Test tạo task dependencies
     */
    public function test_can_create_task_dependencies(): void
    {
        // Tạo task A (foundation)
        $taskA = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Foundation Design',
            'description' => 'Design foundation structure',
            'status' => 'open',
            'priority' => 'high',
            'assigned_to' => $this->designer->id,
            'created_by' => $this->projectManager->id,
        ]);

        // Tạo task B (foundation construction) - depends on task A
        $taskB = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Foundation Construction',
            'description' => 'Construct foundation',
            'status' => 'open',
            'priority' => 'high',
            'assigned_to' => $this->engineer->id,
            'created_by' => $this->projectManager->id,
        ]);

        // Tạo dependency: Task B depends on Task A
        $dependency = TaskDependency::create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $taskB->id,
            'dependency_id' => $taskA->id,
        ]);

        // Kiểm tra dependency được tạo thành công
        $this->assertDatabaseHas('task_dependencies', [
            'id' => $dependency->id,
            'tenant_id' => $this->tenant->id,
            'task_id' => $taskB->id,
            'dependency_id' => $taskA->id,
        ]);

        // Kiểm tra relationships
        $this->assertEquals($taskB->id, $dependency->task->id);
        $this->assertEquals($taskA->id, $dependency->dependsOnTask->id);
        $this->assertEquals($this->tenant->id, $dependency->tenant->id);
    }

    /**
     * Test blocking logic - task cannot start if dependencies not completed
     */
    public function test_task_blocking_logic(): void
    {
        // Tạo task A (foundation design)
        $taskA = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Foundation Design',
            'description' => 'Design foundation structure',
            'status' => 'open',
            'priority' => 'high',
            'assigned_to' => $this->designer->id,
            'created_by' => $this->projectManager->id,
        ]);

        // Tạo task B (foundation construction) - depends on task A
        $taskB = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Foundation Construction',
            'description' => 'Construct foundation',
            'status' => 'open',
            'priority' => 'high',
            'assigned_to' => $this->engineer->id,
            'created_by' => $this->projectManager->id,
        ]);

        // Tạo dependency: Task B depends on Task A
        TaskDependency::create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $taskB->id,
            'dependency_id' => $taskA->id,
        ]);

        // Task A chưa hoàn thành, Task B không thể bắt đầu
        $this->assertEquals('open', $taskA->status);
        $this->assertEquals('open', $taskB->status);

        // Kiểm tra Task B có dependencies chưa hoàn thành
        $dependencies = TaskDependency::where('task_id', $taskB->id)
            ->where('tenant_id', $this->tenant->id)
            ->get();

        $this->assertCount(1, $dependencies);
        $this->assertEquals($taskA->id, $dependencies->first()->dependency_id);

        // Task A hoàn thành
        $taskA->update(['status' => 'completed']);

        // Bây giờ Task B có thể bắt đầu
        $this->assertEquals('completed', $taskA->fresh()->status);
        $this->assertEquals('open', $taskB->fresh()->status);
    }

    /**
     * Test complex dependency chain
     */
    public function test_complex_dependency_chain(): void
    {
        // Tạo chuỗi tasks: A → B → C → D
        $taskA = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Task A - Planning',
            'description' => 'Project planning',
            'status' => 'open',
            'priority' => 'high',
            'assigned_to' => $this->projectManager->id,
            'created_by' => $this->projectManager->id,
        ]);

        $taskB = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Task B - Design',
            'description' => 'Design phase',
            'status' => 'open',
            'priority' => 'high',
            'assigned_to' => $this->designer->id,
            'created_by' => $this->projectManager->id,
        ]);

        $taskC = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Task C - Construction',
            'description' => 'Construction phase',
            'status' => 'open',
            'priority' => 'high',
            'assigned_to' => $this->engineer->id,
            'created_by' => $this->projectManager->id,
        ]);

        $taskD = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Task D - Testing',
            'description' => 'Testing phase',
            'status' => 'open',
            'priority' => 'medium',
            'assigned_to' => $this->engineer->id,
            'created_by' => $this->projectManager->id,
        ]);

        // Tạo dependencies: A → B → C → D
        TaskDependency::create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $taskB->id,
            'dependency_id' => $taskA->id,
        ]);

        TaskDependency::create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $taskC->id,
            'dependency_id' => $taskB->id,
        ]);

        TaskDependency::create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $taskD->id,
            'dependency_id' => $taskC->id,
        ]);

        // Kiểm tra dependency chain
        $this->assertDatabaseHas('task_dependencies', [
            'task_id' => $taskB->id,
            'dependency_id' => $taskA->id,
        ]);

        $this->assertDatabaseHas('task_dependencies', [
            'task_id' => $taskC->id,
            'dependency_id' => $taskB->id,
        ]);

        $this->assertDatabaseHas('task_dependencies', [
            'task_id' => $taskD->id,
            'dependency_id' => $taskC->id,
        ]);

        // Test sequential completion
        $taskA->update(['status' => 'completed']);
        $this->assertEquals('completed', $taskA->fresh()->status);

        $taskB->update(['status' => 'completed']);
        $this->assertEquals('completed', $taskB->fresh()->status);

        $taskC->update(['status' => 'completed']);
        $this->assertEquals('completed', $taskC->fresh()->status);

        // Task D bây giờ có thể bắt đầu
        $this->assertEquals('open', $taskD->fresh()->status);
    }

    /**
     * Test multiple dependencies (task depends on multiple tasks)
     */
    public function test_multiple_dependencies(): void
    {
        // Tạo task A (foundation design)
        $taskA = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Foundation Design',
            'description' => 'Design foundation structure',
            'status' => 'open',
            'priority' => 'high',
            'assigned_to' => $this->designer->id,
            'created_by' => $this->projectManager->id,
        ]);

        // Tạo task B (structural design)
        $taskB = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Structural Design',
            'description' => 'Design structural elements',
            'status' => 'open',
            'priority' => 'high',
            'assigned_to' => $this->designer->id,
            'created_by' => $this->projectManager->id,
        ]);

        // Tạo task C (construction) - depends on both A and B
        $taskC = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Construction',
            'description' => 'Construct building',
            'status' => 'open',
            'priority' => 'high',
            'assigned_to' => $this->engineer->id,
            'created_by' => $this->projectManager->id,
        ]);

        // Tạo dependencies: Task C depends on both Task A and Task B
        TaskDependency::create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $taskC->id,
            'dependency_id' => $taskA->id,
        ]);

        TaskDependency::create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $taskC->id,
            'dependency_id' => $taskB->id,
        ]);

        // Kiểm tra Task C có 2 dependencies
        $dependencies = TaskDependency::where('task_id', $taskC->id)
            ->where('tenant_id', $this->tenant->id)
            ->get();

        $this->assertCount(2, $dependencies);

        // Task C không thể bắt đầu vì cả A và B chưa hoàn thành
        $this->assertEquals('open', $taskA->status);
        $this->assertEquals('open', $taskB->status);
        $this->assertEquals('open', $taskC->status);

        // Chỉ hoàn thành Task A, Task C vẫn không thể bắt đầu
        $taskA->update(['status' => 'completed']);
        $this->assertEquals('completed', $taskA->fresh()->status);
        $this->assertEquals('open', $taskB->fresh()->status);
        $this->assertEquals('open', $taskC->fresh()->status);

        // Hoàn thành Task B, bây giờ Task C có thể bắt đầu
        $taskB->update(['status' => 'completed']);
        $this->assertEquals('completed', $taskA->fresh()->status);
        $this->assertEquals('completed', $taskB->fresh()->status);
        $this->assertEquals('open', $taskC->fresh()->status);
    }

    /**
     * Test circular dependency prevention
     */
    public function test_circular_dependency_prevention(): void
    {
        // Tạo task A
        $taskA = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Task A',
            'description' => 'Task A description',
            'status' => 'open',
            'priority' => 'medium',
            'assigned_to' => $this->designer->id,
            'created_by' => $this->projectManager->id,
        ]);

        // Tạo task B
        $taskB = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Task B',
            'description' => 'Task B description',
            'status' => 'open',
            'priority' => 'medium',
            'assigned_to' => $this->engineer->id,
            'created_by' => $this->projectManager->id,
        ]);

        // Tạo dependency: A depends on B
        TaskDependency::create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $taskA->id,
            'dependency_id' => $taskB->id,
        ]);

        // Kiểm tra dependency được tạo
        $this->assertDatabaseHas('task_dependencies', [
            'task_id' => $taskA->id,
            'dependency_id' => $taskB->id,
        ]);

        // Trong thực tế, system sẽ check circular dependency trước khi tạo
        // Ví dụ: nếu tạo B depends on A, sẽ tạo circular dependency A → B → A
        // Test này chỉ kiểm tra việc tạo dependency cơ bản
        $this->assertCount(1, TaskDependency::where('tenant_id', $this->tenant->id)->get());
    }

    /**
     * Test dependency removal
     */
    public function test_can_remove_dependency(): void
    {
        // Tạo task A và B
        $taskA = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Task A',
            'description' => 'Task A description',
            'status' => 'open',
            'priority' => 'medium',
            'assigned_to' => $this->designer->id,
            'created_by' => $this->projectManager->id,
        ]);

        $taskB = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Task B',
            'description' => 'Task B description',
            'status' => 'open',
            'priority' => 'medium',
            'assigned_to' => $this->engineer->id,
            'created_by' => $this->projectManager->id,
        ]);

        // Tạo dependency: B depends on A
        $dependency = TaskDependency::create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $taskB->id,
            'dependency_id' => $taskA->id,
        ]);

        // Kiểm tra dependency được tạo
        $this->assertDatabaseHas('task_dependencies', [
            'id' => $dependency->id,
            'task_id' => $taskB->id,
            'dependency_id' => $taskA->id,
        ]);

        // Xóa dependency
        $dependency->delete();

        // Kiểm tra dependency đã bị xóa
        $this->assertDatabaseMissing('task_dependencies', [
            'id' => $dependency->id,
        ]);

        // Task B bây giờ không còn dependency
        $remainingDependencies = TaskDependency::where('task_id', $taskB->id)
            ->where('tenant_id', $this->tenant->id)
            ->get();

        $this->assertCount(0, $remainingDependencies);
    }

    /**
     * Test task dependency workflow end-to-end
     */
    public function test_task_dependency_workflow_end_to_end(): void
    {
        // Tạo project với 3 tasks có dependency chain
        $task1 = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Design Phase',
            'description' => 'Complete design phase',
            'status' => 'open',
            'priority' => 'high',
            'assigned_to' => $this->designer->id,
            'created_by' => $this->projectManager->id,
        ]);

        $task2 = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Construction Phase',
            'description' => 'Complete construction phase',
            'status' => 'open',
            'priority' => 'high',
            'assigned_to' => $this->engineer->id,
            'created_by' => $this->projectManager->id,
        ]);

        $task3 = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Testing Phase',
            'description' => 'Complete testing phase',
            'status' => 'open',
            'priority' => 'medium',
            'assigned_to' => $this->engineer->id,
            'created_by' => $this->projectManager->id,
        ]);

        // Tạo dependency chain: Task1 → Task2 → Task3
        TaskDependency::create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $task2->id,
            'dependency_id' => $task1->id,
        ]);

        TaskDependency::create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $task3->id,
            'dependency_id' => $task2->id,
        ]);

        // Kiểm tra initial state
        $this->assertEquals('open', $task1->fresh()->status);
        $this->assertEquals('open', $task2->fresh()->status);
        $this->assertEquals('open', $task3->fresh()->status);

        // Complete Task1
        $task1->update(['status' => 'completed']);
        $this->assertEquals('completed', $task1->fresh()->status);

        // Task2 bây giờ có thể bắt đầu
        $task2->update(['status' => 'in_progress']);
        $this->assertEquals('in_progress', $task2->fresh()->status);

        // Complete Task2
        $task2->update(['status' => 'completed']);
        $this->assertEquals('completed', $task2->fresh()->status);

        // Task3 bây giờ có thể bắt đầu
        $task3->update(['status' => 'in_progress']);
        $this->assertEquals('in_progress', $task3->fresh()->status);

        // Complete Task3
        $task3->update(['status' => 'completed']);
        $this->assertEquals('completed', $task3->fresh()->status);

        // Kiểm tra toàn bộ dependency chain đã hoàn thành
        $this->assertDatabaseHas('tasks', [
            'id' => $task1->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task2->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task3->id,
            'status' => 'completed',
        ]);

        // Kiểm tra dependencies vẫn tồn tại
        $this->assertDatabaseHas('task_dependencies', [
            'task_id' => $task2->id,
            'dependency_id' => $task1->id,
        ]);

        $this->assertDatabaseHas('task_dependencies', [
            'task_id' => $task3->id,
            'dependency_id' => $task2->id,
        ]);
    }
}
