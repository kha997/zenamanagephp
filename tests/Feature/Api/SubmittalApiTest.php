<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\ZenaProject;
use App\Models\ZenaSubmittal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class SubmittalApiTest extends TestCase
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
    }

    /**
     * Test Submittal index endpoint
     */
    public function test_can_get_submittal_list()
    {
        ZenaSubmittal::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/submittals');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'title',
                                'submittal_type',
                                'status',
                                'created_at'
                            ]
                        ]
                    ]
                ]);
    }

    /**
     * Test Submittal creation
     */
    public function test_can_create_submittal()
    {
        $submittalData = [
            'project_id' => $this->project->id,
            'title' => 'Test Submittal',
            'description' => 'Test submittal description',
            'submittal_number' => 'SUB-001',
            'submittal_type' => 'drawing',
            'specification_section' => 'Section 1',
            'contractor' => 'Test Contractor',
            'manufacturer' => 'Test Manufacturer'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/submittals', $submittalData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'title',
                        'submittal_type',
                        'status'
                    ]
                ]);

        $this->assertDatabaseHas('submittals', [
            'title' => 'Test Submittal',
            'project_id' => $this->project->id
        ]);
    }

    /**
     * Test Submittal submission
     */
    public function test_can_submit_submittal()
    {
        $submittal = ZenaSubmittal::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status' => 'draft'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/submittals/{$submittal->id}/submit");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'status',
                        'submitted_at'
                    ]
                ]);

        $this->assertDatabaseHas('submittals', [
            'id' => $submittal->id,
            'status' => 'submitted'
        ]);
    }

    /**
     * Test Submittal review
     */
    public function test_can_review_submittal()
    {
        $submittal = ZenaSubmittal::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status' => 'submitted'
        ]);

        $reviewData = [
            'review_notes' => 'This submittal looks good',
            'status' => 'approved'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/submittals/{$submittal->id}/review", $reviewData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'review_notes',
                        'status'
                    ]
                ]);

        $this->assertDatabaseHas('submittals', [
            'id' => $submittal->id,
            'review_notes' => 'This submittal looks good',
            'status' => 'approved'
        ]);
    }

    /**
     * Test Submittal approval
     */
    public function test_can_approve_submittal()
    {
        $submittal = ZenaSubmittal::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status' => 'pending_review'
        ]);

        $approvalData = [
            'approval_comments' => 'Approved with minor comments'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/submittals/{$submittal->id}/approve", $approvalData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'approval_comments',
                        'status',
                        'approved_at'
                    ]
                ]);

        $this->assertDatabaseHas('submittals', [
            'id' => $submittal->id,
            'status' => 'approved',
            'approval_comments' => 'Approved with minor comments'
        ]);
    }

    /**
     * Test Submittal rejection
     */
    public function test_can_reject_submittal()
    {
        $submittal = ZenaSubmittal::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status' => 'pending_review'
        ]);

        $rejectionData = [
            'rejection_reason' => 'Does not meet specifications',
            'rejection_comments' => 'Please revise and resubmit'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/submittals/{$submittal->id}/reject", $rejectionData);

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

        $this->assertDatabaseHas('submittals', [
            'id' => $submittal->id,
            'status' => 'rejected',
            'rejection_reason' => 'Does not meet specifications'
        ]);
    }

    /**
     * Test Submittal update
     */
    public function test_can_update_submittal()
    {
        $submittal = ZenaSubmittal::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $updateData = [
            'title' => 'Updated Submittal Title',
            'description' => 'Updated description'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson("/api/zena/submittals/{$submittal->id}", $updateData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'title',
                        'description'
                    ]
                ]);

        $this->assertDatabaseHas('submittals', [
            'id' => $submittal->id,
            'title' => 'Updated Submittal Title'
        ]);
    }

    /**
     * Test Submittal deletion
     */
    public function test_can_delete_submittal()
    {
        $submittal = ZenaSubmittal::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson("/api/zena/submittals/{$submittal->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('submittals', [
            'id' => $submittal->id
        ]);
    }

    /**
     * Test Submittal validation
     */
    public function test_submittal_creation_requires_valid_data()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/submittals', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['project_id', 'title', 'description', 'submittal_type']);
    }

    /**
     * Test unauthorized access
     */
    public function test_unauthorized_access_returns_401()
    {
        $response = $this->getJson('/api/zena/submittals');
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
