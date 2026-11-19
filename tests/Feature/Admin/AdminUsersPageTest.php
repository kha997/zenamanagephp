<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\StaffAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * GAP-129 U-1 — the company's staff accounts page (/app/admin/users).
 */
final class AdminUsersPageTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('production:rbac')->assertExitCode(0);
        $this->artisan('production:bootstrap', [
            '--tenant-name' => 'Zena Build', '--tenant-slug' => 'zena-build', '--admin-email' => 'owner@zena-build.example',
        ])->assertExitCode(0);
        $this->owner = User::query()->where('email', 'owner@zena-build.example')->firstOrFail();
    }

    public function test_owner_sees_the_company_accounts_and_only_those(): void
    {
        $pm = $this->staff('pm@zena-build.example', 'Project Manager');
        $other = Tenant::factory()->create(['slug' => 'other-co']);
        User::factory()->create(['tenant_id' => $other->id, 'email' => 'stranger@other.example']);

        $this->actingAs($this->owner)->get(route('app.admin.users'))
            ->assertOk()
            ->assertSee('owner@zena-build.example')
            ->assertSee($pm->email)
            ->assertSee('Project Manager')
            ->assertDontSee('stranger@other.example');
    }

    /**
     * Project Manager holds user.read / user.manage_roles, so the pages are gated by system.admin (Owner only).
     */
    public function test_every_non_owner_role_is_refused(): void
    {
        foreach (['Project Manager', 'Finance', 'site_engineer', 'Project Member'] as $i => $role) {
            $staff = $this->staff("staff{$i}@zena-build.example", $role);
            $this->actingAs($staff)->get(route('app.today'))->assertOk();
            $this->get(route('app.admin.users'))->assertRedirect()->assertSessionHas('error');
            $this->post(route('app.admin.users.store'), [
                'email' => "x{$i}@zena-build.example", 'name' => 'X', 'role' => 'super_admin',
            ])->assertRedirect()->assertSessionHas('error');
            $this->post(route('app.admin.users.role', $staff->id), ['role' => 'super_admin'])->assertRedirect()->assertSessionHas('error');
            $this->assertDatabaseMissing('users', ['email' => "x{$i}@zena-build.example"]);
            $this->assertSame([$role], app(StaffAccountService::class)->roleNames($staff), $role);
        }
    }

    public function test_owner_creates_an_account_and_the_password_is_shown_once(): void
    {
        $this->actingAs($this->owner)->get(route('app.admin.users'))->assertOk();
        $response = $this->post(route('app.admin.users.store'), [
            'email' => 'KeToan@Zena-Build.example', 'name' => 'Kế toán', 'role' => 'Finance',
        ]);
        $response->assertRedirect(route('app.admin.users'));
        $password = session('one_time_password.password');
        $this->assertIsString($password);
        $this->assertSame(20, strlen($password));

        /** @var User $user */
        $user = User::query()->where('email', 'ketoan@zena-build.example')->firstOrFail();
        $this->assertSame((string) $this->owner->tenant_id, (string) $user->tenant_id);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertSame(['Finance'], app(StaffAccountService::class)->roleNames($user));

        $this->get(route('app.admin.users'))->assertSee($password);
        $this->get(route('app.admin.users'))->assertDontSee($password);

        $audit = AuditLog::query()->where('action', 'production.user.created')->sole();
        $this->assertSame((string) $this->owner->id, (string) $audit->user_id);
        $this->assertSame('web', $audit->new_data['source']);

        auth()->logout();
        $this->get('/login');
        $this->post('/login', ['email' => 'ketoan@zena-build.example', 'password' => $password])->assertRedirect('/app/today');
    }

    public function test_create_refuses_duplicates_and_unknown_roles(): void
    {
        $this->actingAs($this->owner)->get(route('app.admin.users'));
        $this->post(route('app.admin.users.store'), [
            'email' => 'owner@zena-build.example', 'name' => 'Dup', 'role' => 'Finance',
        ])->assertSessionHasErrors('email');
        $this->post(route('app.admin.users.store'), [
            'email' => 'x@zena-build.example', 'name' => 'X', 'role' => 'hacker',
        ])->assertSessionHasErrors('role');
        $this->assertSame(1, User::query()->count());
    }

    public function test_change_role_deactivate_reactivate_and_reset_password(): void
    {
        $pm = $this->staff('pm@zena-build.example', 'Project Manager');
        $this->actingAs($this->owner)->get(route('app.admin.users'));

        $this->post(route('app.admin.users.role', $pm), ['role' => 'site_engineer'])->assertRedirect();
        $this->assertSame(['site_engineer'], app(StaffAccountService::class)->roleNames($pm));

        $this->post(route('app.admin.users.deactivate', $pm))->assertRedirect();
        $this->assertFalse($pm->fresh()->is_active);

        $this->post(route('app.admin.users.reactivate', $pm))->assertRedirect();
        $this->assertTrue($pm->fresh()->is_active);

        $this->post(route('app.admin.users.reset-password', $pm))->assertRedirect();
        $password = session('one_time_password.password');
        $this->assertTrue(Hash::check($password, $pm->fresh()->password));

        foreach (['admin.user.role_changed', 'admin.user.deactivated', 'admin.user.reactivated', 'production.user.password_reset'] as $action) {
            $this->assertSame(1, AuditLog::query()->where('action', $action)->where('entity_id', (string) $pm->id)->count(), $action);
        }
    }

    public function test_owner_cannot_lock_or_demote_themselves_or_the_last_owner(): void
    {
        $this->actingAs($this->owner)->get(route('app.admin.users'));
        $this->post(route('app.admin.users.deactivate', $this->owner))->assertSessionHasErrors('account');
        $this->post(route('app.admin.users.role', $this->owner), ['role' => 'Finance'])->assertSessionHasErrors('account');
        $this->assertTrue($this->owner->fresh()->is_active);
        $this->assertTrue(app(StaffAccountService::class)->isOwner($this->owner));

        $second = $this->staff('owner2@zena-build.example', 'super_admin');
        $this->actingAs($second)->get(route('app.admin.users'));
        $this->post(route('app.admin.users.deactivate', $this->owner))->assertRedirect();
        $this->assertFalse($this->owner->fresh()->is_active);
        $this->post(route('app.admin.users.role', $second), ['role' => 'Finance'])->assertSessionHasErrors('account');
    }

    public function test_a_user_of_another_company_is_not_found(): void
    {
        $other = Tenant::factory()->create(['slug' => 'other-co']);
        $stranger = User::factory()->create(['tenant_id' => $other->id, 'is_active' => true]);

        $this->actingAs($this->owner)->get(route('app.admin.users'));
        $this->post(route('app.admin.users.deactivate', $stranger))->assertNotFound();
        $this->post(route('app.admin.users.reset-password', $stranger))->assertNotFound();
        $this->post(route('app.admin.users.role', $stranger), ['role' => 'Finance'])->assertNotFound();
        $this->assertTrue($stranger->fresh()->is_active);
    }

    public function test_admin_section_is_in_the_sidebar_for_the_owner_only(): void
    {
        $this->actingAs($this->owner)->get(route('app.admin.users'))->assertSee(route('app.admin.roles'), false);
        $pm = $this->staff('pm@zena-build.example', 'Project Manager');
        $this->actingAs($pm)->get(route('app.today'))->assertDontSee(route('app.admin.users'), false);
    }

    private function staff(string $email, string $role): User
    {
        [$user] = app(StaffAccountService::class)->create(
            Tenant::query()->where('slug', 'zena-build')->firstOrFail(),
            $email,
            ucfirst(strtok($email, '@')),
            $role,
            null,
            'test'
        );

        return $user;
    }
}
