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
            'start_date' => now()->addDays(1)->format('Y-m-d'), // Future date
            'end_date' => now()->addDays(30)->format('Y-m-d'), // After start date
            'priority' => 'medium',
            'status' => 'planning'
        ];
        
        $result = $this->validationService->validateProject($validData);
        $this->assertIsArray($result);
        $this->assertEquals('Test Project', $result['name']);
        
        // Test invalid data
        $this->expectException(ValidationException::class);
        $invalidData = [
            'name' => '', // Required field empty
            'start_date' => now()->subDays(1)->format('Y-m-d'), // Past date
            'end_date' => now()->subDays(30)->format('Y-m-d'), // Before start date
            'status' => 'invalid_status'
        ];
        
        $this->validationService->validateProject($invalidData);
    }

    /**
     * Test task validation with dependencies
     */
    public function test_task_validation_with_dependencies(): void
    {
        $taskData = [
            'title' => 'Test Task',
            'project_id' => 'project_123',
            'start_date' => now()->addDays(1)->format('Y-m-d'),
            'end_date' => now()->addDays(15)->format('Y-m-d'),
            'priority' => 'medium',
            'status' => 'pending',
            'visibility' => 'team'
        ];
        
        $dependencies = ['task_1', 'task_2'];
        
        $result = $this->validationService->validateTaskWithDependencies($taskData, $dependencies);
        $this->assertIsArray($result);
        $this->assertEquals('Test Task', $result['title']);
        
        // Test circular dependency
        $circularData = [
            'title' => 'Task A',
            'project_id' => 'project_123',
            'start_date' => now()->addDays(1)->format('Y-m-d'),
            'end_date' => now()->addDays(10)->format('Y-m-d'),
            'priority' => 'medium',
            'status' => 'pending',
            'visibility' => 'team'
        ];
        
        $circularDependencies = ['task_b'];
        $existingDependencies = ['task_b' => ['task_a']]; // Existing dependencies map
        
        $result = $this->validationService->validateTaskDependencies(
            'task_a', 
            $circularDependencies,
            $existingDependencies
        );
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertIsArray($result['errors']);
        $this->assertNotEmpty($result['errors']);
    }

    /**
     * Test business rule validation
     */
    public function test_business_rule_validation(): void
    {
        // Test project budget validation
        $projectData = [
            'budget_planned' => 100000,
            'budget_actual' => 80000 // Within budget
        ];
        
        $result = $this->validationService->validateProjectBudget($projectData);
        $this->assertArrayHasKey('budget_planned', $result);
        $this->assertArrayHasKey('budget_actual', $result);
        
        // Test task timeline validation
        $taskData = [
            'start_date' => now()->addDays(1)->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'estimated_hours' => 40
        ];
        
        $result = $this->validationService->validateTaskTimeline($taskData);
        $this->assertArrayHasKey('start_date', $result);
        $this->assertArrayHasKey('end_date', $result);
        $this->assertArrayHasKey('estimated_hours', $result);
    }
}