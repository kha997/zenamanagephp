<?php declare(strict_types=1);

namespace Tests\Integration;

use App\Contracts\Dashboard\WidgetDataResolver;
use App\Models\DashboardWidget;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Traits\AuthenticationTrait;
use Tests\TestCase;

final class GAP052DashboardWidgetContractTest extends TestCase
{
    use RefreshDatabase, AuthenticationTrait;

    /** @test */
    public function both_retained_routes_cover_all_seven_roles_without_inventing_providers(): void
    {
        $capabilities = [
            'system_admin' => ['system_health', 'tenant_overview'],
            'project_manager' => ['project_overview', 'change_requests'],
            'design_lead' => ['design_progress'],
            'site_engineer' => ['daily_tasks'],
            'qc_inspector' => ['inspection_schedule', 'defect_analysis'],
            'client_rep' => ['project_summary'],
            'subcontractor_lead' => ['subcontractor_progress'],
        ];

        $tenant = Tenant::factory()->create();

        foreach ($capabilities as $dashboardRole => $codes) {
            $user = $this->createTenantUser(
                $tenant,
                ['role' => $dashboardRole, 'email' => $dashboardRole.'@gap052.example.com'],
                ['admin'],
            );

            foreach ($codes as $code) {
                DashboardWidget::create([
                    'name' => $code,
                    'code' => $code,
                    'type' => 'card',
                    'category' => 'gap052',
                    'permissions' => json_encode([]),
                    'config' => json_encode([]),
                    'is_active' => true,
                    'tenant_id' => $tenant->id,
                ]);
            }

            $this->apiAs($user, $tenant);

            $root = $this->getJson('/api/v1/dashboard/role-based');
            $root->assertOk()
                ->assertJsonStructure(['success', 'data' => ['role_config', 'widgets']]);
            $this->assertStringNotContainsString('DashboardDataAggregationService', $root->getContent());

            $widgets = $this->getJson('/api/v1/dashboard/role-based/widgets?include_data=1');
            $widgets->assertOk()->assertJsonStructure(['success', 'data' => ['widgets']]);

            $entries = collect($widgets->json('data.widgets'));
            foreach ($codes as $code) {
                $entry = $entries->firstWhere('widget.code', $code);
                self::assertIsArray($entry, "Missing GAP-052 widget {$code} for {$dashboardRole}");

                if (in_array($code, ['system_health', 'project_overview', 'inspection_schedule'], true)) {
                    self::assertSame('ready', $entry['state']);
                    self::assertIsArray($entry['data']);
                } else {
                    self::assertSame('degraded', $entry['state']);
                    self::assertNull($entry['data']);
                    self::assertSame('DASHBOARD.WIDGET_UNSUPPORTED', $entry['error']['code']);
                    self::assertStringNotContainsString('DashboardDataAggregationService', json_encode($entry));
                }
            }
        }
    }

    /** @test */
    public function metadata_only_requests_do_not_resolve_a_provider(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, ['role' => 'project_manager'], ['admin']);
        DashboardWidget::create([
            'name' => 'Project Overview',
            'code' => 'project_overview',
            'type' => 'card',
            'category' => 'gap052',
            'permissions' => json_encode([]),
            'config' => json_encode([]),
            'is_active' => true,
            'tenant_id' => $tenant->id,
        ]);

        $resolver = Mockery::mock(WidgetDataResolver::class);
        $resolver->shouldReceive('resolve')->never();
        $this->app->instance(WidgetDataResolver::class, $resolver);

        $this->apiAs($user, $tenant);
        $this->getJson('/api/v1/dashboard/role-based/widgets?include_data=0')
            ->assertOk()
            ->assertJsonPath('data.widgets.0.widget.code', 'project_overview')
            ->assertJsonMissingPath('data.widgets.0.state');
    }

    /** @test */
    public function unknown_dashboard_role_fails_closed_before_catalog_or_provider_execution(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, ['role' => 'unknown_gap052_role'], ['admin']);
        $resolver = Mockery::mock(WidgetDataResolver::class);
        $resolver->shouldReceive('resolve')->never();
        $this->app->instance(WidgetDataResolver::class, $resolver);

        $this->apiAs($user, $tenant);
        foreach (['/api/v1/dashboard/role-based', '/api/v1/dashboard/role-based/widgets'] as $endpoint) {
            $response = $this->getJson($endpoint);
            $response->assertStatus(403)
                ->assertJsonPath('error.code', 'DASHBOARD.ROLE_UNSUPPORTED');
            self::assertStringNotContainsString('client_rep', $response->getContent());
            self::assertStringNotContainsString('DashboardDataAggregationService', $response->getContent());
        }
    }

    /** @test */
    public function foreign_project_is_denied_request_wide_before_provider_execution(): void
    {
        $tenant = Tenant::factory()->create();
        $foreignTenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, ['role' => 'project_manager'], ['admin']);
        $foreignProject = Project::factory()->create([
            'tenant_id' => $foreignTenant->id,
            'pm_id' => $user->id,
        ]);

        $resolver = Mockery::mock(WidgetDataResolver::class);
        $resolver->shouldReceive('resolve')->never();
        $this->app->instance(WidgetDataResolver::class, $resolver);

        $this->apiAs($user, $tenant);
        foreach (['/api/v1/dashboard/role-based?project_id='.$foreignProject->id, '/api/v1/dashboard/role-based/widgets?include_data=1&project_id='.$foreignProject->id] as $endpoint) {
            $response = $this->getJson($endpoint);
            $response->assertStatus(403)
                ->assertJsonPath('error.code', 'DASHBOARD.PROJECT_FORBIDDEN')
                ->assertJsonPath('error.message', 'Dashboard project is not accessible.');
            self::assertStringNotContainsString('SQLSTATE', $response->getContent());
            self::assertStringNotContainsString('ForbiddenDashboardProject', $response->getContent());
        }
    }
}
