<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * GAP-115 U-1 — production:user.
 *
 * - Creates an account with a canonical role in the tenant and a generated one-time password.
 * - Refuses duplicate emails, unknown roles, and roles not loaded by production:rbac.
 * - Requires --tenant when several companies exist.
 * - Resets a password; every action is audited.
 * - The accountant reaches the payroll page.
 */
class ProductionUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_accountant_who_can_open_payroll(): void
    {
        $this->bootstrapCompany();
        $password = $this->createUser(['--email' => 'ketoan@zena-build.example', '--name' => 'Kế toán', '--role' => 'Finance']);

        /** @var User $user */
        $user = User::query()->where('email', 'ketoan@zena-build.example')->firstOrFail();
        $this->assertSame((string) Tenant::query()->sole()->id, (string) $user->tenant_id);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertSame(20, strlen($password));
        $this->assertTrue($user->hasPermission('payroll.manage'));
        $this->assertFalse($user->hasPermission('payroll.close'));
        $this->actingAs($user)->get(route('operator.payroll.index'))->assertOk();
        $this->assertSame(1, AuditLog::query()->where('action', 'production.user.created')->count());
    }

    public function test_refusals(): void
    {
        $this->artisan('production:user', ['--email' => 'a@b.example', '--name' => 'A', '--role' => 'Finance'])->assertExitCode(1);

        $this->bootstrapCompany();
        $this->artisan('production:user', ['--email' => 'owner@zena-build.example', '--name' => 'Dup', '--role' => 'Finance'])->assertExitCode(1);
        $this->artisan('production:user', ['--email' => 'x@zena-build.example', '--name' => 'X', '--role' => 'hacker'])->assertExitCode(1);
        $this->artisan('production:user', ['--email' => 'not-an-email', '--name' => 'X', '--role' => 'Finance'])->assertExitCode(1);
        $this->artisan('production:user', ['--email' => 'x@zena-build.example', '--role' => 'Finance'])->assertExitCode(1);
        $this->assertSame(1, User::query()->count());
    }

    public function test_roles_must_be_loaded_first(): void
    {
        $this->artisan('production:bootstrap', [
            '--tenant-name' => 'Zena Build', '--tenant-slug' => 'zena-build', '--admin-email' => 'owner@zena-build.example',
        ])->assertExitCode(0);
        $this->artisan('production:user', ['--email' => 'pm@zena-build.example', '--name' => 'PM', '--role' => 'Project Manager'])
            ->expectsOutputToContain('production:rbac')
            ->assertExitCode(1);
    }

    public function test_several_tenants_need_tenant_option_and_password_reset(): void
    {
        $this->bootstrapCompany();
        Tenant::factory()->create(['slug' => 'other-co']);
        $this->artisan('production:user', ['--email' => 'pm@zena-build.example', '--name' => 'PM', '--role' => 'Project Manager'])->assertExitCode(1);
        $first = $this->createUser(['--email' => 'pm@zena-build.example', '--name' => 'PM', '--role' => 'Project Manager', '--tenant' => 'zena-build']);

        $second = $this->createUser(['--email' => 'pm@zena-build.example', '--reset-password' => true, '--tenant' => 'zena-build']);
        /** @var User $pm */
        $pm = User::query()->where('email', 'pm@zena-build.example')->firstOrFail();
        $this->assertNotSame($first, $second);
        $this->assertFalse(Hash::check($first, $pm->password));
        $this->assertTrue(Hash::check($second, $pm->password));
        $this->assertSame(1, AuditLog::query()->where('action', 'production.user.password_reset')->count());
        $this->artisan('production:user', ['--email' => 'nobody@zena-build.example', '--reset-password' => true, '--tenant' => 'zena-build'])->assertExitCode(1);
    }

    private function bootstrapCompany(): void
    {
        $this->artisan('production:rbac')->assertExitCode(0);
        $this->artisan('production:bootstrap', [
            '--tenant-name' => 'Zena Build', '--tenant-slug' => 'zena-build', '--admin-email' => 'owner@zena-build.example',
        ])->assertExitCode(0);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createUser(array $options): string
    {
        $this->assertSame(0, Artisan::call('production:user', $options));
        $this->assertSame(1, preg_match('/password[^:]*:\s*(\S{20})\s*$/mi', Artisan::output(), $m), Artisan::output());

        return $m[1];
    }
}
