<?php declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureSystemAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant
        $this->tenant = Tenant::factory()->create();
        
        // Create org_admin role
        $this->orgAdminRole = Role::create([
            'name' => 'org_admin',
            'scope' => 'system',
            'description' => 'Organization Admin',
            'is_active' => true,
        ]);
        
        // Create super_admin role
        $this->superAdminRole = Role::create([
            'name' => 'super_admin',
            'scope' => 'system',
            'description' => 'Super Administrator',
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_access_system_routes(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach($this->superAdminRole);
        
        $this->actingAs($user);
        
        $response = $this->get('/admin/users');
        
        // Should not be blocked (may return 200 or 404 if route doesn't exist, but not 403)
        $this->assertNotEquals(403, $response->status());
    }

    public function test_org_admin_is_blocked_from_system_routes(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $user->roles()->attach($this->orgAdminRole);
        
        $this->actingAs($user);
        
        $response = $this->get('/admin/users');
        
        // Should be blocked - system routes are Super Admin only
        $this->assertEquals(403, $response->status());
    }

    public function test_regular_user_is_blocked(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        
        $this->actingAs($user);
        
        $response = $this->get('/admin/users');
        
        // Should be blocked
        $this->assertEquals(403, $response->status());
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $response = $this->get('/admin/users');
        
        // Should redirect to login
        $response->assertRedirect('/login');
    }

    public function test_inactive_user_is_blocked(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
        ]);
        $user->roles()->attach($this->superAdminRole);
        
        $this->actingAs($user);
        
        $response = $this->get('/admin/users');
        
        // Should be blocked
        $this->assertEquals(403, $response->status());
    }
}
