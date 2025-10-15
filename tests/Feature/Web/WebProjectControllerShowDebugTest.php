<?php

namespace Tests\Feature\Web;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class WebProjectControllerShowDebugTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set API base URL for testing
        config(['app.api_base_url' => 'http://localhost']);
        
        // Create tenant
        $this->tenant = Tenant::factory()->create();
        
        // Create user with tenant
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);
    }

    /**
     * Test web project show debug
     */
    public function test_web_project_show_debug(): void
    {
        // Create a real project in the database
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Project',
            'code' => 'TEST-001',
            'status' => 'active',
            'progress_pct' => 50,
            'budget_actual' => 10000
        ]);
        
        // Mock HTTP client to simulate API call
        Http::fake([
            "http://localhost/api/v1/app/projects/{$project->id}" => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $project->id,
                    'name' => 'Test Project',
                    'code' => 'TEST-001',
                    'status' => 'active',
                    'progress_pct' => 50,
                    'budget_actual' => 10000,
                    'tenant_id' => $this->tenant->id
                ]
            ], 200)
        ]);

        $this->actingAs($this->user);
        
        $response = $this->get("/app/projects/{$project->id}");
        
        dump('Response status: ' . $response->status());
        dump('Response content: ' . $response->content());
        
        // Just check that we get some response (not 404)
        $this->assertNotEquals(404, $response->status());
    }
}
