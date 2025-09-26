<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\ZenaProject;
use App\Models\ZenaTask;
use App\Models\ZenaRfi;
use App\Models\ZenaSubmittal;
use App\Models\ZenaChangeRequest;
use App\Models\ZenaDocument;
use App\Models\ZenaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class IntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $project;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->project = ZenaProject::factory()->create([
            'created_by' => $this->user->id
        ]);
        $this->token = $this->generateJwtToken($this->user);
        
        Storage::fake('local');
    }

    /**
     * Test complete project workflow
     */
    public function test_complete_project_workflow()
    {
        // 1. Create project
        $projectData = [
            'name' => 'Integration Test Project',
            'description' => 'Test project for integration testing',
            'status' => 'planning',
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'budget' => 1000000,
            'client_name' => 'Test Client',
            'location' => 'Test Location'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/projects', $projectData);

        $response->assertStatus(201);
        $project = $response->json('data');

        // 2. Create tasks
        $taskData = [
            'project_id' => $project['id'],
            'name' => 'Integration Test Task',
            'description' => 'Test task for integration testing',
            'status' => 'todo',
            'priority' => 'high',
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-31'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/tasks', $taskData);

        $response->assertStatus(201);
        $task = $response->json('data');

        // 3. Create RFI
        $rfiData = [
            'project_id' => $project['id'],
            'title' => 'Integration Test RFI',
            'description' => 'Test RFI for integration testing',
            'rfi_number' => 'RFI-001',
            'priority' => 'medium',
            'location' => 'Building A'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/rfis', $rfiData);

        $response->assertStatus(201);
        $rfi = $response->json('data');

        // 4. Create Submittal
        $submittalData = [
            'project_id' => $project['id'],
            'title' => 'Integration Test Submittal',
            'description' => 'Test submittal for integration testing',
            'submittal_number' => 'SUB-001',
            'submittal_type' => 'drawing',
            'specification_section' => 'Section 1'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/submittals', $submittalData);

        $response->assertStatus(201);
        $submittal = $response->json('data');

        // 5. Create Change Request
        $changeRequestData = [
            'project_id' => $project['id'],
            'title' => 'Integration Test Change Request',
            'description' => 'Test change request for integration testing',
            'change_number' => 'CR-001',
            'change_type' => 'scope',
            'justification' => 'Required for project success',
            'cost_impact' => 50000,
            'schedule_impact_days' => 10,
            'priority' => 'high'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/change-requests', $changeRequestData);

        $response->assertStatus(201);
        $changeRequest = $response->json('data');

        // 6. Upload Document
        $file = UploadedFile::fake()->create('integration-test.pdf', 1000);
        $documentData = [
            'project_id' => $project['id'],
            'title' => 'Integration Test Document',
            'description' => 'Test document for integration testing',
            'document_type' => 'drawing',
            'file' => $file
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/documents', $documentData);

        $response->assertStatus(201);
        $document = $response->json('data');

        // 7. Create Notification
        $notificationData = [
            'user_id' => $this->user->id,
            'type' => 'task_assigned',
            'title' => 'Integration Test Notification',
            'message' => 'Test notification for integration testing',
            'priority' => 'medium'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/notifications', $notificationData);

        $response->assertStatus(201);
        $notification = $response->json('data');

        // Verify all entities were created
        $this->assertDatabaseHas('projects', ['id' => $project['id']]);
        $this->assertDatabaseHas('tasks', ['id' => $task['id']]);
        $this->assertDatabaseHas('rfis', ['id' => $rfi['id']]);
        $this->assertDatabaseHas('submittals', ['id' => $submittal['id']]);
        $this->assertDatabaseHas('change_requests', ['id' => $changeRequest['id']]);
        $this->assertDatabaseHas('documents', ['id' => $document['id']]);
        $this->assertDatabaseHas('notifications', ['id' => $notification['id']]);
    }

    /**
     * Test RFI workflow integration
     */
    public function test_rfi_workflow_integration()
    {
        // Create RFI
        $rfi = ZenaRfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status' => 'pending'
        ]);

        // Assign RFI
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/rfis/{$rfi->id}/assign", [
            'assigned_to' => $this->user->id,
            'assignment_notes' => 'Please review this RFI'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('rfis', [
            'id' => $rfi->id,
            'status' => 'in_progress'
        ]);

        // Respond to RFI
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/rfis/{$rfi->id}/respond", [
            'response' => 'This is the response to the RFI',
            'response_notes' => 'Additional notes'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('rfis', [
            'id' => $rfi->id,
            'status' => 'answered'
        ]);

        // Close RFI
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/rfis/{$rfi->id}/close");

        $response->assertStatus(200);
        $this->assertDatabaseHas('rfis', [
            'id' => $rfi->id,
            'status' => 'closed'
        ]);
    }

    /**
     * Test Submittal workflow integration
     */
    public function test_submittal_workflow_integration()
    {
        // Create Submittal
        $submittal = ZenaSubmittal::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status' => 'draft'
        ]);

        // Submit Submittal
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/submittals/{$submittal->id}/submit");

        $response->assertStatus(200);
        $this->assertDatabaseHas('submittals', [
            'id' => $submittal->id,
            'status' => 'submitted'
        ]);

        // Review Submittal
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/submittals/{$submittal->id}/review", [
            'review_notes' => 'This submittal looks good',
            'status' => 'approved'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('submittals', [
            'id' => $submittal->id,
            'status' => 'approved'
        ]);
    }

    /**
     * Test Change Request workflow integration
     */
    public function test_change_request_workflow_integration()
    {
        // Create Change Request
        $changeRequest = ZenaChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status' => 'draft'
        ]);

        // Submit Change Request
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/change-requests/{$changeRequest->id}/submit");

        $response->assertStatus(200);
        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'status' => 'submitted'
        ]);

        // Approve Change Request
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/change-requests/{$changeRequest->id}/approve", [
            'approved_cost' => 75000,
            'approved_schedule_days' => 15,
            'approval_comments' => 'Approved with modifications'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'status' => 'approved'
        ]);

        // Implement Change Request
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/change-requests/{$changeRequest->id}/apply", [
            'implementation_notes' => 'Change has been implemented successfully'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'status' => 'implemented'
        ]);
    }

    /**
     * Test Task Dependencies integration
     */
    public function test_task_dependencies_integration()
    {
        // Create tasks
        $task1 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status' => 'todo'
        ]);

        $task2 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status' => 'todo'
        ]);

        // Add dependency
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/tasks/{$task2->id}/dependencies", [
            'dependency_id' => $task1->id
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tasks', [
            'id' => $task2->id,
            'dependencies' => json_encode([$task1->id])
        ]);

        // Complete task1
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson("/api/zena/tasks/{$task1->id}/status", [
            'status' => 'done'
        ]);

        $response->assertStatus(200);

        // Start task2
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson("/api/zena/tasks/{$task2->id}/status", [
            'status' => 'in_progress'
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test Document Versioning integration
     */
    public function test_document_versioning_integration()
    {
        // Create document
        $file = UploadedFile::fake()->create('original-document.pdf', 1000);
        $documentData = [
            'project_id' => $this->project->id,
            'title' => 'Original Document',
            'description' => 'Original document description',
            'document_type' => 'drawing',
            'file' => $file
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/documents', $documentData);

        $response->assertStatus(201);
        $document = $response->json('data');

        // Create version
        $newFile = UploadedFile::fake()->create('updated-document.pdf', 1000);
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/documents/{$document['id']}/version", [
            'file' => $newFile,
            'version' => '2.0',
            'change_notes' => 'Updated with new specifications'
        ]);

        $response->assertStatus(201);
        $version = $response->json('data');

        // Get versions
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/zena/documents/{$document['id']}/versions");

        $response->assertStatus(200);
        $versions = $response->json('data');
        $this->assertCount(2, $versions);
    }

    /**
     * Test Notification integration
     */
    public function test_notification_integration()
    {
        // Create notification
        $notification = ZenaNotification::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'unread'
        ]);

        // Mark as read
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson("/api/zena/notifications/{$notification->id}/read");

        $response->assertStatus(200);
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'status' => 'read'
        ]);

        // Get unread count
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/notifications/stats/count');

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('data.count'));
    }

    /**
     * Test cross-module data consistency
     */
    public function test_cross_module_data_consistency()
    {
        // Create project
        $project = ZenaProject::factory()->create([
            'created_by' => $this->user->id
        ]);

        // Create related entities
        $task = ZenaTask::factory()->create([
            'project_id' => $project->id,
            'created_by' => $this->user->id
        ]);

        $rfi = ZenaRfi::factory()->create([
            'project_id' => $project->id,
            'created_by' => $this->user->id
        ]);

        $submittal = ZenaSubmittal::factory()->create([
            'project_id' => $project->id,
            'created_by' => $this->user->id
        ]);

        $changeRequest = ZenaChangeRequest::factory()->create([
            'project_id' => $project->id,
            'created_by' => $this->user->id
        ]);

        // Verify all entities reference the same project
        $this->assertEquals($project->id, $task->project_id);
        $this->assertEquals($project->id, $rfi->project_id);
        $this->assertEquals($project->id, $submittal->project_id);
        $this->assertEquals($project->id, $changeRequest->project_id);

        // Verify all entities reference the same user
        $this->assertEquals($this->user->id, $task->created_by);
        $this->assertEquals($this->user->id, $rfi->created_by);
        $this->assertEquals($this->user->id, $submittal->created_by);
        $this->assertEquals($this->user->id, $changeRequest->created_by);
    }

    /**
     * Generate JWT token for testing
     */
    private function generateJwtToken(User $user): string
    {
        return 'test-jwt-token-' . $user->id;
    }
}
