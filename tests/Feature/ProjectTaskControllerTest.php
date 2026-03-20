<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use App\Models\Tenant;
use Src\CoreProject\Models\Project as CoreProjectProject;
use Src\WorkTemplate\Models\ProjectPhase;
use Src\WorkTemplate\Models\ProjectTask;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Event;
use Src\WorkTemplate\Events\TaskConditionalToggled;

class ProjectTaskControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Tenant $tenant;
    private string $token;
    private CoreProjectProject $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Tạo tenant mặc định
        $this->tenant = Tenant::factory()->create([
            'id' => 1,
            'name' => 'Test Company',
            'domain' => 'test.com'
        ]);

        // Tạo user và login
        $this->user = User::factory()->create([
            'password' => Hash::make('password123'),
            'tenant_id' => $this->tenant->id,
        ]);

        $this->user->assignRole('super_admin');

        $this->token = $this->user->createToken('project-task-test')->plainTextToken;
        
        // Tạo project cho test
        $this->project = CoreProjectProject::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    private function authenticatedJson(string $method, string $uri, array $data = [], array $headers = [])
    {
        $headers['Authorization'] = 'Bearer ' . $this->token;
        $headers['X-Tenant-ID'] = (string) $this->tenant->id;
        return $this->json($method, $uri, $data, $headers);
    }

    /**
     * Test POST /api/v1/projects/{project}/tasks/{task}/toggle-conditional
     */
    public function test_can_toggle_conditional_task(): void
    {
        Event::fake();
        
        $phase = ProjectPhase::factory()->create([
            'project_id' => $this->project->id,
        ]);
        
        $task = ProjectTask::factory()->create([
            'project_id' => $this->project->id,
            'phase_id' => $phase->id,
            'conditional_tag' => 'design_required',
            'is_hidden' => true,
        ]);


        $response = $this->authenticatedJson(
            'POST',
            "/api/v1/work-template/projects/{$this->project->id}/tasks/{$task->id}/toggle-conditional"
        );

        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'task' => [
                             'id',
                             'name',
                             'is_hidden',
                             'conditional_tag',
                         ],
                         'message'
                     ]
                 ]);

        // Kiểm tra task được toggle
        $task->refresh();
        $this->assertFalse($task->is_hidden);

        // Kiểm tra event được dispatch
        Event::assertDispatched(TaskConditionalToggled::class, function ($event) use ($task) {
            return $event->taskId === $task->id &&
                   $event->projectId === $this->project->id &&
                   $event->isHidden === false;
        });
    }

    /**
     * Test toggle task không có conditional tag
     */
    public function test_toggle_task_without_conditional_tag(): void
    {
        $phase = ProjectPhase::factory()->create([
            'project_id' => $this->project->id,
        ]);
        
        $task = ProjectTask::factory()->create([
            'project_id' => $this->project->id,
            'phase_id' => $phase->id,
            'conditional_tag' => null,
            'is_hidden' => false,
        ]);

        $response = $this->authenticatedJson(
            'POST',
            "/api/v1/work-template/projects/{$this->project->id}/tasks/{$task->id}/toggle-conditional"
        );

        $response->assertStatus(400)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Task này không có conditional tag'
                 ]);
    }

    /**
     * Test toggle task không thuộc project
     */
    public function test_toggle_task_not_in_project(): void
    {
        $otherProject = CoreProjectProject::factory()->create();
        $phase = ProjectPhase::factory()->create([
            'project_id' => $otherProject->id,
        ]);
        
        $task = ProjectTask::factory()->create([
            'project_id' => $otherProject->id,
            'phase_id' => $phase->id,
            'conditional_tag' => 'design_required',
        ]);

        $response = $this->authenticatedJson(
            'POST',
            "/api/v1/work-template/projects/{$this->project->id}/tasks/{$task->id}/toggle-conditional"
        );

        $response->assertStatus(404)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Task không tồn tại trong project này'
                 ]);
    }

    /**
     * Test GET /api/v1/projects/{project}/tasks/conditional
     */
    public function test_can_get_conditional_tasks(): void
    {
        $phase = ProjectPhase::factory()->create([
            'project_id' => $this->project->id,
        ]);
        
        // Tạo tasks với conditional tags
        ProjectTask::factory()->create([
            'project_id' => $this->project->id,
            'phase_id' => $phase->id,
            'conditional_tag' => 'design_required',
            'is_hidden' => true,
        ]);
        
        ProjectTask::factory()->create([
            'project_id' => $this->project->id,
            'phase_id' => $phase->id,
            'conditional_tag' => 'testing_required',
            'is_hidden' => false,
        ]);
        
        // Task không có conditional tag
        ProjectTask::factory()->create([
            'project_id' => $this->project->id,
            'phase_id' => $phase->id,
            'conditional_tag' => null,
        ]);

        $response = $this->authenticatedJson(
            'GET',
            "/api/v1/work-template/projects/{$this->project->id}/tasks/conditional"
        );

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'tasks' => [
                             '*' => [
                                 'id',
                                 'name',
                                 'conditional_tag',
                                 'is_hidden',
                                 'phase',
                             ]
                         ],
                         'summary' => [
                             'total_conditional_tasks',
                             'hidden_tasks',
                             'visible_tasks',
                             'conditional_tags',
                         ]
                     ]
                 ]);

        $tasks = $response->json('data.tasks');
        $this->assertCount(2, $tasks); // Chỉ tasks có conditional tag
        
        $summary = $response->json('data.summary');
        $this->assertEquals(2, $summary['total_conditional_tasks']);
        $this->assertEquals(1, $summary['hidden_tasks']);
        $this->assertEquals(1, $summary['visible_tasks']);
    }

    public function test_index_route_returns_project_scoped_task_collection_in_current_jsend_envelope_family(): void
    {
        $phase = ProjectPhase::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $visibleTask = ProjectTask::factory()->create([
            'project_id' => $this->project->id,
            'phase_id' => $phase->id,
            'name' => 'Visible project task',
        ]);

        $secondTask = ProjectTask::factory()->create([
            'project_id' => $this->project->id,
            'phase_id' => $phase->id,
            'name' => 'Second project task',
        ]);

        $otherProject = CoreProjectProject::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $otherPhase = ProjectPhase::factory()->create([
            'project_id' => $otherProject->id,
        ]);
        ProjectTask::factory()->create([
            'project_id' => $otherProject->id,
            'phase_id' => $otherPhase->id,
            'name' => 'Other project task',
        ]);

        $response = $this->authenticatedJson(
            'GET',
            "/api/v1/work-template/projects/{$this->project->id}/tasks"
        );

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id',
                        'project_id',
                        'phase_id',
                        'name',
                        'description',
                        'duration_days',
                        'progress_percent',
                        'status',
                        'conditional_tag',
                        'is_hidden',
                        'has_conditional_tag',
                        'template_id',
                        'template_task_id',
                        'is_from_template',
                        'status_info' => [
                            'is_completed',
                            'is_in_progress',
                        ],
                        'created_by',
                        'updated_by',
                        'created_at',
                        'updated_at',
                        'phase',
                        'template',
                    ],
                ],
            ])
            ->assertJsonCount(2, 'data');

        $this->assertSame(
            [$visibleTask->id, $secondTask->id],
            array_column($response->json('data'), 'id')
        );
        $this->assertSame(
            [$this->project->id, $this->project->id],
            array_column($response->json('data'), 'project_id')
        );
    }

    public function test_update_route_returns_200_and_keeps_current_jsend_resource_envelope_family(): void
    {
        $phase = ProjectPhase::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $task = ProjectTask::factory()->create([
            'project_id' => $this->project->id,
            'phase_id' => $phase->id,
            'name' => 'Original task name',
            'description' => 'Original task description',
        ]);

        $response = $this->authenticatedJson(
            'PUT',
            "/api/v1/work-template/projects/{$this->project->id}/tasks/{$task->id}",
            [
                'name' => 'Updated task name',
                'description' => 'Updated task description',
            ]
        );

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.project_id', $this->project->id)
            ->assertJsonPath('data.name', 'Updated task name')
            ->assertJsonPath('data.description', 'Updated task description')
            ->assertJsonStructure([
                'status',
                'data' => [
                    'id',
                    'project_id',
                    'phase_id',
                    'name',
                    'description',
                    'duration_days',
                    'progress_percent',
                    'status',
                    'conditional_tag',
                    'is_hidden',
                    'has_conditional_tag',
                    'template_id',
                    'template_task_id',
                    'is_from_template',
                    'status_info' => [
                        'is_completed',
                        'is_in_progress',
                    ],
                    'created_by',
                    'updated_by',
                    'created_at',
                    'updated_at',
                    'phase',
                    'template',
                ],
            ]);

        $task->refresh();
        $this->assertSame('Updated task name', $task->name);
        $this->assertSame('Updated task description', $task->description);
        $this->assertSame($this->user->id, $task->updated_by);
    }

    public function test_update_progress_route_returns_422_for_invalid_payload_before_helper_drift_is_reached(): void
    {
        $phase = ProjectPhase::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $task = ProjectTask::factory()->create([
            'project_id' => $this->project->id,
            'phase_id' => $phase->id,
        ]);

        $response = $this->authenticatedJson(
            'PUT',
            "/api/v1/work-template/projects/{$this->project->id}/tasks/{$task->id}/progress",
            []
        );

        $response->assertStatus(422);
    }

    public function test_update_progress_route_returns_200_for_valid_existing_task(): void
    {
        $phase = ProjectPhase::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $task = ProjectTask::factory()->create([
            'project_id' => $this->project->id,
            'phase_id' => $phase->id,
            'progress_percent' => 0,
        ]);

        $response = $this->authenticatedJson(
            'PUT',
            "/api/v1/work-template/projects/{$this->project->id}/tasks/{$task->id}/progress",
            ['progress_percent' => 25]
        );

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Task progress updated successfully',
            ]);

        $task->refresh();
        $this->assertSame(25.0, (float) $task->progress_percent);
    }

    public function test_update_progress_route_returns_404_error_envelope_for_missing_task(): void
    {
        $response = $this->authenticatedJson(
            'PUT',
            "/api/v1/work-template/projects/{$this->project->id}/tasks/999999/progress",
            ['progress_percent' => 25]
        );

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Task không tồn tại trong project này',
            ]);
    }

    /**
     * Test authentication required
     */
    public function test_authentication_required_for_task_operations(): void
    {
        $task = ProjectTask::factory()->create();
        
        $response = $this->postJson("/api/v1/work-template/projects/{$this->project->id}/tasks/{$task->id}/toggle-conditional");
        $response->assertStatus(401);
        
        $response = $this->getJson("/api/v1/work-template/projects/{$this->project->id}/tasks/conditional");
        $response->assertStatus(401);
    }

}
