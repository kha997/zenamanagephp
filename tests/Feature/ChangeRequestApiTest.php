<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ChangeRequest;
use App\Models\Project;
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
            'change-request.submit',
            'change-request.approve',
            'change-request.reject',
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
        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
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
    }

    /**
     * Test approve change request
     */
    public function test_can_approve_change_request()
    {
        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
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
    }

    /**
     * Test reject change request
     */
    public function test_can_reject_change_request()
    {
        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
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
}
