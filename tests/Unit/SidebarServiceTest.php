<?php

namespace Tests\Unit;

use App\Services\ConditionalDisplayService;
use App\Services\PermissionService;
use App\Services\SecurityGuardService;
use App\Services\SidebarService;
use App\Services\UserPreferenceService;
use Mockery;
use Tests\TestCase;

class SidebarServiceTest extends TestCase
{
    protected SidebarService $sidebarService;
    protected PermissionService $permissionService;
    protected ConditionalDisplayService $conditionalDisplayService;
    protected SecurityGuardService $securityGuardService;
    protected UserPreferenceService $userPreferenceService;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_get_default_sidebar_config()
    {
        $sidebarConfig = $this->sidebarService->getSidebarForUser(null);

        $this->assertIsArray($sidebarConfig);
        $this->assertArrayHasKey('items', $sidebarConfig);
        $this->assertSame('dashboard', $sidebarConfig['items'][0]['id'] ?? null);
    }

    /** @test */
    public function it_can_build_sidebar_for_role()
    {
        $sidebarConfig = $this->sidebarService->getSidebarForRole('project_manager');

        $this->assertIsArray($sidebarConfig);
        $this->assertArrayHasKey('items', $sidebarConfig);
    }

    /** @test */
    public function it_merges_tenant_configuration_over_default_for_role()
    {
        $service = $this->serviceWithTenantConfig([
            'items' => [
                ['id' => 'dashboard', 'type' => 'link', 'label' => 'Tenant Dashboard', 'enabled' => true, 'order' => 10],
                ['id' => 'tenant-reports', 'type' => 'link', 'label' => 'Tenant Reports', 'enabled' => true, 'order' => 15],
            ],
        ]);

        $sidebarConfig = $service->getSidebarForRole('project_manager', 'tenant-1');
        $itemsById = collect($sidebarConfig['items'] ?? [])->keyBy('id');

        $this->assertSame('Tenant Dashboard', $itemsById->get('dashboard')['label'] ?? null);
        $this->assertTrue($itemsById->has('tenant-reports'));
        $this->assertTrue($itemsById->has('tasks'));
    }

    /** @test */
    public function it_falls_back_to_default_when_tenant_config_has_no_items()
    {
        $service = $this->serviceWithTenantConfig([
            'metadata' => ['source' => 'tenant-override-without-items'],
        ]);

        $sidebarConfig = $service->getSidebarForRole('project_manager', 'tenant-1');
        $itemsById = collect($sidebarConfig['items'] ?? [])->keyBy('id');

        $this->assertSame('Dashboard', $itemsById->get('dashboard')['label'] ?? null);
        $this->assertTrue($itemsById->has('tasks'));
    }

    /** @test */
    public function it_handles_configuration_ordering_after_merge()
    {
        $service = $this->serviceWithTenantConfig([
            'items' => [
                ['id' => 'dashboard', 'type' => 'link', 'label' => 'Dashboard', 'enabled' => true, 'order' => 50],
                ['id' => 'tenant-first', 'type' => 'link', 'label' => 'Tenant First', 'enabled' => true, 'order' => 5],
            ],
        ]);

        $sidebarConfig = $service->getSidebarForRole('project_manager', 'tenant-1');
        $items = $sidebarConfig['items'] ?? [];

        $this->assertNotEmpty($items);
        $this->assertSame('tenant-first', $items[0]['id']);
    }

    /** @test */
    public function it_supports_nested_group_items_from_overrides()
    {
        $service = $this->serviceWithTenantConfig([
            'items' => [
                [
                    'id' => 'grp-custom',
                    'type' => 'group',
                    'label' => 'Custom Group',
                    'children' => [
                        ['id' => 'child-link', 'type' => 'link', 'label' => 'Child', 'enabled' => true, 'order' => 1],
                    ],
                    'enabled' => true,
                    'order' => 25,
                ],
            ],
        ]);

        $sidebarConfig = $service->getSidebarForRole('project_manager', 'tenant-1');
        $itemsById = collect($sidebarConfig['items'] ?? [])->keyBy('id');

        $this->assertTrue($itemsById->has('grp-custom'));
        $this->assertIsArray($itemsById->get('grp-custom')['children'] ?? null);
    }

    /** @test */
    public function it_keeps_disabled_items_when_provided_by_configuration()
    {
        $service = $this->serviceWithTenantConfig([
            'items' => [
                ['id' => 'disabled-item', 'type' => 'link', 'label' => 'Disabled Item', 'enabled' => false, 'order' => 12],
            ],
        ]);

        $sidebarConfig = $service->getSidebarForRole('project_manager', 'tenant-1');
        $itemsById = collect($sidebarConfig['items'] ?? [])->keyBy('id');

        $this->assertTrue($itemsById->has('disabled-item'));
        $this->assertFalse($itemsById->get('disabled-item')['enabled']);
    }

    private function serviceWithTenantConfig(array $tenantConfig): SidebarService
    {
        $service = Mockery::mock(
            SidebarService::class,
            [
                $this->permissionService,
                $this->conditionalDisplayService,
                $this->securityGuardService,
                $this->userPreferenceService,
            ]
        )->makePartial();

        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('getTenantConfig')->andReturn($tenantConfig);

        return $service;
    }
}
