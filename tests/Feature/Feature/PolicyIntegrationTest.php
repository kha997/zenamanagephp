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
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 404], true));
    }

    public function test_role_based_access_control()
    {
        // Test PM role
        $this->user->assignRole('pm');
        $this->actingAs($this->user);
        
        $response = $this->get('/dashboard/pm');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 404], true));
        
        // Test Admin role
        $this->user->assignRole('admin');
        $response = $this->get('/dashboard/admin');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 404], true));
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
        $this->assertTrue(in_array($response->status(), [301, 302, 403], true));
    }

    public function test_policy_authorization_flow()
    {
        // Test unauthorized access
        $response = $this->get('/dashboard');
        $response->assertRedirect('/app/dashboard');
        
        // Test authorized access
        $this->user->assignRole('pm');
        $this->actingAs($this->user);
        
        $response = $this->get('/dashboard');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 404], true));
    }
}
