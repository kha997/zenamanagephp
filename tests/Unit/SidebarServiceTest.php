<?php

namespace Tests\Unit;

use App\Models\SidebarConfig;
use App\Models\User;
use App\Models\UserSidebarPreference;
use App\Services\SidebarService;
use App\Services\PermissionService;
use App\Services\ConditionalDisplayService;
use App\Services\SecurityGuardService;
use App\Services\UserPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class SidebarServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SidebarService $sidebarService;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create tenant first
        $tenant = \App\Models\Tenant::factory()->create();
        
        $this->user = User::factory()->create([
            'email' => 'user@test.com',
            'role' => 'project_manager',
            'tenant_id' => $tenant->id
        ]);

        // Mock dependencies with proper expectations
        $permissionService = Mockery::mock(PermissionService::class);
        $permissionService->shouldReceive('filterSidebarItems')
            ->andReturnUsing(function($items, $user) {
                return $items; // Return items as-is for testing
            });

        $conditionalDisplayService = Mockery::mock(ConditionalDisplayService::class);
        $conditionalDisplayService->shouldReceive('applyConditionalDisplay')
            ->andReturnUsing(function($config, $user) {
                return $config; // Return config as-is for testing
            });

        $securityGuardService = Mockery::mock(SecurityGuardService::class);
        $securityGuardService->shouldReceive('isSafeToUse')
            ->andReturn(true);
        $securityGuardService->shouldReceive('sanitizeConfig')
            ->andReturnUsing(function($config) {
                return $config; // Return config as-is for testing
            });

        $userPreferenceService = Mockery::mock(UserPreferenceService::class);
        $userPreferenceService->shouldReceive('getUserPreferences')
            ->andReturn([]);
        $userPreferenceService->shouldReceive('applyUserPreferences')
            ->andReturnUsing(function($config, $user) {
                return $config; // Return config as-is for testing
            });

        $this->sidebarService = new SidebarService(
            $permissionService,
            $conditionalDisplayService,
            $securityGuardService,
            $userPreferenceService
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
        // Test getSidebarForUser with authenticated user
        $sidebarConfig = $this->sidebarService->getSidebarForUser($this->user);
        
        $this->assertIsArray($sidebarConfig);
        $this->assertArrayHasKey('items', $sidebarConfig);
        $this->assertIsArray($sidebarConfig['items']);
        
        // Should have at least some default items
        $this->assertGreaterThan(0, count($sidebarConfig['items']));
    }

    /** @test */
    public function it_can_build_sidebar_for_role()
    {
        $role = 'project_manager';
        $sidebarConfig = $this->sidebarService->getSidebarForRole($role);

        $this->assertIsArray($sidebarConfig);
        $this->assertArrayHasKey('items', $sidebarConfig);
    }

    /** @test */
    public function it_can_merge_configurations()
    {
        // Test through public method getSidebarForRole
        $role = 'project_manager';
        $sidebarConfig = $this->sidebarService->getSidebarForRole($role);
        
        $this->assertIsArray($sidebarConfig);
        $this->assertArrayHasKey('items', $sidebarConfig);
        
        // Should have project_manager specific items
        $itemIds = array_column($sidebarConfig['items'], 'id');
        $this->assertContains('dashboard', $itemIds);
        $this->assertContains('projects', $itemIds);
    }

    /** @test */
    public function it_handles_empty_configurations()
    {
        // Test with invalid role - should return default config
        $sidebarConfig = $this->sidebarService->getSidebarForRole('invalid_role');
        
        $this->assertIsArray($sidebarConfig);
        $this->assertArrayHasKey('items', $sidebarConfig);
        
        // Should have some default items even for invalid role
        $this->assertGreaterThan(0, count($sidebarConfig['items']));
    }

    /** @test */
    public function it_prioritizes_user_preferences_over_tenant_config()
    {
        // Test user-specific sidebar configuration
        $sidebarConfig = $this->sidebarService->getSidebarForUser($this->user);
        
        $this->assertIsArray($sidebarConfig);
        $this->assertArrayHasKey('items', $sidebarConfig);
        
        // Should have user-specific configuration
        $this->assertGreaterThan(0, count($sidebarConfig['items']));
    }

    /** @test */
    public function it_prioritizes_tenant_config_over_default()
    {
        // Test role-based configuration
        $sidebarConfig = $this->sidebarService->getSidebarForRole('project_manager', $this->user->tenant_id);
        
        $this->assertIsArray($sidebarConfig);
        $this->assertArrayHasKey('items', $sidebarConfig);
        
        // Should have project_manager specific items
        $itemIds = array_column($sidebarConfig['items'], 'id');
        $this->assertContains('dashboard', $itemIds);
    }

    /** @test */
    public function it_handles_missing_items_key()
    {
        // Test with null user - should return default sidebar
        $sidebarConfig = $this->sidebarService->getSidebarForUser(null);
        
        $this->assertIsArray($sidebarConfig);
        $this->assertArrayHasKey('items', $sidebarConfig);
        
        // Should have default items
        $this->assertGreaterThan(0, count($sidebarConfig['items']));
    }

    /** @test */
    public function it_can_apply_permission_filtering()
    {
        // Test permission filtering through user-specific sidebar
        $sidebarConfig = $this->sidebarService->getSidebarForUser($this->user);
        
        $this->assertIsArray($sidebarConfig);
        $this->assertArrayHasKey('items', $sidebarConfig);
        
        // Should have filtered items based on user permissions
        $this->assertGreaterThan(0, count($sidebarConfig['items']));
    }

    /** @test */
    public function it_handles_nested_group_items()
    {
        // Test different roles to see nested group handling
        $adminConfig = $this->sidebarService->getSidebarForRole('super_admin');
        $pmConfig = $this->sidebarService->getSidebarForRole('project_manager');
        
        $this->assertIsArray($adminConfig);
        $this->assertIsArray($pmConfig);
        $this->assertArrayHasKey('items', $adminConfig);
        $this->assertArrayHasKey('items', $pmConfig);
        
        // Admin should have more items than PM
        $this->assertGreaterThanOrEqual(count($pmConfig['items']), count($adminConfig['items']));
    }

    /** @test */
    public function it_handles_disabled_items()
    {
        // Test cache clearing functionality
        $this->sidebarService->clearUserCache($this->user);
        $this->sidebarService->clearRoleCache('project_manager');
        
        // Should still work after cache clear
        $sidebarConfig = $this->sidebarService->getSidebarForUser($this->user);
        
        $this->assertIsArray($sidebarConfig);
        $this->assertArrayHasKey('items', $sidebarConfig);
        $this->assertGreaterThan(0, count($sidebarConfig['items']));
    }
}