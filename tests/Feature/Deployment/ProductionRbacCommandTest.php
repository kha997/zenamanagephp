<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * GAP-115 R-1 — production:rbac.
 *
 * - Creates the canonical roles and loads permissions and role grants only.
 * - Idempotent; --dry-run writes nothing.
 * - Never creates tenants, users or demo data.
 * - The owner created afterwards by production:bootstrap has the full set of permissions.
 */
class ProductionRbacCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_roles_and_grants_idempotently_without_users(): void
    {
        $this->assertSame(0, $this->artisan('production:rbac')->run());

        foreach (['super_admin', 'Finance', 'Project Manager', 'site_engineer', 'Project Member'] as $name) {
            $this->assertTrue(Role::query()->where('name', $name)->exists(), $name);
        }
        $this->assertTrue($this->roleHas('super_admin', 'payroll.close'));
        $this->assertTrue($this->roleHas('super_admin', 'treasury.view'));
        $this->assertTrue($this->roleHas('super_admin', 'task.view'));
        $this->assertTrue($this->roleHas('Finance', 'payroll.manage'));
        $this->assertFalse($this->roleHas('Finance', 'payroll.close'));
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Tenant::query()->count());

        $roles = Role::query()->count();
        $permissions = Permission::query()->count();
        $grants = DB::table('role_permissions')->count();
        $this->assertSame(0, $this->artisan('production:rbac')->run());
        $this->assertSame($roles, Role::query()->count());
        $this->assertSame($permissions, Permission::query()->count());
        $this->assertSame($grants, DB::table('role_permissions')->count());
    }

    /**
     * Codex P1/P2: PermissionSeeder sync()s Project Manager / Project Member, so grants added before it must survive, and
     * every staff role needs the pages the go-live runbook relies on (time logging / review, project pages).
     */
    public function test_operational_roles_keep_their_grants_and_reach_time_and_project_pages(): void
    {
        $this->artisan('production:rbac')->assertExitCode(0);
        foreach (['template.view', 'template.apply', 'task.view', 'project.view'] as $code) {
            $this->assertTrue($this->roleHas('Project Manager', $code), "Project Manager {$code}");
        }
        foreach (['Finance', 'site_engineer', 'Project Member'] as $role) {
            $this->assertTrue($this->roleHas($role, 'task.view'), "{$role} task.view");
        }
        foreach (['site_engineer', 'Project Member'] as $role) {
            $this->assertTrue($this->roleHas($role, 'project.view'), "{$role} project.view");
        }
        $this->artisan('production:rbac')->assertExitCode(0);
        $this->assertTrue($this->roleHas('Project Manager', 'template.apply'));
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('production:rbac', ['--dry-run' => true])
            ->expectsOutputToContain('super_admin')
            ->expectsOutputToContain('Dry run')
            ->assertExitCode(0);
        $this->assertSame(0, Role::query()->count());
        $this->assertSame(0, Permission::query()->count());
    }

    public function test_owner_from_bootstrap_has_permissions_and_templates_follow_the_tenant(): void
    {
        $this->artisan('production:rbac')->assertExitCode(0);
        $this->artisan('production:bootstrap', [
            '--tenant-name' => 'Zena Build', '--tenant-slug' => 'zena-build', '--admin-email' => 'owner@zena-build.example',
        ])->assertExitCode(0);
        /** @var User $owner */
        $owner = User::query()->where('email', 'owner@zena-build.example')->firstOrFail();
        foreach (['payroll.manage', 'payroll.close', 'treasury.view', 'task.view', 'project.view'] as $code) {
            $this->assertTrue($owner->hasPermission($code), $code);
        }

        if (Schema::hasTable('work_templates')) {
            $this->artisan('production:rbac')->assertExitCode(0);
            $this->assertGreaterThan(0, DB::table('work_templates')->where('tenant_id', (string) $owner->tenant_id)->count());
        }
        $this->assertSame(1, User::query()->count());
    }

    public function test_never_calls_demo_seeders(): void
    {
        $source = (string) file_get_contents(app_path('Console/Commands/ProductionRbacCommand.php'));
        foreach (['DatabaseSeeder', 'UserSeeder', 'TenantSeeder', 'DemoUsersSeeder', 'TestUsersSeeder', 'MockDataSeeder', 'UserRoleSeeder', 'db:seed'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source, $forbidden);
        }
    }

    private function roleHas(string $role, string $code): bool
    {
        return Role::query()->where('name', $role)->firstOrFail()->permissions()->where('code', $code)->exists();
    }
}
