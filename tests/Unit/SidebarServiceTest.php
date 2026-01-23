<?php

namespace Tests\Unit;

use App\Models\SidebarConfig;
use App\Models\User;
use App\Services\ConditionalDisplayService;
use App\Services\PermissionService;
use App\Services\SecurityGuardService;
use App\Services\SidebarService;
use App\Services\UserPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;
use Tests\Traits\RbacTestTrait;

class SidebarServiceTest extends TestCase
{
    use RefreshDatabase, RbacTestTrait;

    private PermissionService $permissionService;
    private ConditionalDisplayService $conditionalDisplayService;
    private SecurityGuardService $securityGuardService;
    private UserPreferenceService $userPreferenceService;
    private SidebarService $sidebarService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::flush();

        if (!Schema::hasTable('sidebar_configs')) {
            Schema::create('sidebar_configs', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('tenant_id')->nullable();
                $table->string('role_name')->nullable();
                $table->json('config')->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->integer('version')->default(1);
                $table->string('updated_by')->nullable();
                $table->timestamps();
            });
        }

        $this->permissionService = Mockery::mock(PermissionService::class);
        $this->conditionalDisplayService = Mockery::mock(ConditionalDisplayService::class);
        $this->securityGuardService = Mockery::mock(SecurityGuardService::class);
        $this->userPreferenceService = Mockery::mock(UserPreferenceService::class);

        $this->sidebarService = new SidebarService(
            $this->permissionService,
            $this->conditionalDisplayService,
            $this->securityGuardService,
            $this->userPreferenceService
        );

        $this->seedAuthenticatedSidebarUser();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Cache::flush();
        $guard = Auth::guard();

        if (method_exists($guard, 'logout')) {
            $guard->logout();
        }
        parent::tearDown();
    }

    public function test_builds_sidebar_with_tenant_overrides_and_permissions(): void
    {
        SidebarConfig::create([
            'tenant_id' => $this->user->tenant_id,
            'role_name' => 'project_manager',
            'config' => [
                'items' => [
                    [
                        'id' => 'tenant-dashboard',
                        'type' => 'link',
                        'label' => 'Tenant Dashboard',
                        'enabled' => true,
                        'order' => 5,
                        'required_permissions' => [],
                    ],
                ],
            ],
            'is_enabled' => true,
            'version' => 1,
            'updated_by' => $this->user->id,
        ]);

        $this->permissionService
            ->shouldReceive('filterSidebarItems')
            ->once()
            ->andReturnUsing(fn (array $items, User $user) => $items);

        $this->conditionalDisplayService
            ->shouldReceive('applyConditionalDisplay')
            ->once()
            ->andReturnUsing(fn (array $items, User $user) => $items);

        $this->securityGuardService
            ->shouldReceive('isSafeToUse')
            ->once()
            ->andReturn(true);

        $this->securityGuardService
            ->shouldReceive('sanitizeConfig')
            ->once()
            ->andReturnUsing(fn (array $config) => $this->sanitizeResultWithFlag($config));

        $this->userPreferenceService
            ->shouldReceive('applyUserPreferences')
            ->once()
            ->andReturnUsing(fn (array $config, User $user) => $this->appendUserPreferences($config));

        $result = $this->sidebarService->getSidebarForUser($this->user);

        $this->assertArrayHasKey('items', $result);
        $ids = array_column($result['items'], 'id');
        $this->assertContains('tenant-dashboard', $ids);
        $this->assertEquals(['theme' => 'dark'], $result['user_preferences']);
        $this->assertTrue($result['sanitized'] ?? false);
    }

    public function test_uses_fallback_sidebar_when_security_guard_detects_risk(): void
    {
        $this->permissionService
            ->shouldReceive('filterSidebarItems')
            ->once()
            ->andReturnUsing(fn (array $items, User $user) => $items);

        $this->conditionalDisplayService
            ->shouldReceive('applyConditionalDisplay')
            ->once()
            ->andReturnUsing(fn (array $items, User $user) => $items);

        $this->securityGuardService
            ->shouldReceive('isSafeToUse')
            ->once()
            ->andReturn(false);

        $this->securityGuardService
            ->shouldReceive('logSecurityEvent')
            ->once();

        $fallback = ['items' => [['id' => 'fallback']]];
        $this->securityGuardService
            ->shouldReceive('getFallbackSidebar')
            ->once()
            ->andReturn($fallback);

        $this->securityGuardService
            ->shouldReceive('sanitizeConfig')
            ->never();

        $this->userPreferenceService
            ->shouldReceive('applyUserPreferences')
            ->never();

        $result = $this->sidebarService->getSidebarForUser($this->user);

        $this->assertSame($fallback, $result);
    }

    public function test_applies_permission_filtering_before_returning_sidebar(): void
    {
        $filtered = [
            [
                'id' => 'filtered-item',
                'type' => 'link',
                'label' => 'Filtered',
                'enabled' => true,
                'order' => 99,
            ],
        ];

        $this->permissionService
            ->shouldReceive('filterSidebarItems')
            ->once()
            ->andReturn($filtered);

        $this->conditionalDisplayService
            ->shouldReceive('applyConditionalDisplay')
            ->once()
            ->andReturnUsing(fn (array $items, User $user) => $items);

        $this->securityGuardService
            ->shouldReceive('isSafeToUse')
            ->once()
            ->andReturn(true);

        $this->securityGuardService
            ->shouldReceive('sanitizeConfig')
            ->once()
            ->andReturnUsing(fn (array $config) => $config);

        $this->userPreferenceService
            ->shouldReceive('applyUserPreferences')
            ->once()
            ->andReturnUsing(fn (array $config, User $user) => $this->appendUserPreferences($config));

        $result = $this->sidebarService->getSidebarForUser($this->user);

        $this->assertCount(1, $result['items']);
        $this->assertSame('filtered-item', $result['items'][0]['id']);
        $this->assertEquals(['theme' => 'dark'], $result['user_preferences']);
    }

    private function seedAuthenticatedSidebarUser(): void
    {
        $authContext = $this->actingAsWithPermissions(['dashboard.view']);
        $this->user = $authContext['user'];

        $tenantId = (string) $this->user->tenant_id;
        $this->user->tenant_id = $tenantId;

        Sanctum::actingAs($this->user);
        Auth::shouldUse('sanctum');
        Auth::setUser($this->user);

        app()->instance('current_tenant_id', $tenantId);
        app()->instance('tenant', $this->user->tenant);
    }

    private function sanitizeResultWithFlag(array $config): array
    {
        $config['sanitized'] = true;

        return $config;
    }

    private function appendUserPreferences(array $config): array
    {
        $config['user_preferences'] = ['theme' => 'dark'];

        return $config;
    }
}
