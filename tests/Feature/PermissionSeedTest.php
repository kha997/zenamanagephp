<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PermissionSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_codes_exist_after_migrate_fresh_seed(): void
    {
        Artisan::call('migrate:fresh', [
            '--seed' => true,
        ]);

        $expectedCodes = [
            'project.create',
            'project.read',
            'project.update',
            'project.delete',
            'project.write',
            'document.upload_files',
            'inspection.read',
            'dashboard.view',
        ];

        $actualCount = Permission::whereIn('code', $expectedCodes)->count();

        $this->assertSame(
            count($expectedCodes),
            $actualCount,
            'Expected every canonical permission code to exist after seeding.'
        );
    }
}
