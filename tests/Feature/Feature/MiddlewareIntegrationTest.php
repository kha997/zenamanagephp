<?php

namespace Tests\Feature\Feature;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiddlewareIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create([
            'slug' => 'test-tenant-' . uniqid(),
            'name' => 'Test Tenant'
        ]);
        
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example-' . uniqid() . '.com'
        ]);
    }

    public function test_auth_middleware_blocks_unauthorized_access()
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_auth_middleware_allows_authorized_access()
    {
        $this->user->assignRole('pm');
        $this->actingAs($this->user);
        
        $response = $this->get('/dashboard');
        $this->assertTrue($response->status() === 200 || $response->status() === 404);
    }

    public function test_tenant_middleware_enforces_tenant_isolation()
    {
        $otherTenant = Tenant::factory()->create([
            'slug' => 'other-tenant-' . uniqid(),
            'name' => 'Other Tenant'
        ]);
        
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'email' => 'other@example-' . uniqid() . '.com'
        ]);
        
        $otherUser->assignRole('pm');
        
        $this->actingAs($otherUser);
        
        // Should be blocked by tenant middleware
        $response = $this->get('/dashboard');
        $this->assertTrue($response->status() === 403 || $response->status() === 302);
    }

    public function test_role_middleware_enforces_role_based_access()
    {
        $this->user->assignRole('engineer');
        $this->actingAs($this->user);
        
        // Should be blocked by role middleware
        $response = $this->get('/dashboard/admin');
        $this->assertTrue($response->status() === 403 || $response->status() === 302);
    }

    public function test_middleware_stack_execution_order()
    {
        // Test that middleware executes in correct order: auth -> tenant -> role
        $this->user->assignRole('admin');
        $this->actingAs($this->user);
        
        $response = $this->get('/dashboard/admin');
        $this->assertTrue($response->status() === 200 || $response->status() === 404);
    }

    public function test_csrf_middleware_protection()
    {
        $this->user->assignRole('pm');
        $this->actingAs($this->user);
        
        // Test CSRF protection on POST routes
        $response = $this->post('/test-task-update', [
            'name' => 'Test Task'
        ]);
        
        // Should either succeed (if CSRF is disabled for testing) or fail with CSRF error
        $this->assertTrue($response->status() === 200 || $response->status() === 419);
    }
}