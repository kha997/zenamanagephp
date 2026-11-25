<?php

namespace Tests\Feature\GAP055;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;
use Tests\Traits\AuthenticationTrait;

/**
 * GAP-055: the admin maintenance clear-cache endpoint must never flush the
 * shared cache store, which also holds every tenant's rate-limit counters.
 */
final class AdminClearCacheFailsClosedTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticationTrait;

    public function test_clear_cache_endpoint_refuses_and_leaves_other_tenants_state_intact(): void
    {
        $otherTenant = Tenant::factory()->create();
        $tenant = Tenant::factory()->create();
        $superAdmin = $this->createTenantUser($tenant, ['email' => 'root@gap055.example.com'], ['super_admin']);
        // OWN-2026-024 H3: host maintenance is reached only by a Platform Operator (explicit platform code + active ledger row),
        // no longer by the role name super_admin. The refusal assertions below are unchanged.
        $this->provisionTestPlatformOperator($superAdmin, ['platform.maintenance.execute']);

        $limiterKey = 'zena-login|victim@other-tenant.example.com';
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($limiterKey, 60);
        }
        $otherTenantKey = "tenant:{$otherTenant->id}:gap055-probe";
        Cache::put($otherTenantKey, 'kept', 600);

        $response = $this->actingAs($superAdmin)
            ->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
            ->postJson('/admin/maintenance/clear-cache', [], ['X-Tenant-ID' => (string) $tenant->id]);

        $response->assertStatus(409)->assertJson(['success' => false]);
        $this->assertSame(5, RateLimiter::attempts($limiterKey));
        $this->assertSame('kept', Cache::get($otherTenantKey));
    }
}
