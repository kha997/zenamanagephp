<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Hash;

/**
 * API Response Format Test
 * 
 * Verifies all API endpoints return standardized JSON format:
 * - Success responses: {success: true, data: ..., message: ..., timestamp: ...}
 * - Error responses: {ok: false, code: ..., message: ..., traceId: ..., details: ...}
 * - Paginated responses: {success: true, data: [], meta: {...}, links: {...}}
 * 
 * @group api-format
 */
class ApiResponseFormatTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected User $user;
    protected Tenant $tenant;
    protected Project $project;
    protected Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setDomainSeed(99999);
        $this->setDomainName('api-format');
        $this->setupDomainIsolation();

        $this->tenant = TestDataSeeder::createTenant();
        $this->storeTestData('tenant', $this->tenant);

        $this->user = TestDataSeeder::createUser($this->tenant, [
            'name' => 'Test User',
            'email' => 'user@api-format-test.test',
            'role' => 'admin',
            'password' => Hash::make('password123'),
        ]);

        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
        ]);

        Sanctum::actingAs($this->user);
    }

    /**
     * Test success response format
     */
    public function test_success_response_format(): void
    {
        $response = $this->getJson("/api/v1/app/projects/{$this->project->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
            'message',
            'timestamp',
        ]);
        $response->assertJson([
            'success' => true,
        ]);
    }

    /**
     * Test error response format (404)
     */
    public function test_error_response_format_404(): void
    {
        $response = $this->getJson('/api/v1/app/projects/non-existent-id');

        $response->assertStatus(404);
        $response->assertJsonStructure([
            'ok',
            'code',
            'message',
            'traceId',
            'details',
        ]);
        $response->assertJson([
            'ok' => false,
        ]);
    }

    /**
     * Test error response format (401)
     */
    public function test_error_response_format_401(): void
    {
        // Logout
        $this->postJson('/api/v1/auth/logout');

        $response = $this->getJson('/api/v1/app/projects');

        $response->assertStatus(401);
        $response->assertJsonStructure([
            'ok',
            'code',
            'message',
            'traceId',
        ]);
        $response->assertJson([
            'ok' => false,
            'code' => 'UNAUTHORIZED',
        ]);
    }

    /**
     * Test paginated response format
     */
    public function test_paginated_response_format(): void
    {
        // Create more projects for pagination
        Project::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->getJson('/api/v1/app/projects?per_page=2');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [],
            'meta' => [
                'current_page',
                'per_page',
                'total',
                'last_page',
                'from',
                'to',
            ],
            'links' => [
                'first',
                'last',
                'prev',
                'next',
            ],
            'message',
            'timestamp',
        ]);
        $response->assertJson([
            'success' => true,
        ]);
    }

    /**
     * Test users endpoint response format
     */
    public function test_users_endpoint_response_format(): void
    {
        $response = $this->getJson('/api/v1/app/users');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [],
            'message',
        ]);
    }

    /**
     * Test tasks endpoint response format
     */
    public function test_tasks_endpoint_response_format(): void
    {
        $response = $this->getJson('/api/v1/app/tasks');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [],
            'meta' => [],
            'message',
        ]);
    }

    /**
     * Test subtasks endpoint response format
     */
    public function test_subtasks_endpoint_response_format(): void
    {
        $response = $this->getJson("/api/v1/app/tasks/{$this->task->id}/subtasks");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'data' => [],
                'meta' => [],
            ],
            'message',
        ]);
    }

    /**
     * Test task comments endpoint response format
     */
    public function test_task_comments_endpoint_response_format(): void
    {
        $response = $this->getJson("/api/v1/app/tasks/{$this->task->id}/comments");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [],
            'message',
        ]);
    }

    /**
     * Test task attachments endpoint response format
     */
    public function test_task_attachments_endpoint_response_format(): void
    {
        $response = $this->getJson("/api/v1/app/tasks/{$this->task->id}/attachments");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [],
            'message',
        ]);
    }

    /**
     * Test project assignments endpoint response format
     */
    public function test_project_assignments_endpoint_response_format(): void
    {
        $response = $this->getJson("/api/v1/app/projects/{$this->project->id}/assignments/users");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [],
            'message',
        ]);
    }

    /**
     * Test task assignments endpoint response format
     */
    public function test_task_assignments_endpoint_response_format(): void
    {
        $response = $this->getJson("/api/v1/app/tasks/{$this->task->id}/assignments/users");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [],
            'message',
        ]);
    }

    /**
     * Test validation error response format (422)
     */
    public function test_validation_error_response_format(): void
    {
        $response = $this->postJson('/api/v1/app/projects', []);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'ok',
            'code',
            'message',
            'traceId',
            'details' => [
                'validation' => [],
            ],
        ]);
        $response->assertJson([
            'ok' => false,
            'code' => 'VALIDATION_FAILED',
        ]);
    }

    /**
     * Test all responses are JSON
     */
    public function test_all_responses_are_json(): void
    {
        $endpoints = [
            '/api/v1/app/projects',
            '/api/v1/app/tasks',
            '/api/v1/app/users',
            '/api/v1/app/observability/metrics',
            '/api/v1/app/media/quota',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);
            
            $this->assertTrue(
                $response->headers->contains('Content-Type', 'application/json'),
                "Endpoint {$endpoint} should return JSON"
            );
            
            // Verify response is valid JSON
            $this->assertNotNull(json_decode($response->getContent()));
        }
    }

    /**
     * Test error responses include traceId
     */
    public function test_error_responses_include_trace_id(): void
    {
        $response = $this->getJson('/api/v1/app/projects/non-existent-id');

        $response->assertStatus(404);
        $data = $response->json();
        
        $this->assertArrayHasKey('traceId', $data);
        $this->assertNotEmpty($data['traceId']);
        $this->assertStringStartsWith('req_', $data['traceId']);
    }

    /**
     * Test success responses include timestamp
     */
    public function test_success_responses_include_timestamp(): void
    {
        $response = $this->getJson("/api/v1/app/projects/{$this->project->id}");

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertNotEmpty($data['timestamp']);
        // Verify it's a valid ISO 8601 timestamp
        $this->assertNotFalse(\DateTime::createFromFormat(\DateTime::ISO8601, $data['timestamp']));
    }
}

