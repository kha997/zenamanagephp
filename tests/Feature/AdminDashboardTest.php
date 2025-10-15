<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_access_admin_dashboard_with_feature_flag()
    {
        // Create admin user
        $adminUser = User::factory()->create([
            'role' => 'super_admin'
        ]);
        
        // Authenticate as admin user
        $this->actingAs($adminUser, 'web');
        
        $response = $this->get('/admin/dashboard');
        
        $response->assertStatus(200);
        $response->assertViewIs('admin.dashboard');
    }

    /** @test */
    public function it_requires_authentication_when_bypass_disabled()
    {
        // Disable auth bypass
        config(['app.auth_bypass_enabled' => false]);
        
        $response = $this->get('/admin/dashboard');
        
        $response->assertStatus(302); // Redirect to login
    }

    /** @test */
    public function it_allows_super_admin_access()
    {
        config(['app.auth_bypass_enabled' => false]);
        
        $user = User::factory()->create([
            'role' => 'super_admin'
        ]);
        
        $this->actingAs($user);
        
        $response = $this->get('/admin/dashboard');
        
        $response->assertStatus(200);
    }

    /** @test */
    public function it_denies_regular_user_access()
    {
        config(['app.auth_bypass_enabled' => false]);
        
        $user = User::factory()->create([
            'role' => 'user'
        ]);
        
        $this->actingAs($user);
        
        $response = $this->get('/admin/dashboard');
        
        $response->assertStatus(403);
    }
}
