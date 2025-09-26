<?php

namespace Tests\Feature\Feature;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityIntegrationTest extends TestCase
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

    public function test_unauthorized_user_cannot_access_dashboard()
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_authorized_user_can_access_dashboard()
    {
        $this->user->assignRole('pm');
        $this->actingAs($this->user);
        
        $response = $this->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_user_cannot_access_admin_dashboard_without_admin_role()
    {
        $this->user->assignRole('pm');
        $this->actingAs($this->user);
        
        $response = $this->get('/dashboard/admin');
        $response->assertStatus(403);
    }

    public function test_admin_can_access_admin_dashboard()
    {
        $this->user->assignRole('admin');
        $this->actingAs($this->user);
        
        $response = $this->get('/dashboard/admin');
        $response->assertStatus(200);
    }

    public function test_super_admin_can_access_all_dashboards()
    {
        $this->user->assignRole('super_admin');
        $this->actingAs($this->user);
        
        $dashboards = [
            '/dashboard',
            '/dashboard/admin',
            '/dashboard/pm',
            '/dashboard/designer',
            '/dashboard/site'
        ];
        
        foreach ($dashboards as $dashboard) {
            $response = $this->get($dashboard);
            $this->assertTrue($response->status() === 200 || $response->status() === 404, 
                "Super admin should be able to access {$dashboard}");
        }
    }
}
