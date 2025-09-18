<?php declare(strict_types=1);

namespace Tests\Helpers;

use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Component;
use Src\CoreProject\Models\Task;
use App\Models\User;
use Carbon\Carbon;

/**
 * Class TestDataHelper
 * 
 * Provides helper methods for creating test data
 * Centralizes test data creation logic
 * 
 * @package Tests\Helpers
 */
class TestDataHelper
{
    /**
     * Create a complete project structure for testing
     * 
     * @param User $owner
     * @param array $projectData
     * @return array
     */
    public static function createProjectStructure(User $owner, array $projectData = []): array
    {
        $project = Project::factory()->create(array_merge([
            'tenant_id' => $owner->tenant_id,
            'name' => 'Test Project',
            'description' => 'Test project for unit testing',
            'start_date' => Carbon::now(),
            'end_date' => Carbon::now()->addMonths(6),
            'status' => 'active'
        ], $projectData));

        $components = [];
        $tasks = [];

        // Create root components
        for ($i = 1; $i <= 3; $i++) {
            $component = Component::factory()->create([
                'project_id' => $project->id,
                'name' => "Component {$i}",
                'planned_cost' => 10000 * $i,
                'progress_percent' => 0
            ]);
            $components[] = $component;

            // Create tasks for each component
            for ($j = 1; $j <= 2; $j++) {
                $task = Task::factory()->create([
                    'project_id' => $project->id,
                    'component_id' => $component->id,
                    'name' => "Task {$i}.{$j}",
                    'start_date' => Carbon::now()->addDays($j),
                    'end_date' => Carbon::now()->addDays($j + 7),
                    'status' => 'pending'
                ]);
                $tasks[] = $task;
            }
        }

        return [
            'project' => $project,
            'components' => $components,
            'tasks' => $tasks
        ];
    }

    /**
     * Create sample API request data
     * 
     * @param string $type
     * @return array
     */
    public static function getApiRequestData(string $type): array
    {
        switch ($type) {
            case 'project':
                return [
                    'name' => 'New Test Project',
                    'description' => 'Project created via API test',
                    'start_date' => Carbon::now()->format('Y-m-d'),
                    'end_date' => Carbon::now()->addMonths(3)->format('Y-m-d'),
                    'status' => 'active'
                ];

            case 'component':
                return [
                    'name' => 'New Test Component',
                    'planned_cost' => 15000,
                    'progress_percent' => 0
                ];

            case 'task':
                return [
                    'name' => 'New Test Task',
                    'start_date' => Carbon::now()->format('Y-m-d'),
                    'end_date' => Carbon::now()->addDays(14)->format('Y-m-d'),
                    'status' => 'pending'
                ];

            default:
                return [];
        }
    }

    /**
     * Create invalid API request data for validation testing
     * 
     * @param string $type
     * @return array
     */
    public static function getInvalidApiRequestData(string $type): array
    {
        switch ($type) {
            case 'project':
                return [
                    'name' => '', // Required field empty
                    'start_date' => 'invalid-date',
                    'end_date' => Carbon::now()->subDays(1)->format('Y-m-d'), // End before start
                    'status' => 'invalid-status'
                ];

            case 'component':
                return [
                    'name' => '', // Required field empty
                    'planned_cost' => -1000, // Negative cost
                    'progress_percent' => 150 // Over 100%
                ];

            case 'task':
                return [
                    'name' => '', // Required field empty
                    'start_date' => 'invalid-date',
                    'end_date' => Carbon::now()->subDays(1)->format('Y-m-d'),
                    'status' => 'invalid-status'
                ];

            default:
                return [];
        }
    }
}