<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\ProjectMilestone;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TaskDependencyService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/**
 * Timeline & Scheduling (Gantt Chart) Test
 * 
 * Tests the timeline and scheduling functionality including:
 * - Task scheduling and timeline management
 * - Gantt chart data generation
 * - Critical path calculation
 * - Milestone tracking
 * - Dependency visualization
 * - Timeline optimization
 */
class TimelineSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $user;
    protected $project;
    protected $dependencyService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'status' => 'active'
        ]);

        $this->user = User::factory()->create([
            'name' => 'Project Manager',
            'email' => 'pm@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id
        ]);

        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'TIMELINE-001',
            'name' => 'Timeline Test Project',
            'description' => 'Test project for timeline and scheduling',
            'start_date' => now(),
            'end_date' => now()->addMonths(6),
            'status' => 'active',
            'budget_total' => 500000.00
        ]);

        $this->dependencyService = new TaskDependencyService();
    }

    /**
     * Test basic task scheduling functionality
     */
    public function test_can_schedule_tasks_with_timeline(): void
    {
        // Create tasks with specific start and end dates
        $task1 = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Foundation Work',
            'description' => 'Foundation construction',
            'start_date' => now(),
            'end_date' => now()->addDays(10),
            'status' => 'pending',
            'priority' => 'high',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id
        ]);

        $task2 = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Structural Work',
            'description' => 'Structural construction',
            'start_date' => now()->addDays(11),
            'end_date' => now()->addDays(25),
            'status' => 'pending',
            'priority' => 'high',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id
        ]);

        // Test timeline data
        $this->assertEquals(now()->format('Y-m-d'), $task1->start_date->format('Y-m-d'));
        $this->assertEquals(now()->addDays(10)->format('Y-m-d'), $task1->end_date->format('Y-m-d'));
        $this->assertEquals(now()->addDays(11)->format('Y-m-d'), $task2->start_date->format('Y-m-d'));
        $this->assertEquals(now()->addDays(25)->format('Y-m-d'), $task2->end_date->format('Y-m-d'));

        // Test task duration calculation
        $duration1 = $task1->start_date->diffInDays($task1->end_date);
        $duration2 = $task2->start_date->diffInDays($task2->end_date);
        
        $this->assertEquals(10, $duration1);
        $this->assertEquals(14, $duration2);
    }

    /**
     * Test task dependencies and timeline impact
     */
    public function test_can_create_task_dependencies_with_timeline(): void
    {
        // Create tasks
        $task1 = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Design Phase',
            'start_date' => now(),
            'end_date' => now()->addDays(5),
            'status' => 'pending',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id
        ]);

        $task2 = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Construction Phase',
            'start_date' => now()->addDays(6),
            'end_date' => now()->addDays(20),
            'status' => 'pending',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id
        ]);

        // Create dependency: Task2 depends on Task1
        $result = $this->dependencyService->addDependency(
            $task2->id,
            $task1->id,
            $this->tenant->id
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('Dependency added successfully', $result['message']);

        // Verify dependency exists
        $this->assertDatabaseHas('task_dependencies', [
            'task_id' => $task2->id,
            'dependency_id' => $task1->id,
            'tenant_id' => $this->tenant->id
        ]);

        // Test timeline validation
        $this->assertTrue($task2->start_date->isAfter($task1->end_date));
    }

    /**
     * Test critical path calculation
     */
    public function test_can_calculate_critical_path(): void
    {
        // Create a chain of tasks: A → B → C
        $taskA = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Task A',
            'start_date' => now(),
            'end_date' => now()->addDays(5),
            'status' => 'pending',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id
        ]);

        $taskB = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Task B',
            'start_date' => now()->addDays(6),
            'end_date' => now()->addDays(10),
            'status' => 'pending',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id
        ]);

        $taskC = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Task C',
            'start_date' => now()->addDays(11),
            'end_date' => now()->addDays(15),
            'status' => 'pending',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id
        ]);

        // Create dependencies
        $this->dependencyService->addDependency($taskB->id, $taskA->id, $this->tenant->id);
        $this->dependencyService->addDependency($taskC->id, $taskB->id, $this->tenant->id);

        // Calculate critical path
        $criticalPath = $this->dependencyService->getCriticalPath($this->project->id, $this->tenant->id);

        $this->assertCount(3, $criticalPath);
        $this->assertEquals($taskA->id, $criticalPath->first()->id);
        $this->assertEquals($taskC->id, $criticalPath->last()->id);
    }

    /**
     * Test milestone creation and tracking
     */
    public function test_can_create_and_track_milestones(): void
    {
        // Create milestones
        $milestone1 = ProjectMilestone::create([
            'project_id' => $this->project->id,
            'name' => 'Foundation Complete',
            'description' => 'Foundation work completed',
            'target_date' => now()->addDays(10),
            'status' => 'pending',
            'order' => 1,
            'created_by' => $this->user->id
        ]);

        $milestone2 = ProjectMilestone::create([
            'project_id' => $this->project->id,
            'name' => 'Structure Complete',
            'description' => 'Structural work completed',
            'target_date' => now()->addDays(25),
            'status' => 'pending',
            'order' => 2,
            'created_by' => $this->user->id
        ]);

        // Test milestone creation
        $this->assertDatabaseHas('project_milestones', [
            'id' => $milestone1->id,
            'project_id' => $this->project->id,
            'name' => 'Foundation Complete',
            'status' => 'pending'
        ]);

        // Test milestone completion
        $milestone1->markCompleted($this->user->id);
        
        $this->assertEquals('completed', $milestone1->fresh()->status);
        $this->assertNotNull($milestone1->fresh()->completed_date);

        // Test milestone statistics
        $stats = ProjectMilestone::getProjectStatistics($this->project->id);
        
        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(1, $stats['completed']);
        $this->assertEquals(1, $stats['pending']);
        $this->assertEquals(50.0, $stats['completion_rate']);
    }

    /**
     * Test Gantt chart data generation
     */
    public function test_can_generate_gantt_chart_data(): void
    {
        // Create tasks with different timelines
        $tasks = [];
        $startDate = now();
        
        for ($i = 0; $i < 5; $i++) {
            $tasks[] = Task::create([
                'tenant_id' => $this->tenant->id,
                'project_id' => $this->project->id,
                'name' => "Task " . ($i + 1),
                'description' => "Description for task " . ($i + 1),
                'start_date' => $startDate->copy()->addDays($i * 5),
                'end_date' => $startDate->copy()->addDays(($i * 5) + 3),
                'status' => $i < 2 ? 'completed' : 'pending',
                'priority' => $i % 2 === 0 ? 'high' : 'medium',
                'assigned_to' => $this->user->id,
                'created_by' => $this->user->id
            ]);
        }

        // Generate Gantt chart data
        $ganttData = $this->generateGanttChartData($this->project->id);

        $this->assertCount(5, $ganttData['tasks']);
        $this->assertArrayHasKey('timeline', $ganttData);
        $this->assertArrayHasKey('dependencies', $ganttData);
        $this->assertArrayHasKey('milestones', $ganttData);

        // Test task data structure
        $firstTask = $ganttData['tasks'][0];
        $this->assertArrayHasKey('id', $firstTask);
        $this->assertArrayHasKey('name', $firstTask);
        $this->assertArrayHasKey('start', $firstTask);
        $this->assertArrayHasKey('end', $firstTask);
        $this->assertArrayHasKey('status', $firstTask);
        $this->assertArrayHasKey('priority', $firstTask);
    }

    /**
     * Test timeline optimization
     */
    public function test_can_optimize_timeline(): void
    {
        // Create tasks with overlapping timelines
        $task1 = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Task 1',
            'start_date' => now(),
            'end_date' => now()->addDays(5),
            'status' => 'pending',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id
        ]);

        $task2 = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Task 2',
            'start_date' => now()->addDays(3), // Overlaps with Task 1
            'end_date' => now()->addDays(8),
            'status' => 'pending',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id
        ]);

        // Create dependency to force sequential execution
        $this->dependencyService->addDependency($task2->id, $task1->id, $this->tenant->id);

        // Optimize timeline (move Task 2 to start after Task 1 ends)
        $optimizedStartDate = $task1->end_date->addDay();
        $task2->update(['start_date' => $optimizedStartDate]);

        // Verify optimization
        $this->assertTrue($task2->fresh()->start_date->isAfter($task1->end_date));
        $this->assertFalse($task2->fresh()->start_date->isBefore($task1->end_date));
    }

    /**
     * Test blocked tasks detection
     */
    public function test_can_detect_blocked_tasks(): void
    {
        // Create tasks with dependencies
        $task1 = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Blocking Task',
            'status' => 'pending',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id
        ]);

        $task2 = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Blocked Task',
            'status' => 'pending',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id
        ]);

        // Create dependency
        $this->dependencyService->addDependency($task2->id, $task1->id, $this->tenant->id);

        // Get blocked tasks
        $blockedTasks = $this->dependencyService->getBlockedTasks($this->tenant->id);

        $this->assertCount(1, $blockedTasks);
        $this->assertEquals($task2->id, $blockedTasks->first()->id);

        // Complete blocking task
        $task1->update(['status' => 'completed']);

        // Check blocked tasks again
        $blockedTasks = $this->dependencyService->getBlockedTasks($this->tenant->id);
        $this->assertCount(0, $blockedTasks);
    }

    /**
     * Test ready tasks detection
     */
    public function test_can_detect_ready_tasks(): void
    {
        // Create tasks
        $task1 = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Independent Task',
            'status' => 'pending',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id
        ]);

        $task2 = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Dependent Task',
            'status' => 'pending',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id
        ]);

        $task3 = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Blocking Task',
            'status' => 'pending',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id
        ]);

        // Create dependency: Task2 depends on Task3
        $this->dependencyService->addDependency($task2->id, $task3->id, $this->tenant->id);

        // Get ready tasks
        $readyTasks = $this->dependencyService->getReadyTasks($this->tenant->id);

        $this->assertCount(2, $readyTasks); // Task1 and Task3
        $readyTaskIds = $readyTasks->pluck('id')->toArray();
        $this->assertContains($task1->id, $readyTaskIds);
        $this->assertContains($task3->id, $readyTaskIds);
        $this->assertNotContains($task2->id, $readyTaskIds);
    }

    /**
     * Test timeline visualization data
     */
    public function test_can_generate_timeline_visualization_data(): void
    {
        // Create tasks with different statuses and priorities
        $tasks = [];
        $statuses = ['pending', 'in_progress', 'completed'];
        $priorities = ['low', 'medium', 'high', 'critical'];

        for ($i = 0; $i < 6; $i++) {
            $tasks[] = Task::create([
                'tenant_id' => $this->tenant->id,
                'project_id' => $this->project->id,
                'name' => "Visualization Task " . ($i + 1),
                'start_date' => now()->addDays($i * 2),
                'end_date' => now()->addDays(($i * 2) + 3),
                'status' => $statuses[$i % 3],
                'priority' => $priorities[$i % 4],
                'assigned_to' => $this->user->id,
                'created_by' => $this->user->id
            ]);
        }

        // Generate visualization data
        $visualizationData = $this->generateTimelineVisualizationData($this->project->id);

        $this->assertArrayHasKey('tasks', $visualizationData);
        $this->assertArrayHasKey('timeline', $visualizationData);
        $this->assertArrayHasKey('status_counts', $visualizationData);
        $this->assertArrayHasKey('priority_distribution', $visualizationData);

        // Test status counts
        $statusCounts = $visualizationData['status_counts'];
        $this->assertEquals(2, $statusCounts['pending']);
        $this->assertEquals(2, $statusCounts['in_progress']);
        $this->assertEquals(2, $statusCounts['completed']);

        // Test priority distribution
        $priorityDistribution = $visualizationData['priority_distribution'];
        $this->assertArrayHasKey('low', $priorityDistribution);
        $this->assertArrayHasKey('medium', $priorityDistribution);
        $this->assertArrayHasKey('high', $priorityDistribution);
        $this->assertArrayHasKey('critical', $priorityDistribution);
    }

    /**
     * Test multi-tenant timeline isolation
     */
    public function test_timeline_data_is_tenant_isolated(): void
    {
        // Create another tenant
        $tenant2 = Tenant::factory()->create([
            'name' => 'Another Company',
            'slug' => 'another-company',
            'status' => 'active'
        ]);

        $project2 = Project::factory()->create([
            'tenant_id' => $tenant2->id,
            'code' => 'TIMELINE-002',
            'name' => 'Another Timeline Project',
            'status' => 'active'
        ]);

        // Create tasks in both tenants
        $task1 = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Tenant 1 Task',
            'status' => 'pending',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id
        ]);

        $task2 = Task::create([
            'tenant_id' => $tenant2->id,
            'project_id' => $project2->id,
            'name' => 'Tenant 2 Task',
            'status' => 'pending',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id
        ]);

        // Test tenant isolation
        $tenant1Tasks = Task::where('tenant_id', $this->tenant->id)->get();
        $tenant2Tasks = Task::where('tenant_id', $tenant2->id)->get();

        $this->assertCount(1, $tenant1Tasks);
        $this->assertCount(1, $tenant2Tasks);
        $this->assertEquals($task1->id, $tenant1Tasks->first()->id);
        $this->assertEquals($task2->id, $tenant2Tasks->first()->id);

        // Test dependency service isolation
        $result = $this->dependencyService->addDependency(
            $task2->id,
            $task1->id,
            $tenant2->id
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Cannot create dependency across tenants', $result['message']);
    }

    /**
     * Helper method to generate Gantt chart data
     */
    private function generateGanttChartData(string $projectId): array
    {
        $tasks = Task::where('project_id', $projectId)
            ->where('tenant_id', $this->tenant->id)
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'name' => $task->name,
                    'start' => $task->start_date,
                    'end' => $task->end_date,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'progress' => $task->progress_percent ?? 0,
                    'assignee' => $task->assignee ? $task->assignee->name : null
                ];
            });

        $milestones = ProjectMilestone::where('project_id', $projectId)
            ->where('status', '!=', 'cancelled')
            ->get()
            ->map(function ($milestone) {
                return [
                    'id' => $milestone->id,
                    'name' => $milestone->name,
                    'date' => $milestone->target_date,
                    'status' => $milestone->status
                ];
            });

        $dependencies = TaskDependency::whereHas('task', function ($query) use ($projectId) {
            $query->where('project_id', $projectId);
        })->get()
        ->map(function ($dependency) {
            return [
                'from' => $dependency->dependency_id,
                'to' => $dependency->task_id
            ];
        });

        // Calculate timeline range
        $allDates = collect();
        $tasks->each(function ($task) use ($allDates) {
            if ($task['start']) $allDates->push($task['start']);
            if ($task['end']) $allDates->push($task['end']);
        });
        $milestones->each(function ($milestone) use ($allDates) {
            if ($milestone['date']) $allDates->push($milestone['date']);
        });

        $timeline = $allDates->filter()->sort()->unique()->values();

        return [
            'tasks' => $tasks,
            'milestones' => $milestones,
            'dependencies' => $dependencies,
            'timeline' => $timeline
        ];
    }

    /**
     * Helper method to generate timeline visualization data
     */
    private function generateTimelineVisualizationData(string $projectId): array
    {
        $tasks = Task::where('project_id', $projectId)
            ->where('tenant_id', $this->tenant->id)
            ->get();

        $statusCounts = $tasks->groupBy('status')->map->count();
        $priorityDistribution = $tasks->groupBy('priority')->map->count();

        $timeline = $tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'name' => $task->name,
                'start' => $task->start_date,
                'end' => $task->end_date,
                'status' => $task->status,
                'priority' => $task->priority
            ];
        });

        return [
            'tasks' => $timeline,
            'timeline' => $timeline->pluck('start')->merge($timeline->pluck('end'))->filter()->sort()->unique()->values(),
            'status_counts' => $statusCounts,
            'priority_distribution' => $priorityDistribution
        ];
    }
}
