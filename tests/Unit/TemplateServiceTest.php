<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\WorkTemplate\Models\Template;
use Src\WorkTemplate\Services\TemplateService;
use Src\CoreProject\Models\Project;
use Src\WorkTemplate\Models\ProjectPhase;
use Src\WorkTemplate\Models\ProjectTask;
use Illuminate\Support\Facades\Event;
use Src\WorkTemplate\Events\TemplateApplied;

class TemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    private TemplateService $templateService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->templateService = new TemplateService();
    }

    /**
     * Test applyToProject method với full mode
     */
    public function test_apply_template_to_project_full_mode(): void
    {
        Event::fake();
        
        $template = Template::factory()->withComplexStructure()->create();
        $project = Project::factory()->create();
        
        $result = $this->templateService->applyToProject(
            $template,
            $project->id,
            'full',
            ['design_required'],
            null,
            null,
            'user123'
        );

        // Kiểm tra kết quả
        $this->assertEquals($project->id, $result['project_id']);
        $this->assertEquals($template->id, $result['template_id']);
        $this->assertEquals('full', $result['mode']);
        $this->assertGreaterThan(0, $result['phases_created']);
        $this->assertGreaterThan(0, $result['tasks_created']);
        $this->assertIsArray($result['conditional_tags']);

        // Kiểm tra phases được tạo
        $phases = ProjectPhase::where('project_id', $project->id)
                             ->where('template_id', $template->id)
                             ->get();
        $this->assertCount($result['phases_created'], $phases);

        // Kiểm tra tasks được tạo
        $tasks = ProjectTask::where('project_id', $project->id)
                           ->where('template_id', $template->id)
                           ->get();
        $this->assertCount($result['tasks_created'] + $result['tasks_hidden'], $tasks);

        // Kiểm tra event được dispatch
        Event::assertDispatched(TemplateApplied::class, function ($event) use ($template, $project) {
            return $event->templateId === $template->id &&
                   $event->projectId === $project->id &&
                   $event->mode === 'full';
        });
    }

    /**
     * Test applyToProject method với partial mode
     */
    public function test_apply_template_to_project_partial_mode(): void
    {
        Event::fake();
        
        $template = Template::factory()->create();
        $project = Project::factory()->create();
        
        // Tạo existing phase
        $existingPhase = ProjectPhase::factory()->create([
            'project_id' => $project->id,
        ]);
        
        $phaseMapping = [
            $existingPhase->id => 'template_phase_1',
        ];
        
        $selectedItems = [
            'phases' => [$existingPhase->id],
            'tasks' => [],
        ];

        $result = $this->templateService->applyToProject(
            $template,
            $project->id,
            'partial',
            [],
            $phaseMapping,
            $selectedItems,
            'user123'
        );

        $this->assertEquals('partial', $result['mode']);
        
        Event::assertDispatched(TemplateApplied::class);
    }

    /**
     * Test validateConditionalTags method
     */
    public function test_validate_conditional_tags(): void
    {
        $jsonBody = [
            'phases' => [
                [
                    'tasks' => [
                        ['conditional_tag' => 'design_required'],
                        ['conditional_tag' => 'testing_required'],
                        ['conditional_tag' => null],
                    ],
                ],
            ],
        ];

        $validTags = ['design_required', 'testing_required'];
        $invalidTags = ['invalid_tag'];

        $this->assertTrue($this->templateService->validateConditionalTags($jsonBody, $validTags));
        $this->assertFalse($this->templateService->validateConditionalTags($jsonBody, $invalidTags));
    }

    /**
     * Test extractConditionalTags method
     */
    public function test_extract_conditional_tags(): void
    {
        $jsonBody = [
            'phases' => [
                [
                    'tasks' => [
                        ['conditional_tag' => 'design_required'],
                        ['conditional_tag' => 'testing_required'],
                        ['conditional_tag' => null],
                        ['conditional_tag' => 'design_required'], // Duplicate
                    ],
                ],
                [
                    'tasks' => [
                        ['conditional_tag' => 'review_needed'],
                    ],
                ],
            ],
        ];

        $tags = $this->templateService->extractConditionalTags($jsonBody);
        
        $this->assertCount(3, $tags);
        $this->assertContains('design_required', $tags);
        $this->assertContains('testing_required', $tags);
        $this->assertContains('review_needed', $tags);
    }

    /**
     * Test calculateEstimatedDuration method
     */
    public function test_calculate_estimated_duration(): void
    {
        $jsonBody = [
            'phases' => [
                [
                    'tasks' => [
                        ['duration_days' => 5],
                        ['duration_days' => 3],
                    ],
                ],
                [
                    'tasks' => [
                        ['duration_days' => 8],
                        ['duration_days' => 2],
                    ],
                ],
            ],
        ];

        $duration = $this->templateService->calculateEstimatedDuration($jsonBody);
        
        // Phase 1: max(5, 3) = 5
        // Phase 2: max(8, 2) = 8
        // Total: 5 + 8 = 13
        $this->assertEquals(13, $duration);
    }

    /**
     * Test isProjectBusy method
     */
    public function test_is_project_busy(): void
    {
        $project = Project::factory()->create();
        
        // Project không có tasks đang chạy
        $this->assertFalse($this->templateService->isProjectBusy($project->id));
        
        // Tạo task đang in_progress
        ProjectTask::factory()->create([
            'project_id' => $project->id,
            'status' => 'in_progress',
        ]);
        
        $this->assertTrue($this->templateService->isProjectBusy($project->id));
    }

    /**
     * Test createPhaseFromTemplate method
     */
    public function test_create_phase_from_template(): void
    {
        $template = Template::factory()->create();
        $project = Project::factory()->create();
        
        $phaseData = [
            'name' => 'Test Phase',
            'order' => 1,
            'tasks' => [],
        ];
        
        $phase = $this->templateService->createPhaseFromTemplate(
            $phaseData,
            $project->id,
            $template->id,
            'template_phase_1',
            'user123'
        );
        
        $this->assertInstanceOf(ProjectPhase::class, $phase);
        $this->assertEquals('Test Phase', $phase->name);
        $this->assertEquals(1, $phase->order);
        $this->assertEquals($project->id, $phase->project_id);
        $this->assertEquals($template->id, $phase->template_id);
        $this->assertEquals('template_phase_1', $phase->template_phase_id);
    }

    /**
     * Test createTaskFromTemplate method
     */
    public function test_create_task_from_template(): void
    {
        $template = Template::factory()->create();
        $project = Project::factory()->create();
        $phase = ProjectPhase::factory()->create([
            'project_id' => $project->id,
        ]);
        
        $taskData = [
            'name' => 'Test Task',
            'duration_days' => 5,
            'role' => 'Developer',
            'contract_value_percent' => 10.0,
            'dependencies' => [],
            'conditional_tag' => 'design_required',
        ];
        
        $task = $this->templateService->createTaskFromTemplate(
            $taskData,
            $project->id,
            $phase->id,
            $template->id,
            'template_task_1',
            ['design_required'],
            'user123'
        );
        
        $this->assertInstanceOf(ProjectTask::class, $task);
        $this->assertEquals('Test Task', $task->name);
        $this->assertEquals(5, $task->duration_days);
        $this->assertEquals($project->id, $task->project_id);
        $this->assertEquals($phase->id, $task->phase_id);
        $this->assertEquals($template->id, $task->template_id);
        $this->assertEquals('template_task_1', $task->template_task_id);
        $this->assertEquals('design_required', $task->conditional_tag);
        $this->assertFalse($task->is_hidden); // Tag được active
    }

    /**
     * Test createTaskFromTemplate với conditional tag không active
     */
    public function test_create_task_with_inactive_conditional_tag(): void
    {
        $template = Template::factory()->create();
        $project = Project::factory()->create();
        $phase = ProjectPhase::factory()->create([
            'project_id' => $project->id,
        ]);
        
        $taskData = [
            'name' => 'Test Task',
            'duration_days' => 5,
            'role' => 'Developer',
            'contract_value_percent' => 10.0,
            'dependencies' => [],
            'conditional_tag' => 'testing_required',
        ];
        
        $task = $this->templateService->createTaskFromTemplate(
            $taskData,
            $project->id,
            $phase->id,
            $template->id,
            'template_task_1',
            ['design_required'], // Không có testing_required
            'user123'
        );
        
        $this->assertTrue($task->is_hidden); // Task bị ẩn vì tag không active
    }
}