<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\ZenaProject;
use App\Models\ZenaChangeRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Traits\RouteNameTrait;
use Tests\Traits\SchemaAwareChangeRequestAssertions;

class ChangeRequestApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;
    use SchemaAwareChangeRequestAssertions;
    use RouteNameTrait;

    protected $user;
    protected $project;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->project = ZenaProject::factory()->create([
            'created_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
        ]);

        $superAdminRole = Role::firstOrCreate([
            'name' => 'super_admin',
        ], [
            'scope' => Role::SCOPE_SYSTEM,
            'is_active' => true,
        ]);
        $this->user->roles()->syncWithoutDetaching($superAdminRole->id);

        $this->token = $this->generateJwtToken($this->user);
    }

    private function getAuthHeaders(array $overrides = []): array
    {
        return array_merge([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $this->user->tenant_id,
            'Authorization' => 'Bearer ' . $this->token,
        ], $overrides);
    }

    /**
     * Test Change Request index endpoint
     */
    public function test_can_get_change_request_list()
    {
        ZenaChangeRequest::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
        ]);

        $response = $this->withHeaders($this->getAuthHeaders())
            ->getJson($this->zena('change-requests.index'));

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        '*' => [
                            'id',
                            'title',
                            'change_type',
                            'status',
                            'priority',
                            'created_at'
                        ]
                    ],
                    'meta' => [
                        'pagination' => [
                            'page',
                            'per_page',
                            'total',
                            'last_page'
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
            'priority' => 'medium'
        ];

        $response = $this->withHeaders($this->getAuthHeaders())
            ->postJson($this->zena('change-requests.store'), $changeRequestData);

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
        $changeRequest = ZenaChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'status' => 'draft'
        ]);

        $response = $this->withHeaders($this->getAuthHeaders())
            ->postJson($this->zena('change-requests.submit', ['id' => $changeRequest->id]));

        $response->assertStatus(200);
        $this->assertChangeRequestResponse($response, ['submitted_at']);
        $response->assertJsonPath('data.status', 'submitted');

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
        $changeRequest = ZenaChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'status' => 'pending_approval'
        ]);

        $approvalData = [
            'approved_cost' => 15000.00,
            'approval_comments' => 'Approved with modifications'
        ];

        $response = $this->withHeaders($this->getAuthHeaders())
            ->postJson($this->zena('change-requests.approve', ['id' => $changeRequest->id]), $approvalData);

        $response->assertStatus(200);
        $this->assertChangeRequestResponse($response, ['approved_at', 'approved_by', 'approval_notes']);
        $response->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'status' => 'approved',
            'approved_by' => $this->user->id
        ]);
    }

    /**
     * Test Change Request rejection
     */
    public function test_can_reject_change_request()
    {
        $changeRequest = ZenaChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'status' => 'pending_approval'
        ]);

        $rejectionData = [
            'rejection_reason' => 'Cost impact too high',
            'rejection_comments' => 'Please revise the proposal'
        ];

        $response = $this->withHeaders($this->getAuthHeaders())
            ->postJson($this->zena('change-requests.reject', ['id' => $changeRequest->id]), $rejectionData);

        $response->assertStatus(200);
        $this->assertChangeRequestResponse($response, ['rejection_reason', 'rejected_at', 'rejected_by']);
        $response->assertJsonPath('data.status', 'rejected');

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
        $changeRequest = ZenaChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'status' => 'approved'
        ]);

        $implementationData = [
            'implementation_notes' => 'Change has been implemented successfully'
        ];

        $response = $this->withHeaders($this->getAuthHeaders())
            ->postJson($this->zena('change-requests.apply', ['id' => $changeRequest->id]), $implementationData);

        $response->assertStatus(200);
        $this->assertChangeRequestResponse($response, ['implementation_notes', 'implemented_at', 'implemented_by']);
        $response->assertJsonPath('data.status', 'implemented');

        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'status' => 'implemented'
        ]);
    }

    /**
     * Test Change Request update
     */
    public function test_can_update_change_request()
    {
        $changeRequest = ZenaChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id
        ]);

        $updateData = [
            'title' => 'Updated Change Request Title',
            'description' => 'Updated description',
            'priority' => 'high'
        ];

        $response = $this->withHeaders($this->getAuthHeaders())
            ->putJson($this->zena('change-requests.update', ['id' => $changeRequest->id]), $updateData);

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
        $changeRequest = ZenaChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id
        ]);

        $response = $this->withHeaders($this->getAuthHeaders())
            ->deleteJson($this->zena('change-requests.destroy', ['id' => $changeRequest->id]));

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
        $response = $this->withHeaders($this->getAuthHeaders())
            ->postJson($this->zena('change-requests.store'), []);

        $response->assertStatus(422);

        $validationData = $response->json('error.details.data', []);
        foreach (['project_id', 'title', 'description', 'change_type'] as $field) {
            $this->assertArrayHasKey($field, $validationData);
        }
    }

    /**
     * Test Change Request impact level calculation
     */
    public function test_change_request_impact_level_calculation()
    {
        $changeRequest = ZenaChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
        ]);

        $response = $this->withHeaders($this->getAuthHeaders())
            ->getJson($this->zena('change-requests.show', ['id' => $changeRequest->id]));

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'impact_level'
                    ]
                ]);

        $impactLevel = $response->json('data.impact_level');
        $this->assertContains($impactLevel, ['low', 'medium', 'high'], 'Impact level should be one of the expected tiers');
    }

    /**
     * Test unauthorized access
     */
    public function test_unauthorized_access_returns_401()
    {
        $response = $this->getJson($this->zena('change-requests.index'));
        $response->assertStatus(401);
    }

    /**
     * Generate JWT token for testing
     */
    private function generateJwtToken(User $user): string
    {
        return $user->createToken('change-request-tests')->plainTextToken;
    }
}
