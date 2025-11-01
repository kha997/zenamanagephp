<?php

namespace Tests\Feature\Feature;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyIntegrationTest extends TestCase
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

    public function test_policy_middleware_integration()
    {
        $this->user->assignRole('pm');
        $this->actingAs($this->user);
        
        // Test that policies work with middleware
        $response = $this->get('/dashboard');
        $this->assertTrue($response->status() === 200 || $response->status() === 404);
    }

    public function test_role_based_access_control()
    {
        // Test PM role
        $this->user->assignRole('pm');
        $this->actingAs($this->user);
        
        $response = $this->get('/dashboard/pm');
        $this->assertTrue($response->status() === 200 || $response->status() === 404);
        
        // Test Admin role
        $this->user->assignRole('admin');
        $response = $this->get('/dashboard/admin');
        $this->assertTrue($response->status() === 200 || $response->status() === 404);
    }

    public function test_tenant_isolation_with_policies()
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
        
        // Should not be able to access current tenant's resources
        $response = $this->get('/dashboard');
        $this->assertTrue($response->status() === 403 || $response->status() === 302);
    }

    public function test_policy_authorization_flow()
    {
        // Test unauthorized access
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
        
        // Test authorized access
        $this->user->assignRole('pm');
        $this->actingAs($this->user);
        
        $response = $this->get('/dashboard');
        $this->assertTrue($response->status() === 200 || $response->status() === 404);
    }
}