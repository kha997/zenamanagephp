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
