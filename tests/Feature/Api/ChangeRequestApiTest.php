<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\ChangeRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class ChangeRequestApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $project;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->project = Project::factory()->create([
            'created_by' => $this->user->id
        ]);
        $this->token = $this->generateJwtToken($this->user);
    }

    /**
     * Test Change Request index endpoint
     */
    public function test_can_get_change_request_list()
    {
        ChangeRequest::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/change-requests');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'title',
                                'change_type',
                                'status',
                                'priority',
                                'created_at'
                            ]
                        ]
                    ]
                ]);
    }

    /**
     * Test Change Request creation
     */
    public function test_can_create_change_request()
    {
        $changeRequestData = [
            'project_id' => $this->project->id,
            'title' => 'Test Change Request',
            'description' => 'Test change request description',
            'change_number' => 'CR-001',
            'change_type' => 'scope',
            'justification' => 'Required for project success',
            'alternatives_considered' => 'Alternative 1, Alternative 2',
            'impact_analysis' => 'Minimal impact on schedule',
            'cost_impact' => 10000.00,
            'schedule_impact_days' => 5,
            'priority' => 'medium'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/change-requests', $changeRequestData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'title',
                        'change_type',
                        'status',
                        'priority'
                    ]
                ]);

        $this->assertDatabaseHas('change_requests', [
            'title' => 'Test Change Request',
            'project_id' => $this->project->id
        ]);
    }

    /**
     * Test Change Request submission
     */
    public function test_can_submit_change_request()
    {
        $changeRequest = ChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status' => 'draft'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/change-requests/{$changeRequest->id}/submit");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'status',
                        'submitted_at'
                    ]
                ]);

        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'status' => 'submitted'
        ]);
    }

    /**
     * Test Change Request approval
     */
    public function test_can_approve_change_request()
    {
        $changeRequest = ChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status' => 'pending_approval'
        ]);

        $approvalData = [
            'approved_cost' => 15000.00,
            'approved_schedule_days' => 7,
            'approval_comments' => 'Approved with modifications'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/change-requests/{$changeRequest->id}/approve", $approvalData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'approved_cost',
                        'approved_schedule_days',
                        'approval_comments',
                        'status',
                        'approved_at'
                    ]
                ]);

        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'status' => 'approved',
            'approved_cost' => 15000.00
        ]);
    }

    /**
     * Test Change Request rejection
     */
    public function test_can_reject_change_request()
    {
        $changeRequest = ChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status' => 'pending_approval'
        ]);

        $rejectionData = [
            'rejection_reason' => 'Cost impact too high',
            'rejection_comments' => 'Please revise the proposal'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/change-requests/{$changeRequest->id}/reject", $rejectionData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'rejection_reason',
                        'rejection_comments',
                        'status',
                        'rejected_at'
                    ]
                ]);

        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'status' => 'rejected',
            'rejection_reason' => 'Cost impact too high'
        ]);
    }

    /**
     * Test Change Request implementation
     */
    public function test_can_implement_change_request()
    {
        $changeRequest = ChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status' => 'approved'
        ]);

        $implementationData = [
            'implementation_notes' => 'Change has been implemented successfully'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/change-requests/{$changeRequest->id}/apply", $implementationData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'implementation_notes',
                        'status',
                        'implemented_at'
                    ]
                ]);

        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'status' => 'implemented',
            'implementation_notes' => 'Change has been implemented successfully'
        ]);
    }

    /**
     * Test Change Request update
     */
    public function test_can_update_change_request()
    {
        $changeRequest = ChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $updateData = [
            'title' => 'Updated Change Request Title',
            'description' => 'Updated description',
            'priority' => 'high'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson("/api/zena/change-requests/{$changeRequest->id}", $updateData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'title',
                        'description',
                        'priority'
                    ]
                ]);

        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'title' => 'Updated Change Request Title'
        ]);
    }

    /**
     * Test Change Request deletion
     */
    public function test_can_delete_change_request()
    {
        $changeRequest = ChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson("/api/zena/change-requests/{$changeRequest->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('change_requests', [
            'id' => $changeRequest->id
        ]);
    }

    /**
     * Test Change Request validation
     */
    public function test_change_request_creation_requires_valid_data()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/change-requests', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['project_id', 'title', 'description', 'change_type']);
    }

    /**
     * Test Change Request impact level calculation
     */
    public function test_change_request_impact_level_calculation()
    {
        $changeRequest = ChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'cost_impact' => 50000.00,
            'schedule_impact_days' => 15
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/zena/change-requests/{$changeRequest->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'impact_level'
                    ]
                ]);
    }

    /**
     * Test unauthorized access
     */
    public function test_unauthorized_access_returns_401()
    {
        $response = $this->getJson('/api/zena/change-requests');
        $response->assertStatus(401);
    }

    /**
     * Generate JWT token for testing
     */
    private function generateJwtToken(User $user): string
    {
        return 'test-jwt-token-' . $user->id;
    }
}
