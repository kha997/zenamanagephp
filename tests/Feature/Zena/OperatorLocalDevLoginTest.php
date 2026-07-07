<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class OperatorLocalDevLoginTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    public function test_local_operator_dev_login_signs_in_tenant_user_and_redirects_to_dashboard(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser(
            $tenant,
            ['email' => 'operator-local@example.com'],
            ['admin'],
            ['material.read']
        );

        $response = $this->get('/local/dev-login/operator?email=' . urlencode($user->email));

        $response->assertRedirect(route('operator.dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->get(route('operator.dashboard'))
            ->assertOk()
            ->assertSee('Bảng điều hành');
    }

    public function test_local_operator_dev_login_fails_clearly_when_no_tenant_backed_user_exists(): void
    {
        User::factory()->create([
            'tenant_id' => null,
            'email' => 'no-tenant@example.com',
        ]);

        $this->get('/local/dev-login/operator?email=no-tenant@example.com')
            ->assertNotFound()
            ->assertSee('No tenant-backed user found for [no-tenant@example.com].');
    }
}
