<?php declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_redirects_to_today(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        // GET login page first to establish a session for CSRF token
        $this->get(route('login'));

        $response = $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/app/today');
    }

    public function test_root_url_redirects_to_today(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/app/today');
    }

    public function test_dashboard_route_still_exists_and_is_reachable(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('app.dashboard'));

        $response->assertOk();
    }
}
