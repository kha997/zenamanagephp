<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Src\Common\Services\ValidationService;
use Illuminate\Validation\ValidationException;

/**
 * Unit tests cho ValidationService
 */
class ValidationServiceTest extends TestCase
{
    private ValidationService $validationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validationService = new ValidationService();
    }

    /**
     * Test project validation rules
     */
    public function test_project_validation_rules(): void
    {
        $validData = [
            'name' => 'Test Project',
            'description' => 'Project description',
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'status' => 'planning'
        ];
        
        $result = $this->validationService->validateProject($validData);
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        
        // Test invalid data
        $invalidData = [
            'name' => '', // Required field empty
            'start_date' => '2024-12-31',
            'end_date' => '2024-01-01', // End date before start date
            'status' => 'invalid_status'
        ];
        
        $result = $this->validationService->validateProject($invalidData);
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    /**
     * Test task validation with dependencies
     */
    public function test_task_validation_with_dependencies(): void
    {
        $taskData = [
            'name' => 'Test Task',
            'project_id' => 'project_123',
            'start_date' => '2024-02-01',
            'end_date' => '2024-02-15',
            'dependencies' => ['task_1', 'task_2'],
            'status' => 'pending'
        ];
        
        $result = $this->validationService->validateTask($taskData);
        $this->assertTrue($result['valid']);
        
        // Test circular dependency
        $circularData = [
            'name' => 'Task A',
            'project_id' => 'project_123',
            'dependencies' => ['task_b'] // task_b depends on task_a
        ];
        
        $result = $this->validationService->validateTaskDependencies(
            'task_a', 
            $circularData['dependencies'],
            ['task_b' => ['task_a']] // Existing dependencies map
        );
        
        $this->assertFalse($result['valid']);
        $this->assertStringContains('circular dependency', $result['errors'][0]);
    }

    /**
     * Test business rule validation
     */
    public function test_business_rule_validation(): void
    {
        // Test project budget validation
        $projectData = [
            'planned_cost' => 100000,
            'actual_cost' => 120000 // 20% over budget
        ];
        
        $result = $this->validationService->validateProjectBudget($projectData);
        $this->assertTrue($result['warning']); // Should trigger warning
        $this->assertStringContains('over budget', $result['message']);
        
        // Test task assignment validation
        $assignmentData = [
            'task_id' => 'task_123',
            'assignments' => [
                ['user_id' => 'user_1', 'split_percentage' => 60],
                ['user_id' => 'user_2', 'split_percentage' => 50] // Total > 100%
            ]
        ];
        
        $result = $this->validationService->validateTaskAssignments($assignmentData);
        $this->assertFalse($result['valid']);
        $this->assertStringContains('exceed 100%', $result['errors'][0]);
    }
}