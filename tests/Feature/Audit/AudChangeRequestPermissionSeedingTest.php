<?php declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * EVIDENCE TEST for AUD-22 (docs/audits/2026-07-23-end-to-end-operational-audit.md).
 * Runs the real production seeder chain and checks the actual resulting
 * permission/role state.
 */
class AudChangeRequestPermissionSeedingTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_seeder_sets_name_column_for_every_permission_it_creates(): void
    {
        Artisan::call('db:seed', ['--force' => true]);

        // AUD-28 (new, separate finding — not part of AUD-22's original scope):
        // RoleSeeder.php has the identical "firstOrCreate without a name key" bug
        // for 'project.read' and 'user.manage', and neither PermissionSeeder.php
        // nor ZenaPermissionsSeeder.php's canonical lists ever touch those two
        // codes afterward, so they stay NULL. 'user.manage' is confirmed dead
        // (only referenced as a hardcoded string in
        // app/Http/Controllers/Admin/SimpleSidebarBuilderController.php:126, not
        // by any real RBAC check). Fixing RoleSeeder.php is out of scope for this
        // task (see Global Constraints) — allowlisting these two so this test
        // still catches any *other* NULL-name permission as a real regression,
        // without pretending the RoleSeeder bug doesn't exist.
        $knownPreExistingExceptions = ['project.read', 'user.manage'];

        $withoutName = Permission::whereNull('name')
            ->whereNotIn('code', $knownPreExistingExceptions)
            ->pluck('code')
            ->all();

        $this->assertSame(
            [],
            $withoutName,
            'No permission row should have a NULL name column after seeding (excluding the known RoleSeeder.php bug tracked as AUD-28).'
        );
    }

    public function test_project_manager_can_pass_the_real_change_request_approve_middleware(): void
    {
        Artisan::call('db:seed', ['--force' => true]);

        $pmRole = Role::where('name', 'Project Manager')->first();
        $this->assertNotNull($pmRole, "Expected a 'Project Manager' role to exist after seeding.");

        $hasHyphenatedApprove = $pmRole->permissions()->where('name', 'change-request.approve')->exists();

        $this->assertTrue(
            $hasHyphenatedApprove,
            "'Project Manager' must have the hyphenated 'change-request.approve' permission -- the one routes/api_zena.php:423's rbac:change-request.approve middleware actually checks."
        );
    }
}
