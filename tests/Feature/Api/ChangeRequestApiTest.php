<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Baseline;
use App\Models\CrLink;
use App\Models\Task;
use Tests\TestCase;
use App\Models\Notification;
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
        $approver = User::factory()->create([
            'tenant_id' => $this->user->tenant_id,
        ]);

        $changeRequest = ZenaChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'assigned_to' => $approver->id,
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

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->user->tenant_id,
            'user_id' => $approver->id,
            'type' => 'change_request_submitted',
            'channel' => Notification::CHANNEL_INAPP,
        ]);
    }

    /**
     * Test Change Request approval
     */
    public function test_can_approve_change_request()
    {
        $requester = User::factory()->create([
            'tenant_id' => $this->user->tenant_id,
        ]);

        $changeRequest = ZenaChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'requested_by' => $requester->id,
            'tenant_id' => $this->user->tenant_id,
            'status' => 'submitted'
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

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->user->tenant_id,
            'user_id' => $requester->id,
            'type' => 'change_request_approved',
            'channel' => Notification::CHANNEL_INAPP,
        ]);
    }

    /**
     * Test Change Request rejection
     */
    public function test_can_reject_change_request()
    {
        $requester = User::factory()->create([
            'tenant_id' => $this->user->tenant_id,
        ]);

        $changeRequest = ZenaChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'requested_by' => $requester->id,
            'tenant_id' => $this->user->tenant_id,
            'status' => 'submitted'
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

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->user->tenant_id,
            'user_id' => $requester->id,
            'type' => 'change_request_rejected',
            'channel' => Notification::CHANNEL_INAPP,
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
            'status' => 'implemented',
            'implementation_notes' => $implementationData['implementation_notes'],
        ]);

        $this->assertDatabaseMissing('notifications', [
            'tenant_id' => $this->user->tenant_id,
            'project_id' => $this->project->id,
            'type' => 'change_request_implemented',
        ]);

        $task = Task::query()
            ->where('project_id', $this->project->id)
            ->where('tenant_id', $this->user->tenant_id)
            ->where('name', 'CR delta: ' . $changeRequest->title)
            ->first();

        $this->assertNotNull($task, 'Expected canonical task delta to be created.');

        $this->assertDatabaseHas('cr_links', [
            'change_request_id' => $changeRequest->id,
            'linked_type' => CrLink::LINKED_TYPE_TASK,
            'linked_id' => $task->id,
        ]);

        $baseline = Baseline::query()
            ->where('project_id', $this->project->id)
            ->where('linked_contract_id', $changeRequest->id)
            ->first();

        $this->assertNotNull($baseline, 'Expected canonical baseline delta to be created.');

        $this->assertDatabaseHas('baseline_history', [
            'baseline_id' => $baseline->id,
            'to_version' => $baseline->version,
        ]);
    }

    public function test_can_get_change_request_timeline_from_canonical_audit_logs(): void
    {
        $changeRequest = ZenaChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'status' => 'draft',
        ]);

        $otherChangeRequest = ZenaChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'status' => 'draft',
        ]);

        $this->withHeaders($this->getAuthHeaders())
            ->postJson($this->zena('change-requests.submit', ['id' => $changeRequest->id]))
            ->assertStatus(200);

        $this->withHeaders($this->getAuthHeaders())
            ->postJson($this->zena('change-requests.approve', ['id' => $changeRequest->id]), [
                'approval_comments' => 'Audit-backed approval',
            ])
            ->assertStatus(200);

        AuditLog::query()->create([
            'user_id' => (string) $this->user->id,
            'tenant_id' => (string) $this->user->tenant_id,
            'action' => 'zena.change_request.submit',
            'entity_type' => 'change_request',
            'entity_id' => (string) $otherChangeRequest->id,
            'project_id' => (string) $this->project->id,
            'route' => 'api/zena/change-requests/' . $otherChangeRequest->id . '/submit',
            'method' => 'POST',
            'status_code' => 200,
        ]);

        $response = $this->withHeaders($this->getAuthHeaders())
            ->getJson($this->zena('change-requests.timeline', ['id' => $changeRequest->id]));

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.action', 'zena.change_request.submit')
            ->assertJsonPath('data.0.status', 'submitted')
            ->assertJsonPath('data.0.user_id', (string) $this->user->id)
            ->assertJsonPath('data.1.action', 'zena.change_request.approve')
            ->assertJsonPath('data.1.status', 'approved');

        $entityIds = array_column($response->json('data'), 'audit_log_id');
        $this->assertCount(2, $entityIds);
        $this->assertSame(
            [(string) $changeRequest->id, (string) $changeRequest->id],
            AuditLog::query()
                ->whereIn('id', $entityIds)
                ->orderBy('created_at')
                ->orderBy('id')
                ->pluck('entity_id')
                ->all()
        );
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
