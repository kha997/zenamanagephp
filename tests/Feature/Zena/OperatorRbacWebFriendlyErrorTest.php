<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 2026-07-22: manual browser check found materials/create and vendors/create
 * dump raw JSON on a plain page navigation when the user lacks permission —
 * RoleBasedAccessControlMiddleware returns JSON unconditionally on denial,
 * unlike routes gated via $this->authorize() (which get Laravel's normal
 * styled 403 page). This test locks in the content-negotiated fix: JSON
 * stays identical for API/AJAX callers, browser navigation gets a friendly
 * redirect + flash instead of a raw JSON dump.
 */
class OperatorRbacWebFriendlyErrorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
    }

    private function userWithoutPermission(Tenant $tenant): User
    {
        $user = User::factory()->create(['tenant_id' => (string) $tenant->id, 'is_active' => true]);
        // Role with zero permissions — same tenant, so auth/tenant.isolation pass,
        // but the specific rbac:vendor.create check must fail.
        $role = Role::factory()->create(['name' => 'No Permissions ' . uniqid()]);
        UserRole::query()->create(['user_id' => (string) $user->id, 'role_id' => (string) $role->id]);

        return $user;
    }

    public function test_web_navigation_to_permission_gated_page_gets_friendly_redirect_not_raw_json(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->userWithoutPermission($tenant);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        // Plain ->get() — no Accept: application/json header — simulates a real
        // browser page navigation exactly like typing the URL or clicking a link.
        $response = $this->actingAs($user)->get(route('operator.vendors.create'), $headers);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Bạn không có quyền thực hiện thao tác này.');
        $this->assertStringNotContainsString('"code":"E403.AUTHORIZATION"', $response->getContent() ?: '');
    }

    public function test_api_call_to_same_permission_gate_still_gets_unchanged_json_envelope(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->userWithoutPermission($tenant);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $response = $this->actingAs($user)->getJson(route('operator.vendors.create'), $headers);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'E403.AUTHORIZATION');
        $response->assertJsonPath('error.message', 'You do not have permission to access this resource');
        $response->assertJsonPath('success', false);
    }

    public function test_web_navigation_denied_by_bare_rbac_role_check_gets_friendly_redirect(): void
    {
        // handleGeneralAccess() branch: bare `rbac` middleware (no :permission param),
        // denies users with none of the allowed role names at all.
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => (string) $tenant->id, 'is_active' => true]);
        // Deliberately no role assignment at all.
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $response = $this->actingAs($user)->get(route('api.accessibility.preferences'), $headers);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Bạn không có quyền thực hiện thao tác này.');
    }

    public function test_api_call_denied_by_bare_rbac_role_check_still_gets_unchanged_json_envelope(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => (string) $tenant->id, 'is_active' => true]);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $response = $this->actingAs($user)->getJson(route('api.accessibility.preferences'), $headers);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'RBAC_ACCESS_DENIED');
        $response->assertJsonPath('success', false);
    }
}
