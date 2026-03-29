<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Baseline;
use App\Models\ChangeRequest;
use App\Models\CrLink;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Src\RBAC\Models\Permission;
use Src\RBAC\Models\Role;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;
use Tests\Traits\SchemaAwareChangeRequestAssertions;

class ChangeRequestApiTest extends TestCase
{
    use RefreshDatabase, WithFaker, AuthenticationTestTrait;
    use SchemaAwareChangeRequestAssertions;

    protected Tenant $tenant;
    protected Project $project;
    protected User $user;
    protected array $headers = [];
    protected ?string $token = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->project = Project::factory()->for($this->tenant)->create();

        $this->user = $this->apiActingAsTenantAdmin([
            'tenant_id' => $this->tenant->id,
        ], $this->tenant);

        $this->token = $this->apiFeatureToken;
        $this->headers = $this->apiHeaders;

        $this->ensureChangeRequestPermissions();
    }

    private function ensureChangeRequestPermissions(array $permissionCodes = null): void
    {
        $permissionCodes = $permissionCodes ?? [
            'change-request.create',
            'change-request.view',
            'change-request.update',
            'change-request.delete',
            'change-request.submit',
            'change-request.approve',
            'change-request.reject',
            'change-request.apply',
        ];

        $role = Role::firstOrCreate(
            ['name' => 'test-change-request-role', 'scope' => 'system'],
            ['description' => 'Temporary system role for change request tests']
        );

        foreach ($permissionCodes as $permissionCode) {
            $parts = explode('.', $permissionCode, 2);
            $module = $parts[0];
            $action = $parts[1] ?? '*';

            $permission = Permission::firstOrCreate(
                ['code' => $permissionCode],
                [
                    'module' => $module,
                    'action' => $action,
                    'description' => 'Permission used in tests: ' . $permissionCode,
                ]
            );

            $role->permissions()->syncWithoutDetaching($permission->id);
        }

        $this->user->systemRoles()->syncWithoutDetaching($role->id);
    }

    /**
     * Test create change request
     */
    public function test_can_create_change_request()
    {
        $payload = [
            'project_id' => $this->project->id,
            'title' => 'Change Material Specification',
            'description' => 'Shift from granite to marble finishing for the lobby.',
            'change_type' => 'scope',
            'impact_analysis' => 'The scope change only affects select areas and can be absorbed.',
            'cost_impact' => 12500,
            'schedule_impact_days' => 7,
            'priority' => 'medium',
            'justification' => 'Reduce long-term maintenance costs',
            'alternatives_considered' => 'Maintain current finishing or use porcelain instead'
        ];

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/zena/change-requests', $payload);

        $response->assertStatus(201)
                 ->assertJsonPath('status', 'success');

        $this->assertChangeRequestResponse(
            $response,
            ['project_id', 'title', 'description', 'priority', 'change_type', 'impact_analysis', 'justification', 'change_number']
        );

        $this->assertDatabaseHas('change_requests', [
            'title' => $payload['title'],
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'draft'
        ]);
    }

    /**
     * Test get all change requests
     */
    public function test_can_get_all_change_requests()
    {
        ChangeRequest::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'status' => 'draft',
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/zena/change-requests');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'status',
                     'status_text',
                     'data',
                     'meta' => [
                         'pagination' => [
                             'page',
                             'per_page',
                             'total',
                             'last_page',
                         ]
                     ]
                 ]);
    }

    /**
     * Test submit change request for approval
     */
    public function test_can_submit_change_request_for_approval()
    {
        $approver = $this->createTenantUser($this->tenant);

        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'assigned_to' => $approver->id,
            'status' => 'draft'
        ]);

        $response = $this->withHeaders($this->headers)
            ->postJson("/api/zena/change-requests/{$changeRequest->id}/submit");

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonPath('data.status', 'submitted');

        $this->assertChangeRequestResponse($response, ['project_id']);

        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'submitted'
        ]);

        $this->assertSame(1, Notification::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('user_id', $approver->id)
            ->where('type', 'change_request_submitted')
            ->where('channel', Notification::CHANNEL_INAPP)
            ->count());
    }

    /**
     * Test approve change request
     */
    public function test_can_approve_change_request()
    {
        $requester = $this->createTenantUser($this->tenant);

        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'requested_by' => $requester->id,
            'status' => 'submitted'
        ]);

        $payload = [
            'approval_comments' => 'Approved with conditions',
            'approved_cost' => 15000,
        ];

        $response = $this->withHeaders($this->headers)
            ->postJson("/api/zena/change-requests/{$changeRequest->id}/approve", $payload);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonPath('data.status', 'approved');

        $this->assertChangeRequestResponse(
            $response,
            ['project_id', 'approved_by']
        );

        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'approved',
            'approved_by' => $this->user->id,
        ]);

        $this->assertSame(1, Notification::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('user_id', $requester->id)
            ->where('type', 'change_request_approved')
            ->where('channel', Notification::CHANNEL_INAPP)
            ->count());
    }

    /**
     * Test reject change request
     */
    public function test_can_reject_change_request()
    {
        $requester = $this->createTenantUser($this->tenant);

        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'requested_by' => $requester->id,
            'status' => 'submitted'
        ]);

        $payload = [
            'rejection_reason' => 'Budget constraints',
            'rejection_comments' => 'Cannot absorb this cost this quarter'
        ];

        $response = $this->withHeaders($this->headers)
            ->postJson("/api/zena/change-requests/{$changeRequest->id}/reject", $payload);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonPath('data.status', 'rejected')
                 ->assertJsonPath('data.rejection_reason', $payload['rejection_reason']);

        $this->assertChangeRequestResponse(
            $response,
            ['project_id', 'rejection_reason', 'rejection_comments', 'rejected_by']
        );

        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'rejected',
            'rejection_reason' => $payload['rejection_reason'],
            'rejected_by' => $this->user->id
        ]);

        $this->assertSame(1, Notification::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('user_id', $requester->id)
            ->where('type', 'change_request_rejected')
            ->where('channel', Notification::CHANNEL_INAPP)
            ->count());
    }

    /**
     * Test validation errors
     */
    public function test_create_change_request_validation_errors()
    {
        $response = $this->withHeaders($this->headers)
            ->postJson('/api/zena/change-requests', []);

        $response->assertStatus(422)
                 ->assertJsonPath('status', 'error')
                 ->assertJsonPath('error.details.data.project_id.0', 'validation.required')
                 ->assertJsonPath('error.details.data.title.0', 'validation.required')
                 ->assertJsonPath('error.details.data.description.0', 'validation.required')
                 ->assertJsonPath('error.details.data.change_type.0', 'validation.required')
                 ->assertJsonPath('error.details.data.impact_analysis.0', 'validation.required')
                 ->assertJsonPath('error.details.data.priority.0', 'validation.required')
                 ->assertJsonPath('error.details.data.justification.0', 'validation.required');
    }

    /**
     * Ensure submit rejects non-draft requests.
     */
    public function test_submit_requires_draft_status()
    {
        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'status' => 'submitted'
        ]);

        $response = $this->withHeaders($this->headers)
            ->postJson("/api/zena/change-requests/{$changeRequest->id}/submit");

        $response->assertStatus(400)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Only draft change requests can be submitted'
                 ]);
    }

    public function test_approve_requires_submitted_status(): void
    {
        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'status' => 'draft',
        ]);

        $response = $this->withHeaders($this->headers)
            ->postJson("/api/zena/change-requests/{$changeRequest->id}/approve", [
                'approval_comments' => 'Attempted approval from draft',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'Only submitted change requests can be approved',
            ]);
    }

    public function test_reject_requires_submitted_status(): void
    {
        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'status' => 'draft',
        ]);

        $response = $this->withHeaders($this->headers)
            ->postJson("/api/zena/change-requests/{$changeRequest->id}/reject", [
                'rejection_reason' => 'Attempted rejection from draft',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'Only submitted change requests can be rejected',
            ]);
    }

    public function test_apply_requires_approved_status(): void
    {
        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'status' => 'submitted',
        ]);

        $response = $this->withHeaders($this->headers)
            ->postJson("/api/zena/change-requests/{$changeRequest->id}/apply", [
                'implementation_notes' => 'Attempted apply from submitted',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'Only approved change requests can be applied',
            ]);
    }

    public function test_apply_does_not_create_notification_in_this_round(): void
    {
        $requester = $this->createTenantUser($this->tenant);

        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'requested_by' => $requester->id,
            'status' => 'approved',
        ]);

        $response = $this->withHeaders($this->headers)
            ->postJson("/api/zena/change-requests/{$changeRequest->id}/apply", [
                'implementation_notes' => 'Implemented without notification proof expansion',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'implemented');

        $this->assertSame(0, Notification::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('project_id', $this->project->id)
            ->whereIn('type', [
                'change_request_submitted',
                'change_request_approved',
                'change_request_rejected',
            ])
            ->count());
    }

    public function test_apply_creates_canonical_task_and_baseline_artifacts(): void
    {
        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'status' => 'approved',
            'impact_days' => 5,
            'impact_cost' => 4200,
            'change_number' => 'CR-S3-3-0001',
        ]);

        $response = $this->withHeaders($this->headers)
            ->postJson("/api/zena/change-requests/{$changeRequest->id}/apply", [
                'implementation_notes' => 'S3.3 canonical delta proof',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'implemented');

        $task = Task::query()
            ->where('project_id', $this->project->id)
            ->where('tenant_id', $this->tenant->id)
            ->where('name', 'CR delta: ' . $changeRequest->title)
            ->first();

        $this->assertNotNull($task, 'Expected canonical task delta to be persisted.');

        $this->assertDatabaseHas('cr_links', [
            'change_request_id' => $changeRequest->id,
            'linked_type' => CrLink::LINKED_TYPE_TASK,
            'linked_id' => $task->id,
        ]);

        $baseline = Baseline::query()
            ->where('project_id', $this->project->id)
            ->where('linked_contract_id', $changeRequest->id)
            ->first();

        $this->assertNotNull($baseline, 'Expected canonical baseline delta to be persisted.');
        $this->assertSame('contract', $baseline->type);
    }

    public function test_update_cannot_mutate_workflow_status_directly(): void
    {
        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'status' => 'draft',
        ]);

        $response = $this->withHeaders($this->headers)
            ->putJson("/api/zena/change-requests/{$changeRequest->id}", [
                'status' => 'approved',
                'title' => 'Attempted direct workflow mutation',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('error.details.data.status.0', 'validation.prohibited');

        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'status' => 'draft',
            'title' => $changeRequest->title,
        ]);
    }

    public function test_cross_tenant_change_request_show_returns_not_found(): void
    {
        $tenantB = Tenant::factory()->create();
        $userB = $this->createTenantUser($tenantB, [], null, ['change-request.view']);
        $tokenB = $this->apiLoginToken($userB, $tenantB);

        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'status' => 'draft',
        ]);

        $response = $this->withHeaders($this->authHeadersForUser($userB, $tokenB))
            ->getJson("/api/zena/change-requests/{$changeRequest->id}");

        $response->assertStatus(404)
            ->assertJsonFragment([
                'message' => 'Change request not found',
            ]);
    }

    public function test_cross_tenant_change_request_update_returns_not_found(): void
    {
        $tenantB = Tenant::factory()->create();
        $userB = $this->createTenantUser($tenantB, [], null, ['change-request.update']);
        $tokenB = $this->apiLoginToken($userB, $tenantB);

        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'status' => 'draft',
        ]);

        $response = $this->withHeaders($this->authHeadersForUser($userB, $tokenB))
            ->putJson("/api/zena/change-requests/{$changeRequest->id}", [
                'title' => 'Cross tenant update attempt',
            ]);

        $response->assertStatus(404)
            ->assertJsonFragment([
                'message' => 'Change request not found',
            ]);
    }

    public function test_cross_tenant_change_request_destroy_returns_not_found(): void
    {
        $tenantB = Tenant::factory()->create();
        $userB = $this->createTenantUser($tenantB, [], null, ['change-request.delete']);
        $tokenB = $this->apiLoginToken($userB, $tenantB);

        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'status' => 'draft',
        ]);

        $response = $this->withHeaders($this->authHeadersForUser($userB, $tokenB))
            ->deleteJson("/api/zena/change-requests/{$changeRequest->id}");

        $response->assertStatus(404)
            ->assertJsonFragment([
                'message' => 'Change request not found',
            ]);
    }

    public function test_cross_tenant_change_request_submit_returns_not_found(): void
    {
        $tenantB = Tenant::factory()->create();
        $userB = $this->createTenantUser($tenantB, [], null, ['change-request.submit']);
        $tokenB = $this->apiLoginToken($userB, $tenantB);

        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'status' => 'draft',
        ]);

        $response = $this->withHeaders($this->authHeadersForUser($userB, $tokenB))
            ->postJson("/api/zena/change-requests/{$changeRequest->id}/submit");

        $response->assertStatus(404)
            ->assertJsonFragment([
                'message' => 'Change request not found',
            ]);
    }

    public function test_cross_tenant_change_request_approve_returns_not_found(): void
    {
        $tenantB = Tenant::factory()->create();
        $userB = $this->createTenantUser($tenantB, [], null, ['change-request.approve']);
        $tokenB = $this->apiLoginToken($userB, $tenantB);

        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'status' => 'submitted',
        ]);

        $response = $this->withHeaders($this->authHeadersForUser($userB, $tokenB))
            ->postJson("/api/zena/change-requests/{$changeRequest->id}/approve", [
                'approval_comments' => 'Cross-tenant approval attempt',
            ]);

        $response->assertStatus(404)
            ->assertJsonFragment([
                'message' => 'Change request not found',
            ]);
    }

    public function test_approve_requires_rbac_permission(): void
    {
        $restrictedUser = $this->createTenantUser($this->tenant, [], [], ['change-request.view']);
        $restrictedToken = $this->apiLoginToken($restrictedUser, $this->tenant);

        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'status' => 'submitted',
        ]);

        $this->withHeaders($this->authHeadersForUser($restrictedUser, $restrictedToken))
            ->postJson("/api/zena/change-requests/{$changeRequest->id}/approve", [
                'approval_comments' => 'RBAC denied approval attempt',
            ])
            ->assertStatus(403);
    }
}
