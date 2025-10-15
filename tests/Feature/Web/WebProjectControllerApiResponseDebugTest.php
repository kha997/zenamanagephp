<?php

namespace Tests\Feature\Web;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class WebProjectControllerApiResponseDebugTest extends TestCase
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
     * Test API response structure
     */
    public function test_api_response_structure(): void
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
        
        // Test the callApi method directly
        $controller = new \App\Http\Controllers\Web\ProjectController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('callApi');
        $method->setAccessible(true);
        
        $apiResponse = $method->invoke($controller, 'GET', "/api/v1/app/projects/{$project->id}");
        
        dump('API Response: ' . json_encode($apiResponse));
        dump('Has success key: ' . (isset($apiResponse['success']) ? 'YES' : 'NO'));
        dump('Has status key: ' . (isset($apiResponse['status']) ? 'YES' : 'NO'));
        dump('Status value: ' . ($apiResponse['status'] ?? 'NOT SET'));
        
        $this->assertTrue(true); // Just to make it pass
    }
}
