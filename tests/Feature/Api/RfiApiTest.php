<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Rfi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class RfiApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $project;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create();
        
        // Create test project
        $this->project = Project::factory()->create([
            'created_by' => $this->user->id
        ]);
        
        // Generate JWT token
        $this->token = $this->generateJwtToken($this->user);
    }

    /**
     * Test RFI index endpoint
     */
    public function test_can_get_rfi_list()
    {
        // Create test RFIs
        Rfi::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/rfis');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'title',
                                'description',
                                'status',
                                'priority',
                                'created_at'
                            ]
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

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/rfis', $rfiData);

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
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/rfis', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['project_id', 'title', 'description']);
    }

    /**
     * Test RFI show endpoint
     */
    public function test_can_get_single_rfi()
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/zena/rfis/{$rfi->id}");

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
            'created_by' => $this->user->id
        ]);

        $updateData = [
            'title' => 'Updated RFI Title',
            'description' => 'Updated description',
            'priority' => 'high'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson("/api/zena/rfis/{$rfi->id}", $updateData);

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
            'created_by' => $this->user->id
        ]);

        $assignData = [
            'assigned_to' => $this->user->id,
            'assignment_notes' => 'Please review this RFI'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/rfis/{$rfi->id}/assign", $assignData);

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
            'status' => 'in_progress'
        ]);

        $responseData = [
            'response' => 'This is the response to the RFI',
            'response_notes' => 'Additional notes'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/rfis/{$rfi->id}/respond", $responseData);

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
            'status' => 'in_progress'
        ]);

        $escalateData = [
            'escalated_to' => $this->user->id,
            'escalation_reason' => 'Urgent issue requiring immediate attention'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/rfis/{$rfi->id}/escalate", $escalateData);

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
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status' => 'answered'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/rfis/{$rfi->id}/close");

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
            'created_by' => $this->user->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson("/api/zena/rfis/{$rfi->id}");

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
        $response = $this->getJson('/api/zena/rfis');
        $response->assertStatus(401);
    }

    /**
     * Generate JWT token for testing
     */
    private function generateJwtToken(User $user): string
    {
        // This would normally use your JWT service
        // For testing, we'll create a simple token
        return 'test-jwt-token-' . $user->id;
    }
}
