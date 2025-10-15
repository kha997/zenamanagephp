<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;

class SimpleApiTest extends TestCase
{
    public function test_simple_api_route()
    {
        $response = $this->getJson('/api/health');
        $response->assertStatus(200);
    }
    
    public function test_projects_route_without_auth()
    {
        $response = $this->getJson('/api/v1/app/projects');
        // Should return 401 or 403, not 500
        $this->assertTrue(in_array($response->getStatusCode(), [401, 403]));
    }
    
    public function test_projects_route_with_auth()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        $response = $this->actingAs($user, 'sanctum')
                         ->getJson('/api/v1/app/projects');
        
        // Should not return 500
        $this->assertNotEquals(500, $response->getStatusCode());
        
        // Log the actual response for debugging
        if ($response->getStatusCode() !== 200) {
            dump('Response status:', $response->getStatusCode());
            dump('Response content:', $response->getContent());
        }
    }
}
