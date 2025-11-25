<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\Project;
use App\Services\TaskStatusTransitionService;
use App\Services\ValidationResult;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Unit tests for TaskStatusTransitionService
 * 
 * Tests all status transitions and validation rules according to spec.
 * Uses seedTasksDomain() for reproducible test data.
 * 
 * @group services
 * @group tasks
 * @group transitions
 */
class TaskStatusTransitionServiceTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;
    
    private TaskStatusTransitionService $service;
    protected array $seedData;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(45678); // Fixed seed for reproducibility
        $this->setDomainName('tasks');
        $this->setupDomainIsolation();
        
        // Seed test data
        $this->seedData = TestDataSeeder::seedTasksDomain($this->getDomainSeed());
        
        $this->service = new TaskStatusTransitionService();
    }
    
    //region Valid Transitions
    
    /**
     * @test
     */
    public function test_can_transition_from_backlog_to_in_progress(): void
    {
        $tenant = $this->seedData['tenant'];
        $project = $this->seedData['projects'][0];
        $user = $this->seedData['users'][0];
        
        $task = Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Test Task',
            'title' => 'Test Task',
            'status' => TaskStatus::BACKLOG->value,
            'created_by' => $user->id,
            'version' => 1,
            'order' => 1.0,
        ]);
        
        $result = $this->service->validateTransition(
            $task,
            TaskStatus::IN_PROGRESS
        );
        
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }
    
    /**
     * @test
     */
    public function test_can_transition_from_backlog_to_canceled(): void
    {
        $tenant = $this->seedData['tenant'];
        $project = $this->seedData['projects'][0];
        $user = $this->seedData['users'][0];
        
        $task = Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Test Task',
            'title' => 'Test Task',
            'status' => TaskStatus::BACKLOG->value,
            'created_by' => $user->id,
            'version' => 1,
            'order' => 1.0,
        ]);
        
        $result = $this->service->validateTransition(
            $task,
            TaskStatus::CANCELED,
            'Client request'
        );
        
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }
    
    /**
     * @test
     */
    public function test_can_transition_from_in_progress_to_done(): void
    {
        $tenant = $this->seedData['tenant'];
        $project = $this->seedData['projects'][0];
        $user = $this->seedData['users'][0];
        
        $task = Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Test Task',
            'title' => 'Test Task',
            'status' => TaskStatus::IN_PROGRESS->value,
            'created_by' => $user->id,
            'version' => 1,
            'order' => 1.0,
        ]);
        
        $result = $this->service->validateTransition(
            $task,
            TaskStatus::DONE
        );
        
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }
    
    /**
     * @test
     */
    public function test_can_transition_from_in_progress_to_blocked(): void
    {
        $tenant = $this->seedData['tenant'];
        $project = $this->seedData['projects'][0];
        $user = $this->seedData['users'][0];
        
        $task = Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Test Task',
            'title' => 'Test Task',
            'status' => TaskStatus::IN_PROGRESS->value,
            'created_by' => $user->id,
            'version' => 1,
            'order' => 1.0,
        ]);
        
        $result = $this->service->validateTransition(
            $task,
            TaskStatus::BLOCKED,
            'Waiting for client feedback'
        );
        
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }
    
    /**
     * @test
     */
    public function test_can_transition_from_in_progress_to_canceled(): void
    {
        $tenant = $this->seedData['tenant'];
        $project = $this->seedData['projects'][0];
        $user = $this->seedData['users'][0];
        
        $task = Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Test Task',
            'title' => 'Test Task',
            'status' => TaskStatus::IN_PROGRESS->value,
            'created_by' => $user->id,
            'version' => 1,
            'order' => 1.0,
        ]);
        
        $result = $this->service->validateTransition(
            $task,
            TaskStatus::CANCELED,
            'No longer needed'
        );
        
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }
    
    /**
     * @test
     */
    public function test_can_transition_from_in_progress_to_backlog(): void
    {
        $tenant = $this->seedData['tenant'];
        $project = $this->seedData['projects'][0];
        $user = $this->seedData['users'][0];
        
        $task = Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Test Task',
            'title' => 'Test Task',
            'status' => TaskStatus::IN_PROGRESS->value,
            'created_by' => $user->id,
            'version' => 1,
            'order' => 1.0,
        ]);
        
        $result = $this->service->validateTransition(
            $task,
            TaskStatus::BACKLOG
        );
        
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }
    
    /**
     * @test
     */
    public function test_can_transition_from_blocked_to_in_progress(): void
    {
        $tenant = $this->seedData['tenant'];
        $project = $this->seedData['projects'][0];
        $user = $this->seedData['users'][0];
        
        $task = Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Test Task',
            'title' => 'Test Task',
            'status' => TaskStatus::BLOCKED->value,
            'created_by' => $user->id,
            'version' => 1,
            'order' => 1.0,
        ]);
        
        $result = $this->service->validateTransition(
            $task,
            TaskStatus::IN_PROGRESS
        );
        
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }
    
    /**
     * @test
     */
    public function test_can_transition_from_blocked_to_canceled(): void
    {
        $tenant = $this->seedData['tenant'];
        $project = $this->seedData['projects'][0];
        $user = $this->seedData['users'][0];
        
        $task = Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Test Task',
            'title' => 'Test Task',
            'status' => TaskStatus::BLOCKED->value,
            'created_by' => $user->id,
            'version' => 1,
            'order' => 1.0,
        ]);
        
        $result = $this->service->validateTransition(
            $task,
            TaskStatus::CANCELED,
            'Cannot be resolved'
        );
        
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }
    
    /**
     * @test
     */
    public function test_can_transition_from_done_to_in_progress(): void
    {
        $tenant = $this->seedData['tenant'];
        $project = $this->seedData['projects'][0];
        $user = $this->seedData['users'][0];
        
        $task = Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Test Task',
            'title' => 'Test Task',
            'status' => TaskStatus::DONE->value,
            'created_by' => $user->id,
            'version' => 1,
            'order' => 1.0,
        ]);
        
        $result = $this->service->validateTransition(
            $task,
            TaskStatus::IN_PROGRESS
        );
        
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }
    
    /**
     * @test
     */
    public function test_can_transition_from_canceled_to_backlog(): void
    {
        $tenant = $this->seedData['tenant'];
        $project = $this->seedData['projects'][0];
        $user = $this->seedData['users'][0];
        
        $task = Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Test Task',
            'title' => 'Test Task',
            'status' => TaskStatus::CANCELED->value,
            'created_by' => $user->id,
            'version' => 1,
            'order' => 1.0,
        ]);
        
        $result = $this->service->validateTransition(
            $task,
            TaskStatus::BACKLOG
        );
        
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }
    
    //endregion
    
    //region Invalid Transitions
    
    /**
     * @test
     */
    public function test_cannot_transition_from_backlog_to_done(): void
    {
        $tenant = $this->seedData['tenant'];
        $project = $this->seedData['projects'][0];
        $user = $this->seedData['users'][0];
        
        $task = Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Test Task',
            'title' => 'Test Task',
            'status' => TaskStatus::BACKLOG->value,
            'created_by' => $user->id,
            'version' => 1,
            'order' => 1.0,
        ]);
        
        $result = $this->service->validateTransition(
            $task,
            TaskStatus::DONE
        );
        
        $this->assertFalse($result->isValid);
        $this->assertEquals('invalid_transition', $result->errorCode);
        $this->assertArrayHasKey('allowed_transitions', $result->details ?? []);
    }
    
    //endregion
    
    //region Project Status Restrictions
    
    /**
     * @test
     */
    public function test_project_status_blocks_transition_when_archived(): void
    {
        $tenant = $this->seedData['tenant'];
        $project = $this->seedData['projects'][0];
        $user = $this->seedData['users'][0];
        
        // Set project to archived
        $project->update(['status' => 'archived']);
        
        $task = Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Test Task',
            'title' => 'Test Task',
            'status' => TaskStatus::BACKLOG->value,
            'created_by' => $user->id,
            'version' => 1,
            'order' => 1.0,
        ]);
        
        $result = $this->service->validateTransition(
            $task,
            TaskStatus::IN_PROGRESS
        );
        
        $this->assertFalse($result->isValid);
        $this->assertEquals('project_status_restricted', $result->errorCode);
        $this->assertArrayHasKey('project_id', $result->details ?? []);
        $this->assertArrayHasKey('project_status', $result->details ?? []);
    }
    
    /**
     * @test
     */
    public function test_project_status_allows_transition_when_active(): void
    {
        $tenant = $this->seedData['tenant'];
        $project = $this->seedData['projects'][0];
        $user = $this->seedData['users'][0];
        
        // Ensure project is active
        $project->update(['status' => 'active']);
        
        $task = Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Test Task',
            'title' => 'Test Task',
            'status' => TaskStatus::BACKLOG->value,
            'created_by' => $user->id,
            'version' => 1,
            'order' => 1.0,
        ]);
        
        $result = $this->service->validateTransition(
            $task,
            TaskStatus::IN_PROGRESS
        );
        
        $this->assertTrue($result->isValid);
    }
    
    //endregion
    
    //region Dependencies Validation
    
    /**
     * @test
     */
    public function test_dependencies_must_be_complete_to_start(): void
    {
        $tenant = $this->seedData['tenant'];
        $project = $this->seedData['projects'][0];
        $user = $this->seedData['users'][0];
        
        // Create dependency task (not done)
        $dependency = Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Dependency Task',
            'title' => 'Dependency Task',
            'status' => TaskStatus::BACKLOG->value,
            'created_by' => $user->id,
            'version' => 1,
            'order' => 1.0,
        ]);
        
        // Create task that depends on dependency
        $task = Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Test Task',
            'title' => 'Test Task',
            'status' => TaskStatus::BACKLOG->value,
            'created_by' => $user->id,
            'version' => 1,
            'order' => 1.0,
            'dependencies' => [$dependency->id], // Task model uses array field for dependencies
        ]);
        
        $result = $this->service->validateTransition(
            $task,
            TaskStatus::IN_PROGRESS
        );
        
        $this->assertFalse($result->isValid);
        $this->assertEquals('dependencies_incomplete', $result->errorCode);
        $this->assertArrayHasKey('dependencies', $result->details ?? []);
    }
    
    /**
     * @test
     */
    public function test_can_start_when_all_dependencies_done(): void
    {
        $tenant = $this->seedData['tenant'];
        $project = $this->seedData['projects'][0];
        $user = $this->seedData['users'][0];
        
        // Create dependency task (done)
        $dependency = Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Dependency Task',
            'title' => 'Dependency Task',
            'status' => TaskStatus::DONE->value,
            'created_by' => $user->id,
            'version' => 1,
            'order' => 1.0,
        ]);
        
        // Create task that depends on dependency
        $task = Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Test Task',
            'title' => 'Test Task',
            'status' => TaskStatus::BACKLOG->value,
            'created_by' => $user->id,
            'version' => 1,
            'order' => 1.0,
            'dependencies' => [$dependency->id],
        ]);
        
        $result = $this->service->validateTransition(
            $task,
            TaskStatus::IN_PROGRESS
        );
        
        $this->assertTrue($result->isValid);
    }
    
    //endregion
    
    //region Reason Requirements
    
    /**
     * @test
     */
    public function test_reason_required_for_blocked(): void
    {
        $tenant = $this->seedData['tenant'];
        $project = $this->seedData['projects'][0];
        $user = $this->seedData['users'][0];
        
        $task = Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Test Task',
            'title' => 'Test Task',
            'status' => TaskStatus::IN_PROGRESS->value,
            'created_by' => $user->id,
            'version' => 1,
            'order' => 1.0,
        ]);
        
        $result = $this->service->validateTransition(
            $task,
            TaskStatus::BLOCKED,
            null // No reason provided
        );
        
        $this->assertFalse($result->isValid);
        $this->assertEquals('reason_required', $result->errorCode);
    }
    
    /**
     * @test
     */
    public function test_reason_required_for_canceled(): void
    {
        $tenant = $this->seedData['tenant'];
        $project = $this->seedData['projects'][0];
        $user = $this->seedData['users'][0];
        
        $task = Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Test Task',
            'title' => 'Test Task',
            'status' => TaskStatus::IN_PROGRESS->value,
            'created_by' => $user->id,
            'version' => 1,
            'order' => 1.0,
        ]);
        
        $result = $this->service->validateTransition(
            $task,
            TaskStatus::CANCELED,
            null // No reason provided
        );
        
        $this->assertFalse($result->isValid);
        $this->assertEquals('reason_required', $result->errorCode);
    }
    
    /**
     * @test
     */
    public function test_can_cancel_with_reason(): void
    {
        $tenant = $this->seedData['tenant'];
        $project = $this->seedData['projects'][0];
        $user = $this->seedData['users'][0];
        
        $task = Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Test Task',
            'title' => 'Test Task',
            'status' => TaskStatus::IN_PROGRESS->value,
            'created_by' => $user->id,
            'version' => 1,
            'order' => 1.0,
        ]);
        
        $result = $this->service->validateTransition(
            $task,
            TaskStatus::CANCELED,
            'Client request'
        );
        
        $this->assertTrue($result->isValid);
    }
    
    //endregion
    
    //region Progress Calculation
    
    /**
     * @test
     */
    public function test_calculate_progress_sets_100_when_done(): void
    {
        $progress = $this->service->calculateProgress(TaskStatus::DONE, 50);
        $this->assertEquals(100, $progress);
    }
    
    /**
     * @test
     */
    public function test_calculate_progress_sets_0_when_backlog(): void
    {
        $progress = $this->service->calculateProgress(TaskStatus::BACKLOG, 50);
        $this->assertEquals(0, $progress);
    }
    
    /**
     * @test
     */
    public function test_calculate_progress_preserves_when_in_progress(): void
    {
        $progress = $this->service->calculateProgress(TaskStatus::IN_PROGRESS, 50);
        $this->assertEquals(50, $progress);
    }
    
    //endregion
}
