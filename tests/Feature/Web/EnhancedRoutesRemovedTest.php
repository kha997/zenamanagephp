<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-016: /projects-enhanced and /admin-dashboard-enhanced rendered views that no longer
 * exist and answered signed-in users with HTTP 500. Both routes were removed.
 */
class EnhancedRoutesRemovedTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    public function test_signed_in_user_gets_not_found_on_both_urls(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->createTenantUser($tenant, [], ['admin'], ['admin']);

        $this->actingAs($admin)->get('/projects-enhanced')->assertNotFound();
        $this->actingAs($admin)->get('/admin-dashboard-enhanced')->assertNotFound();
    }

    public function test_route_names_are_gone(): void
    {
        $this->assertFalse(Route::has('projects-enhanced'));
        $this->assertFalse(Route::has('admin-dashboard-enhanced'));
    }
}
