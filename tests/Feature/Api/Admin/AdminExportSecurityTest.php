<?php

namespace Tests\Feature\Api\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class AdminExportSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant
        $this->tenant = Tenant::factory()->create();
        
        // Create admin user
        $this->adminUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
            'is_active' => true,
        ]);
        
        // Create regular user
        $this->regularUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_csv_export_endpoints()
    {
        $endpoints = [
            'GET /api/admin/csv/export/users',
            'GET /api/admin/csv/export/projects',
            'POST /api/admin/csv/import/users',
            'POST /api/admin/csv/import/projects',
            'GET /api/admin/csv/template',
            'POST /api/admin/csv/validate',
        ];

        foreach ($endpoints as $endpoint) {
            [$method, $path] = explode(' ', $endpoint);
            
            $response = $this->json($method, $path);
            
            $response->assertStatus(401)
                ->assertJson([
                    'message' => 'Unauthenticated.'
                ]);
        }
    }

    /** @test */
    public function unauthenticated_user_cannot_access_queue_endpoints()
    {
        $endpoints = [
            'GET /api/admin/queue/stats',
            'GET /api/admin/queue/metrics',
            'GET /api/admin/queue/workers',
            'POST /api/admin/queue/retry',
            'POST /api/admin/queue/clear',
        ];

        foreach ($endpoints as $endpoint) {
            [$method, $path] = explode(' ', $endpoint);
            
            $response = $this->json($method, $path);
            
            $response->assertStatus(401)
                ->assertJson([
                    'message' => 'Unauthenticated.'
                ]);
        }
    }

    /** @test */
    public function unauthenticated_user_cannot_access_performance_endpoints()
    {
        $endpoints = [
            'GET /api/admin/performance/dashboard',
            'GET /api/admin/performance/stats',
            'GET /api/admin/performance/memory',
            'GET /api/admin/performance/network',
            'GET /api/admin/performance/recommendations',
            'GET /api/admin/performance/thresholds',
            'POST /api/admin/performance/thresholds',
            'POST /api/admin/performance/page-load',
            'POST /api/admin/performance/api-response',
            'POST /api/admin/performance/memory',
            'POST /api/admin/performance/network-monitor',
            'GET /api/admin/performance/realtime',
            'POST /api/admin/performance/clear',
            'GET /api/admin/performance/export',
            'POST /api/admin/performance/gc',
            'POST /api/admin/performance/test-connectivity',
            'GET /api/admin/performance/network-health',
        ];

        foreach ($endpoints as $endpoint) {
            [$method, $path] = explode(' ', $endpoint);
            
            $response = $this->json($method, $path);
            
            $response->assertStatus(401)
                ->assertJson([
                    'message' => 'Unauthenticated.'
                ]);
        }
    }

    /** @test */
    public function regular_user_cannot_access_admin_csv_endpoints()
    {
        Sanctum::actingAs($this->regularUser);

        $endpoints = [
            'GET /api/admin/csv/export/users',
            'GET /api/admin/csv/export/projects',
            'POST /api/admin/csv/import/users',
            'POST /api/admin/csv/import/projects',
            'GET /api/admin/csv/template',
            'POST /api/admin/csv/validate',
        ];

        foreach ($endpoints as $endpoint) {
            [$method, $path] = explode(' ', $endpoint);
            
            $response = $this->json($method, $path);
            
            $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'error' => 'Admin access required',
                    'code' => 'ADMIN_REQUIRED'
                ]);
        }
    }

    /** @test */
    public function regular_user_cannot_access_admin_queue_endpoints()
    {
        Sanctum::actingAs($this->regularUser);

        $endpoints = [
            'GET /api/admin/queue/stats',
            'GET /api/admin/queue/metrics',
            'GET /api/admin/queue/workers',
            'POST /api/admin/queue/retry',
            'POST /api/admin/queue/clear',
        ];

        foreach ($endpoints as $endpoint) {
            [$method, $path] = explode(' ', $endpoint);
            
            $response = $this->json($method, $path);
            
            $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'error' => 'Admin access required',
                    'code' => 'ADMIN_REQUIRED'
                ]);
        }
    }

    /** @test */
    public function regular_user_cannot_access_admin_performance_endpoints()
    {
        Sanctum::actingAs($this->regularUser);

        $endpoints = [
            'GET /api/admin/performance/dashboard',
            'GET /api/admin/performance/stats',
            'GET /api/admin/performance/memory',
            'GET /api/admin/performance/network',
            'GET /api/admin/performance/recommendations',
            'GET /api/admin/performance/thresholds',
            'POST /api/admin/performance/thresholds',
            'POST /api/admin/performance/page-load',
            'POST /api/admin/performance/api-response',
            'POST /api/admin/performance/memory',
            'POST /api/admin/performance/network-monitor',
            'GET /api/admin/performance/realtime',
            'POST /api/admin/performance/clear',
            'GET /api/admin/performance/export',
            'POST /api/admin/performance/gc',
            'POST /api/admin/performance/test-connectivity',
            'GET /api/admin/performance/network-health',
        ];

        foreach ($endpoints as $endpoint) {
            [$method, $path] = explode(' ', $endpoint);
            
            $response = $this->json($method, $path);
            
            $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'error' => 'Admin access required',
                    'code' => 'ADMIN_REQUIRED'
                ]);
        }
    }

    /** @test */
    public function admin_user_can_access_admin_endpoints()
    {
        Sanctum::actingAs($this->adminUser);

        // Test CSV export endpoint (should not return 403)
        $response = $this->json('GET', '/api/admin/csv/export/users');
        $this->assertNotEquals(403, $response->getStatusCode());

        // Test queue stats endpoint (should not return 403)
        $response = $this->json('GET', '/api/admin/queue/stats');
        $this->assertNotEquals(403, $response->getStatusCode());

        // Test performance dashboard endpoint (should not return 403)
        $response = $this->json('GET', '/api/admin/performance/dashboard');
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    /** @test */
    public function inactive_admin_user_cannot_access_admin_endpoints()
    {
        $inactiveAdmin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
            'is_active' => false,
        ]);

        Sanctum::actingAs($inactiveAdmin);

        $response = $this->json('GET', '/api/admin/csv/export/users');
        
        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Account is inactive',
                'error' => [
                    'id' => 'ACCOUNT_INACTIVE',
                    'code' => 'ACCOUNT_INACTIVE',
                    'details' => 'Account is inactive'
                ]
            ]);
    }
}
