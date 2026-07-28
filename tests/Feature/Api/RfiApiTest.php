<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Rfi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Traits\AuthenticationTestTrait;
use Tests\Traits\RouteNameTrait;
use Tests\Traits\TenantUserFactoryTrait;

class RfiApiTest extends TestCase
{
    use RefreshDatabase, WithFaker, AuthenticationTestTrait, RouteNameTrait, TenantUserFactoryTrait;

    protected User $user;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiActingAsTenantAdmin();
        $this->user = $this->apiFeatureUser;
        $this->project = Project::factory()->create([
            'created_by' => $this->user->id,
            'tenant_id' => $this->apiFeatureTenant->id,
        ]);
    }

    /**
     * Test RFI index endpoint
     */
    public function test_can_get_rfi_list()
    {
        // Create test RFIs
        Rfi::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
        ]);

        $response = $this->apiGet($this->zena('rfis.index'));

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'status',
                    'data' => [
                        '*' => [
                            'id',
                            'title',
                            'description',
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
     * Test RFI creation
     */
    public function test_can_create_rfi()
    {
        $rfiData = [
            'project_id' => $this->project->id,
            'title' => 'Test RFI',
            'description' => 'Test RFI description',
            'rfi_number' => 'RFI-001',
            'priority' => 'medium',
            'location' => 'Building A',
            'drawing_reference' => 'DW-001'
        ];

        $response = $this->apiPost($this->zena('rfis.store'), $rfiData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'title',
                        'description',
                        'status',
                        'priority'
                    ]
                ]);

        $this->assertDatabaseHas('rfis', [
            'title' => 'Test RFI',
            'project_id' => $this->project->id
        ]);
    }

    /**
     * Test RFI validation
     */
    public function test_rfi_creation_requires_valid_data()
    {
        $response = $this->apiPost($this->zena('rfis.store'), []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['project_id', 'title', 'description'], 'error.details.data');
    }

    /**
     * Test RFI show endpoint
     */
    public function test_can_get_single_rfi()
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
        ]);

        $response = $this->apiGet($this->zena('rfis.show', ['id' => $rfi->id]));

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'title',
                        'description',
                        'status',
                        'priority'
                    ]
                ]);
    }

    /**
     * Test RFI update
     */
    public function test_can_update_rfi()
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
        ]);

        $updateData = [
            'title' => 'Updated RFI Title',
            'description' => 'Updated description',
            'priority' => 'high'
        ];

        $response = $this->apiPut($this->zena('rfis.update', ['id' => $rfi->id]), $updateData);

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

        $this->assertDatabaseHas('rfis', [
            'id' => $rfi->id,
            'title' => 'Updated RFI Title'
        ]);
    }

    /**
     * Test RFI assignment
     */
    public function test_can_assign_rfi()
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
        ]);

        $assignData = [
            'assigned_to' => $this->user->id,
            'assignment_notes' => 'Please review this RFI'
        ];

        $response = $this->apiPost($this->zena('rfis.assign', ['id' => $rfi->id]), $assignData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'assigned_to',
                        'assignment_notes',
                        'status'
                    ]
                ]);

        $this->assertDatabaseHas('rfis', [
            'id' => $rfi->id,
            'assigned_to' => $this->user->id,
            'status' => 'in_progress'
        ]);
    }

    /**
     * Test RFI response
     */
    public function test_can_respond_to_rfi()
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'status' => 'in_progress'
        ]);

        $responseData = [
            'response' => 'This is the response to the RFI',
            'response_notes' => 'Additional notes',
            'status' => 'answered'
        ];

        $response = $this->apiPost($this->zena('rfis.respond', ['id' => $rfi->id]), $responseData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'response',
                        'status'
                    ]
                ]);

        $this->assertDatabaseHas('rfis', [
            'id' => $rfi->id,
            'response' => 'This is the response to the RFI',
            'status' => 'answered'
        ]);
    }

    /**
     * Test RFI escalation
     */
    public function test_can_escalate_rfi()
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'status' => 'in_progress'
        ]);

        $escalateData = [
            'escalated_to' => $this->user->id,
            'escalation_reason' => 'Urgent issue requiring immediate attention'
        ];

        $response = $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), $escalateData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'escalated_to',
                        'escalation_reason',
                        'status'
                    ]
                ]);

        $rfi->refresh();
        $this->assertNotNull($rfi->current_escalation_id);
        $this->assertSame('in_progress', $rfi->status);
        $this->assertSame($this->user->id, $rfi->escalated_to);
    }

    public function test_project_manager_can_escalate_rfi_in_their_project(): void
    {
        $pmRole = \App\Models\Role::firstOrCreate(
            ['name' => 'project_manager'],
            ['scope' => 'system', 'description' => 'Project Manager', 'is_active' => true],
        );
        $pmUser = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        \App\Models\UserRoleProject::create([
            'project_id' => $this->project->id, 'user_id' => $pmUser->id, 'role_id' => $pmRole->id,
        ]);
        // The `rbac:rfi.escalate` route middleware checks system-level role
        // attachment (User::roles()/user_roles pivot) + Permission::name, which is
        // independent of the project-scoped UserRoleProject check the controller
        // itself performs. Both must be satisfied for this request to pass.
        $pmUser->roles()->attach($pmRole->id);
        $permission = \App\Models\Permission::firstOrCreate(
            ['code' => 'rfi.escalate'],
            ['name' => 'rfi.escalate', 'module' => 'rfi', 'action' => 'escalate', 'description' => 'Escalate an RFI to another user'],
        );
        $pmRole->permissions()->syncWithoutDetaching([$permission->id]);

        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'open',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        \App\Models\UserRoleProject::create([
            'project_id' => $this->project->id, 'user_id' => $target->id, 'role_id' => $pmRole->id,
        ]);

        $token = $this->apiLoginToken($pmUser, $this->apiFeatureTenant);
        $response = $this->withHeaders($this->authHeadersForUser($pmUser, $token))
            ->postJson($this->zena('rfis.escalate', ['id' => $rfi->id]), [
                'escalation_reason' => 'Client needs urgent clarification',
                'escalated_to' => $target->id,
            ]);

        $response->assertStatus(200);
        $rfi->refresh();
        $this->assertNotNull($rfi->current_escalation_id);
        $this->assertSame('open', $rfi->status);
    }

    public function test_escalate_conflict_when_active_escalation_already_exists(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'open',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        $memberRole = \App\Models\Role::firstOrCreate(
            ['name' => 'project_manager'],
            ['scope' => 'system', 'description' => 'Project Manager', 'is_active' => true],
        );
        \App\Models\UserRoleProject::create([
            'project_id' => $this->project->id, 'user_id' => $target->id, 'role_id' => $memberRole->id,
        ]);

        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), ['escalation_reason' => 'First', 'escalated_to' => $target->id])->assertStatus(200);

        $response = $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), ['escalation_reason' => 'Second', 'escalated_to' => $target->id]);

        $response->assertStatus(409);
    }

    public function test_escalate_rejects_target_from_another_tenant(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'open',
        ]);
        $otherTenant = \App\Models\Tenant::factory()->create();
        $foreignTarget = User::factory()->create(['tenant_id' => $otherTenant->id, 'is_active' => true]);

        $response = $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), ['escalation_reason' => 'Urgent', 'escalated_to' => $foreignTarget->id]);

        $response->assertStatus(422);
    }

    public function test_escalate_rejects_deactivated_target(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'open',
        ]);
        $inactiveTarget = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => false]);

        $response = $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), ['escalation_reason' => 'Urgent', 'escalated_to' => $inactiveTarget->id]);

        $response->assertStatus(422);
    }

    public function test_escalate_blocked_on_closed_rfi(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'closed',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);

        $response = $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), ['escalation_reason' => 'Urgent', 'escalated_to' => $target->id]);

        $response->assertStatus(422);
    }

    public function test_escalation_target_can_resolve_their_own_escalation(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'open',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        $memberRole = \App\Models\Role::firstOrCreate(['name' => 'member'], ['scope' => 'system', 'description' => 'Member', 'is_active' => true]);
        \App\Models\UserRoleProject::firstOrCreate(['project_id' => $this->project->id, 'user_id' => $target->id], ['role_id' => $memberRole->id]);
        // The `rbac:rfi.escalate` route middleware checks system-level role
        // attachment (User::roles()/user_roles pivot) + Permission::name, which is
        // independent of the project-scoped UserRoleProject check above. Both must
        // be satisfied for the target's resolve request to pass the middleware.
        $target->roles()->attach($memberRole->id);
        $permission = \App\Models\Permission::firstOrCreate(
            ['code' => 'rfi.escalate'],
            ['name' => 'rfi.escalate', 'module' => 'rfi', 'action' => 'escalate', 'description' => 'Escalate an RFI to another user'],
        );
        $memberRole->permissions()->syncWithoutDetaching([$permission->id]);

        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), ['escalation_reason' => 'Urgent', 'escalated_to' => $target->id])->assertStatus(200);

        $token = $this->apiLoginToken($target, $this->apiFeatureTenant);
        // The "sanctum" guard (Illuminate\Auth\RequestGuard) memoizes the resolved
        // user for the lifetime of the shared AuthManager instance, which persists
        // across every request made within this test method. Without resetting it
        // here, this request (bearer token for $target) would still resolve to
        // whichever user the guard authenticated first (via apiPost() above).
        app('auth')->forgetGuards();
        $response = $this->withHeaders($this->authHeadersForUser($target, $token))
            ->postJson($this->zena('rfis.resolve-escalation', ['id' => $rfi->id]), ['resolution' => 'Answered the client directly by phone']);

        $response->assertStatus(200);
        $rfi->refresh();
        $this->assertNull($rfi->current_escalation_id);
    }

    public function test_resolve_escalation_by_unrelated_user_is_forbidden(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'open',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        $memberRole = \App\Models\Role::firstOrCreate(['name' => 'member'], ['scope' => 'system', 'description' => 'Member', 'is_active' => true]);
        \App\Models\UserRoleProject::firstOrCreate(['project_id' => $this->project->id, 'user_id' => $target->id], ['role_id' => $memberRole->id]);
        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), ['escalation_reason' => 'Urgent', 'escalated_to' => $target->id])->assertStatus(200);

        $unrelated = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        $unrelated->roles()->attach($memberRole->id);
        $permission = \App\Models\Permission::firstOrCreate(
            ['code' => 'rfi.escalate'],
            ['name' => 'rfi.escalate', 'module' => 'rfi', 'action' => 'escalate', 'description' => 'Escalate an RFI to another user'],
        );
        $memberRole->permissions()->syncWithoutDetaching([$permission->id]);

        $token = $this->apiLoginToken($unrelated, $this->apiFeatureTenant);
        // See the identical comment in test_escalation_target_can_resolve_their_own_escalation():
        // the "sanctum" guard memoizes the resolved user across requests within a
        // single test method, so this must be reset before switching bearer tokens.
        app('auth')->forgetGuards();
        $response = $this->withHeaders($this->authHeadersForUser($unrelated, $token))
            ->postJson($this->zena('rfis.resolve-escalation', ['id' => $rfi->id]), ['resolution' => 'Trying to resolve an escalation that is not mine']);

        $response->assertStatus(403);
    }

    public function test_resolve_escalation_twice_returns_conflict(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'open',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        $memberRole = \App\Models\Role::firstOrCreate(['name' => 'member'], ['scope' => 'system', 'description' => 'Member', 'is_active' => true]);
        \App\Models\UserRoleProject::firstOrCreate(['project_id' => $this->project->id, 'user_id' => $target->id], ['role_id' => $memberRole->id]);
        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), ['escalation_reason' => 'Urgent', 'escalated_to' => $target->id])->assertStatus(200);

        $this->apiPost($this->zena('rfis.resolve-escalation', ['id' => $rfi->id]), ['resolution' => 'First'])->assertStatus(200);

        $response = $this->apiPost($this->zena('rfis.resolve-escalation', ['id' => $rfi->id]), ['resolution' => 'Second attempt']);

        $response->assertStatus(409);
    }

    /**
     * Test RFI closure
     */
    public function test_can_close_rfi()
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'status' => 'answered'
        ]);

        $response = $this->apiPost($this->zena('rfis.close', ['id' => $rfi->id]));

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'status',
                        'closed_at'
                    ]
                ]);

        $this->assertDatabaseHas('rfis', [
            'id' => $rfi->id,
            'status' => 'closed'
        ]);
    }

    /**
     * Test RFI deletion
     */
    public function test_can_delete_rfi()
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
        ]);

        $response = $this->apiDelete($this->zena('rfis.destroy', ['id' => $rfi->id]));

        $response->assertStatus(200);

        $this->assertDatabaseMissing('rfis', [
            'id' => $rfi->id
        ]);
    }

    /**
     * Test unauthorized access
     */
    public function test_unauthorized_access_returns_401()
    {
        $response = $this->getJson($this->zena('rfis.index'));
        $response->assertStatus(401);
    }

}
