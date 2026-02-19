<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\ZenaProject;
use App\Models\ZenaRfi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Traits\AuthenticationTestTrait;
use Tests\Traits\RouteNameTrait;

class RfiApiTest extends TestCase
{
    use RefreshDatabase, WithFaker, AuthenticationTestTrait, RouteNameTrait;

    protected User $user;
    protected ZenaProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiActingAsTenantAdmin();
        $this->user = $this->apiFeatureUser;
        $this->project = ZenaProject::factory()->create([
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
        ZenaRfi::factory()->count(3)->create([
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
        $rfi = ZenaRfi::factory()->create([
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
        $rfi = ZenaRfi::factory()->create([
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
        $rfi = ZenaRfi::factory()->create([
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
        $rfi = ZenaRfi::factory()->create([
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
        $rfi = ZenaRfi::factory()->create([
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

        $this->assertDatabaseHas('rfis', [
            'id' => $rfi->id,
            'escalated_to' => $this->user->id,
            'status' => 'escalated'
        ]);
    }

    /**
     * Test RFI closure
     */
    public function test_can_close_rfi()
    {
        $rfi = ZenaRfi::factory()->create([
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
        $rfi = ZenaRfi::factory()->create([
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
