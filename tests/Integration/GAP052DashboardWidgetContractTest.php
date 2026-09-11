<?php declare(strict_types=1);

namespace Tests\Integration;

use App\Contracts\Dashboard\WidgetDataResolver;
use App\Contracts\Dashboard\WidgetDataProvider;
use App\Models\DashboardWidget;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Dashboard\DashboardWidgetDataResolver;
use App\Services\Dashboard\DashboardWidgetCatalog;
use App\Services\Dashboard\RoleBasedWidgetDataCalculator;
use App\Services\Dashboard\RoleBasedWidgetProvider;
use Illuminate\Support\Facades\Log;
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

    /** @test */
    public function provider_failure_degrades_one_widget_and_logs_structured_context(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, ['role' => 'project_manager'], ['admin']);

        foreach (['project_overview', 'task_progress'] as $code) {
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

        $throwingProvider = new class implements WidgetDataProvider {
            public function supports(string $widgetCode): bool
            {
                return $widgetCode === 'project_overview';
            }

            public function provide(DashboardWidget $widget, \App\Services\Dashboard\WidgetDataContext $context): \App\Services\Dashboard\WidgetDataResult
            {
                throw new \RuntimeException('sensitive SQL/class details');
            }
        };
        $resolver = new DashboardWidgetDataResolver([
            $throwingProvider,
            new RoleBasedWidgetProvider(new RoleBasedWidgetDataCalculator()),
        ]);
        $this->app->instance(WidgetDataResolver::class, $resolver);

        $requestId = 'req_gap052_provider_failure';
        Log::spy();
        $this->apiAs($user, $tenant);
        $response = $this->withHeaders(['X-Request-Id' => $requestId])
            ->getJson('/api/v1/dashboard/role-based/widgets?include_data=1');

        $response->assertOk();
        $entries = collect($response->json('data.widgets'));
        $failed = $entries->firstWhere('widget.code', 'project_overview');
        $safe = $entries->firstWhere('widget.code', 'task_progress');
        self::assertSame('degraded', $failed['state']);
        self::assertNull($failed['data']);
        self::assertSame('DASHBOARD.WIDGET_DATA_UNAVAILABLE', $failed['error']['code']);
        self::assertSame('ready', $safe['state']);
        self::assertStringNotContainsString('sensitive SQL/class details', $response->getContent());
        self::assertStringNotContainsString('RuntimeException', $response->getContent());

        Log::shouldHaveReceived('error')->with(
            'Dashboard widget provider failed.',
            Mockery::on(function (array $context) use ($requestId, $tenant, $user): bool {
                return $context['widget_code'] === 'project_overview'
                    && $context['tenant_id'] === (string) $tenant->id
                    && $context['user_id'] === (string) $user->id
                    && $context['role'] === 'project_manager'
                    && $context['project_id'] === null
                    && $context['request_id'] === $requestId
                    && $context['failure_reason'] === 'provider_failure';
            }),
        )->once();
    }

    /** @test */
    public function impossible_dashboard_composition_returns_safe_gap052_500_envelope(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, ['role' => 'project_manager'], ['admin']);
        $catalog = Mockery::mock(DashboardWidgetCatalog::class);
        $catalog->shouldReceive('configurationForRole')->andThrow(new \RuntimeException('internal composition detail'));
        $this->app->instance(DashboardWidgetCatalog::class, $catalog);

        $requestId = 'req_gap052_composition_failure';
        $this->apiAs($user, $tenant);
        $response = $this->withHeaders(['X-Request-Id' => $requestId])
            ->getJson('/api/v1/dashboard/role-based');

        $response->assertStatus(500)
            ->assertJsonPath('error.id', $requestId)
            ->assertJsonPath('error.code', 'DASHBOARD.INTERNAL_ERROR')
            ->assertJsonPath('error.message', 'Dashboard data is temporarily unavailable.')
            ->assertJsonPath('error.details', []);
        self::assertStringNotContainsString('internal composition detail', $response->getContent());
        self::assertStringNotContainsString('RuntimeException', $response->getContent());
    }
}
