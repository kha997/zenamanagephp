<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\ZenaProject;
use App\Models\ZenaSubmittal;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Traits\RouteNameTrait;

class SubmittalApiTest extends TestCase
{
    use RefreshDatabase, WithFaker, RouteNameTrait;

    protected $user;
    protected $project;
    protected $token;
    protected array $zenaAuthHeaders = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->project = ZenaProject::factory()->create([
            'created_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id
        ]);
        $this->syncZenaProjectRecord($this->project);

        $this->assignSuperAdminRole($this->user);
        $this->token = $this->loginZenaUser($this->user);
        $this->zenaAuthHeaders = $this->buildZenaAuthHeaders();
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

        $response = $this->withZenaAuth()->getJson($this->zena('submittals.index'));

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'title',
                            'submittal_type',
                            'status',
                            'created_at'
                        ]
                    ],
                    'meta' => [
                        'pagination' => [
                            'page',
                            'per_page',
                            'total',
                            'last_page',
                        ],
                    ],
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
            'submittal_type' => 'shop_drawing',
            'specification_section' => 'Section 1',
            'contractor' => 'Test Contractor',
            'manufacturer' => 'Test Manufacturer'
        ];

        $response = $this->withZenaAuth()->postJson($this->zena('submittals.store'), $submittalData);

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
        $submittalData = [
            'project_id' => $this->project->id,
            'title' => 'Submission Draft',
            'description' => 'Ready for submission',
            'submittal_number' => 'BATCH-001',
            'submittal_type' => 'shop_drawing'
        ];

        $createResponse = $this->withZenaAuth()->postJson($this->zena('submittals.store'), $submittalData);
        $createResponse->assertStatus(201);
        $submittalId = $createResponse->json('data.id');

        $response = $this->withZenaAuth()->postJson($this->zena('submittals.submit', ['id' => $submittalId]));

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
            'id' => $submittalId,
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

        $response = $this->withZenaAuth()->postJson($this->zena('submittals.review', ['id' => $submittal->id]), $reviewData);

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

        $response = $this->withZenaAuth()->postJson($this->zena('submittals.approve', ['id' => $submittal->id]), $approvalData);

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

        $response = $this->withZenaAuth()->postJson($this->zena('submittals.reject', ['id' => $submittal->id]), $rejectionData);

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

        $response = $this->withZenaAuth()->putJson($this->zena('submittals.update', ['id' => $submittal->id]), $updateData);

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

        $response = $this->withZenaAuth()->deleteJson($this->zena('submittals.destroy', ['id' => $submittal->id]));

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
        $response = $this->withZenaAuth()->postJson($this->zena('submittals.store'), []);

        $response->assertStatus(422);

        $errors = $response->json('error.details.data', []);
        foreach (['project_id', 'title', 'description', 'submittal_type'] as $field) {
            $this->assertArrayHasKey($field, $errors);
        }
    }

    /**
     * Test unauthorized access
     */
    public function test_unauthorized_access_returns_401()
    {
        $response = $this->getJson($this->zena('submittals.index'));
        $response->assertStatus(400);
        $response->assertJsonPath('message', 'X-Tenant-ID header is required');
        $response->assertJsonPath('error.code', 'TENANT_REQUIRED');
    }

    private function withZenaAuth()
    {
        return $this->withHeaders($this->zenaAuthHeaders);
    }

    private function buildZenaAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID' => (string) $this->user->tenant_id,
            'Accept' => 'application/json',
        ];
    }

    private function loginZenaUser(User $user): string
    {
        $response = $this->postJson($this->zena('auth.login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200);

        return (string) $response->json('data.token');
    }

    private function assignSuperAdminRole(User $user): void
    {
        $role = Role::firstOrCreate([
            'name' => 'super_admin',
        ], [
            'scope' => Role::SCOPE_SYSTEM,
            'allow_override' => true,
            'is_active' => true,
        ]);

        $user->roles()->syncWithoutDetaching($role->id);
    }

    private function syncZenaProjectRecord(ZenaProject $project): void
    {
        DB::table('zena_projects')->updateOrInsert(
            ['id' => $project->id],
            [
                'tenant_id' => $project->tenant_id,
                'code' => $project->code,
                'name' => $project->name,
                'description' => $project->description,
                'client_id' => $project->client_id,
                'status' => $this->mapProjectStatusToZenaStatus($project->status),
                'start_date' => $project->start_date,
                'end_date' => $project->end_date,
                'budget' => $project->budget_total ?? 0,
                'settings' => json_encode($project->settings ?? []),
                'created_at' => $project->created_at,
                'updated_at' => $project->updated_at,
            ]
        );
    }

    private function mapProjectStatusToZenaStatus(string $status): string
    {
        return match ($status) {
            'planning' => 'planning',
            'active', 'in_progress' => 'active',
            'on_hold' => 'on_hold',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            default => 'planning',
        };
    }
}
