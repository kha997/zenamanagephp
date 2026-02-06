<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Src\CoreProject\Models\Project;
use Src\ChangeRequest\Models\ChangeRequest;
use Src\RBAC\Models\Role;
use Src\RBAC\Models\Permission;
use Illuminate\Support\Facades\Hash;
use Tests\Traits\SchemaAwareChangeRequestAssertions;

class ChangeRequestApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;
    use SchemaAwareChangeRequestAssertions;

    protected $user;
    protected $tenant;
    protected $project;
    protected $token;

    /**
     * Setup method
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create([
            'id' => 1,
            'name' => 'Test Company',
            'domain' => 'test.com'
        ]);
        
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'password' => Hash::make('password123'),
        ]);
        
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        
        $this->createRolesAndPermissions();
        
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password123',
        ]);
        
        $this->token = $loginResponse->json('data.token');
    }

    /**
     * Tạo roles và permissions cho test
     */
    private function createRolesAndPermissions()
    {
        $permissions = [
            'change_request.create',
            'change_request.read',
            'change_request.update',
            'change_request.approve',
            'change_request.reject',
        ];
        
        foreach ($permissions as $permissionCode) {
            Permission::create([
                'code' => $permissionCode,
                'module' => 'change_request',
                'action' => explode('.', $permissionCode)[1],
                'description' => 'Permission for ' . $permissionCode
            ]);
        }
        
        $adminRole = Role::create([
            'name' => 'Admin',
            'scope' => 'system',
            'description' => 'System Administrator'
        ]);
        
        $adminRole->permissions()->attach(
            Permission::whereIn('code', $permissions)->pluck('id')
        );
        
        $this->user->systemRoles()->attach($adminRole->id);
    }

    /**
     * Test create change request
     */
    public function test_can_create_change_request()
    {
        $crData = [
            'title' => 'Change Material Specification',
            'description' => 'Change from granite to marble flooring',
            'project_id' => $this->project->id,
            'priority' => 'medium',
            'visibility' => 'internal',
            'justification' => 'Reduce long-term maintenance costs',
            'tags' => ['flooring', 'materials']
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/change-requests', $crData);

        $response->assertStatus(201)
                 ->assertJsonPath('status', 'success');

        $this->assertChangeRequestResponse(
            $response,
            ['project_id', 'title', 'description', 'priority', 'visibility', 'change_number'],
            false
        );

        $this->assertDatabaseHas('change_requests', [
            'title' => $crData['title'],
            'project_id' => $this->project->id,
            'status' => 'draft'
        ]);
    }

    /**
     * Test get all change requests
     */
    public function test_can_get_all_change_requests()
    {
        ChangeRequest::factory()->count(3)->create([
            'project_id' => $this->project->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/change-requests');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'data',
                         'meta' => [
                             'total',
                             'status_summary',
                             'priority_summary',
                             'total_impact_cost',
                             'total_impact_days',
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
            'project_id' => $this->project->id,
            'status' => 'draft'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/v1/change-requests/{$changeRequest->id}/submit");

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonPath('data.status', 'awaiting_approval');

        $this->assertChangeRequestResponse($response, ['project_id'], false);

        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'status' => 'awaiting_approval'
        ]);
    }

    /**
     * Test approve change request
     */
    public function test_can_approve_change_request()
    {
        $changeRequest = ChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'awaiting_approval'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/v1/change-requests/{$changeRequest->id}/approve", [
            'decision_note' => 'Approved with conditions'
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonPath('data.status', 'approved');

        $this->assertChangeRequestResponse(
            $response,
            ['project_id', 'approved_by', 'decision_note'],
            false
        );

        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'status' => 'approved',
            'decided_by' => $this->user->id,
            'decision_note' => 'Approved with conditions'
        ]);
    }

    /**
     * Test reject change request
     */
    public function test_can_reject_change_request()
    {
        $changeRequest = ChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'awaiting_approval'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/v1/change-requests/{$changeRequest->id}/reject", [
            'decision_note' => 'Budget constraints'
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonPath('data.status', 'rejected');

        $this->assertChangeRequestResponse($response, ['project_id', 'rejection_reason'], false);

        $this->assertDatabaseHas('change_requests', [
            'id' => $changeRequest->id,
            'status' => 'rejected',
            'decided_by' => $this->user->id,
            'decision_note' => 'Budget constraints'
        ]);
    }

    /**
     * Test validation errors
     */
    public function test_create_change_request_validation_errors()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/change-requests', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['title', 'description', 'project_id']);
    }

    /**
     * Test cannot approve draft change request
     */
    public function test_cannot_approve_draft_change_request()
    {
        $changeRequest = ChangeRequest::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'draft'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/v1/change-requests/{$changeRequest->id}/approve");

        $response->assertStatus(400)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Chỉ có thể phê duyệt yêu cầu đang chờ phê duyệt'
                 ]);
    }
}
