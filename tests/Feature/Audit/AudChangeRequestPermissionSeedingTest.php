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

        $withoutName = Permission::whereNull('name')->count();

        $this->assertSame(0, $withoutName, 'No permission row should have a NULL name column after seeding.');
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
