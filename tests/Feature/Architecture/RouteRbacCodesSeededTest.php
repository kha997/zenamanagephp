<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Models\Permission;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TreasuryRolePermissionSeeder;
use Database\Seeders\V1PermissionGapSeeder;
use Database\Seeders\ZenaAdminRolePermissionSeeder;
use Database\Seeders\ZenaPermissionsSeeder;
use Database\Seeders\ZenaProjectManagerRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * GAP-098 — every permission code a route requires through `rbac:<code>` must be
 * created by the RBAC seeders of DatabaseSeeder. A code nobody seeds makes the
 * route super-admin only (GAP-021, GAP-098). Role names (no dot) are not codes.
 */
class RouteRbacCodesSeededTest extends TestCase
{
    use RefreshDatabase;

    /** The RBAC block of DatabaseSeeder, in order. */
    private const RBAC_SEEDERS = [
        RoleSeeder::class,
        PermissionSeeder::class,
        ZenaPermissionsSeeder::class,
        ZenaAdminRolePermissionSeeder::class,
        TreasuryRolePermissionSeeder::class,
        ZenaProjectManagerRolePermissionSeeder::class,
        V1PermissionGapSeeder::class,
    ];

    public function test_every_route_rbac_code_is_seeded(): void
    {
        $this->seed(self::RBAC_SEEDERS);

        $required = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'rbac:')) {
                    $code = explode(',', substr($middleware, 5))[0];
                    if (str_contains($code, '.')) {
                        $required[$code] = true;
                    }
                }
            }
        }

        $seeded = Permission::query()->pluck('code')->all();
        $missing = array_values(array_diff(array_keys($required), $seeded));
        sort($missing);

        $this->assertNotEmpty($required);
        $this->assertSame([], $missing, 'Route permission codes no seeder creates: ' . implode(', ', $missing));
    }
}
