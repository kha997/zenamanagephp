<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\StaffAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP-129 R-1 (roles, read-only), C-1 (company name), deactivated users signed out, legacy mock pages redirected.
 */
final class AdminRolesCompanyAndSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('production:rbac')->assertExitCode(0);
        $this->artisan('production:bootstrap', [
            '--tenant-name' => 'Zena Build', '--tenant-slug' => 'zena-build', '--admin-email' => 'owner@zena-build.example',
        ])->assertExitCode(0);
        $this->owner = User::query()->where('email', 'owner@zena-build.example')->firstOrFail();
        $this->tenant = Tenant::query()->where('slug', 'zena-build')->firstOrFail();
    }

    public function test_roles_page_lists_the_five_roles_with_their_real_permission_counts(): void
    {
        $response = $this->actingAs($this->owner)->get(route('app.admin.roles'))->assertOk();
        foreach (['super_admin', 'Finance', 'Project Manager', 'site_engineer', 'Project Member'] as $name) {
            /** @var Role $role */
            $role = Role::query()->where('name', $name)->firstOrFail();
            $response->assertSee($name)->assertSee('data-permission-count="' . $role->permissions()->count() . '"', false);
        }
        // R-1 is read-only: nothing on the page edits a role's permissions.
        $response->assertDontSee('name="permissions', false)->assertDontSee('/admin/roles/', false);
    }

    public function test_company_page_renames_own_company_and_keeps_the_slug(): void
    {
        $this->actingAs($this->owner)->get(route('app.admin.company'))->assertOk()->assertSee('Zena Build')->assertSee('zena-build');
        $this->post(route('app.admin.company.update'), ['name' => 'Zena Build JSC', 'slug' => 'hacked'])->assertRedirect(route('app.admin.company'));
        $this->assertSame('Zena Build JSC', $this->tenant->fresh()->name);
        $this->assertSame('zena-build', $this->tenant->fresh()->slug);
        $this->post(route('app.admin.company.update'), ['name' => ''])->assertSessionHasErrors('name');
    }

    public function test_staff_cannot_open_roles_or_company(): void
    {
        [$pm] = app(StaffAccountService::class)->create($this->tenant, 'pm@zena-build.example', 'PM', 'Project Manager', null, 'test');
        $this->actingAs($pm)->get(route('app.today'))->assertOk();
        $this->get(route('app.admin.roles'))->assertRedirect()->assertSessionHas('error');
        $this->actingAs($pm)->get(route('app.admin.company'))->assertRedirect()->assertSessionHas('error');
        $this->actingAs($pm)->post(route('app.admin.company.update'), ['name' => 'X'])->assertRedirect()->assertSessionHas('error');
        $this->assertSame('Zena Build', $this->tenant->fresh()->name);
    }

    public function test_a_deactivated_user_is_signed_out_on_the_next_request(): void
    {
        [$pm] = app(StaffAccountService::class)->create($this->tenant, 'pm@zena-build.example', 'PM', 'Project Manager', null, 'test');
        $this->actingAs($pm)->get(route('app.today'))->assertOk();

        $pm->forceFill(['is_active' => false])->save();

        $this->get(route('app.today'))->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_legacy_mock_admin_pages_redirect_to_the_real_ones(): void
    {
        $this->actingAs($this->owner);
        $this->get('/admin/users')->assertRedirect(route('app.admin.users'));
        $this->get('/admin/tenants')->assertRedirect(route('app.admin.company'));
    }
}
