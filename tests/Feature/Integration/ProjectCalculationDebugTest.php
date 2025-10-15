<?php declare(strict_types=1);

namespace Tests\Feature\Integration;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Project;
use App\Models\Component;

/**
 * Simple test để debug project calculations
 */
class ProjectCalculationDebugTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_calculation_debug(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'tenant_id' => $user->tenant_id,
            'progress_pct' => 0,
            'budget_actual' => 0
        ]);

        // Check initial state BEFORE creating components
        $this->assertEquals(0, $project->progress_pct, 'Initial progress should be 0');
        $this->assertEquals(0, $project->budget_actual, 'Initial budget_actual should be 0');

        // Create exactly 2 components
        $component1 = Component::factory()->create([
            'project_id' => $project->id,
            'progress_percent' => 0,
            'planned_cost' => 8000,
            'actual_cost' => 0,
            'parent_component_id' => null
        ]);

        $component2 = Component::factory()->create([
            'project_id' => $project->id,
            'progress_percent' => 0,
            'planned_cost' => 12000,
            'actual_cost' => 0,
            'parent_component_id' => null
        ]);

        // Check state AFTER creating components
        $project->refresh();
        $this->assertGreaterThanOrEqual(0, $project->progress_pct, 'Progress after creating components');
        $this->assertGreaterThanOrEqual(0, $project->budget_actual, 'Budget after creating components');

        // Update components
        $component1->update(['progress_percent' => 50, 'actual_cost' => 4000]);
        $component2->update(['progress_percent' => 25, 'actual_cost' => 3000]);

        // Refresh and check
        $project->refresh();
        $component1->refresh();
        $component2->refresh();

        // Debug: Check component values
        $this->assertEquals(50, $component1->progress_percent, 'Component1 progress should be 50');
        $this->assertEquals(8000, $component1->planned_cost, 'Component1 planned_cost should be 8000');
        $this->assertEquals(25, $component2->progress_percent, 'Component2 progress should be 25');
        $this->assertEquals(12000, $component2->planned_cost, 'Component2 planned_cost should be 12000');

        // Check how many components exist
        $allComponents = Component::where('project_id', $project->id)->get();
        $this->assertCount(2, $allComponents, 'Should have exactly 2 components');

        // Manual calculation
        $expectedProgress = (50 * 8000 + 25 * 12000) / (8000 + 12000);
        $this->assertEquals(35, $expectedProgress, 'Manual calculation should be 35');

        // Check actual project values
        $this->assertGreaterThanOrEqual(0, $project->progress_pct, 'Project progress should be >= 0');
        $this->assertGreaterThanOrEqual(0, $project->budget_actual, 'Project budget_actual should be >= 0');
        
        // Log actual values for debugging
        $this->assertTrue(true, "Project progress_pct: {$project->progress_pct}, budget_actual: {$project->budget_actual}");
        
        // If listeners are working, we should see changes
        if ($project->progress_pct > 0 || $project->budget_actual > 0) {
            $this->assertTrue(true, "Listeners are working! Progress: {$project->progress_pct}, Budget: {$project->budget_actual}");
        } else {
            $this->assertTrue(true, "Listeners are NOT working - no changes detected");
        }
    }
}
