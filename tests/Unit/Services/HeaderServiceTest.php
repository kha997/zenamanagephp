<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\HeaderService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

class HeaderServiceTest extends TestCase
{
    use RefreshDatabase;
    
    private HeaderService $headerService;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->headerService = app(HeaderService::class);
        Cache::flush();
    }
    
    /**
     * Test getNavigation with admin context
     */
    public function test_get_navigation_admin_context(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        
        $navigation = $this->headerService->getNavigation($user, 'admin');
        
        $this->assertIsArray($navigation);
        $this->assertNotEmpty($navigation);
        $this->assertArrayHasKey('key', $navigation[0]);
        $this->assertArrayHasKey('label', $navigation[0]);
        $this->assertArrayHasKey('route', $navigation[0]);
        
        // Check admin-specific items exist
        $keys = array_column($navigation, 'key');
        $this->assertContains('dashboard', $keys);
        $this->assertContains('users', $keys);
        $this->assertContains('tenants', $keys);
    }
    
    /**
     * Test getNavigation with app context
     */
    public function test_get_navigation_app_context(): void
    {
        $user = User::factory()->create();
        
        $navigation = $this->headerService->getNavigation($user, 'app');
        
        $this->assertIsArray($navigation);
        $this->assertNotEmpty($navigation);
        
        $keys = array_column($navigation, 'key');
        $this->assertContains('dashboard', $keys);
        $this->assertContains('projects', $keys);
        $this->assertContains('tasks', $keys);
    }
    
    /**
     * Test getNavigation with null user
     */
    public function test_get_navigation_null_user(): void
    {
        $navigation = $this->headerService->getNavigation(null, 'app');
        
        $this->assertIsArray($navigation);
        $this->assertNotEmpty($navigation);
        $this->assertEquals('dashboard', $navigation[0]['key']);
    }
    
    /**
     * Test getNavigation caching
     */
    public function test_get_navigation_caching(): void
    {
        $user = User::factory()->create();
        
        // First call
        $navigation1 = $this->headerService->getNavigation($user, 'app');
        
        // Second call should use cache
        $navigation2 = $this->headerService->getNavigation($user, 'app');
        
        $this->assertEquals($navigation1, $navigation2);
        
        // Verify cache exists
        $cacheKey = "header_navigation_{$user->id}_app";
        $this->assertTrue(Cache::has($cacheKey));
    }
    
    /**
     * Test getNotifications
     */
    public function test_get_notifications(): void
    {
        $user = User::factory()->create();
        
        $notifications = $this->headerService->getNotifications($user);
        
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $notifications);
    }
    
    /**
     * Test getNotifications with null user
     */
    public function test_get_notifications_null_user(): void
    {
        $notifications = $this->headerService->getNotifications(null);
        
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $notifications);
        $this->assertTrue($notifications->isEmpty());
    }
    
    /**
     * Test getUnreadCount
     */
    public function test_get_unread_count(): void
    {
        $user = User::factory()->create();
        
        $count = $this->headerService->getUnreadCount($user);
        
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }
    
    /**
     * Test getUnreadCount with null user
     */
    public function test_get_unread_count_null_user(): void
    {
        $count = $this->headerService->getUnreadCount(null);
        
        $this->assertEquals(0, $count);
    }
    
    /**
     * Test getRouteForVariant helper method
     */
    public function test_get_route_for_variant(): void
    {
        // Test with existing route
        if (Route::has('app.dashboard')) {
            $route = $this->headerService->getRouteForVariant('app', ['app.dashboard', 'dashboard']);
            $this->assertNotNull($route);
            $this->assertIsString($route);
        }
        
        // Test with non-existing routes
        $route = $this->headerService->getRouteForVariant('app', ['non.existing.route']);
        $this->assertNull($route);
    }
    
    /**
     * Test invalidateNotificationCache
     */
    public function test_invalidate_notification_cache(): void
    {
        $user = User::factory()->create();
        
        // Set cache
        Cache::put("header_notifications_{$user->id}", ['test'], 60);
        Cache::put("header_unread_count_{$user->id}", 5, 60);
        
        // Invalidate
        $this->headerService->invalidateNotificationCache($user);
        
        // Verify cache cleared
        $this->assertFalse(Cache::has("header_notifications_{$user->id}"));
        $this->assertFalse(Cache::has("header_unread_count_{$user->id}"));
    }
    
    /**
     * Test invalidateNotificationCache with null user
     */
    public function test_invalidate_notification_cache_null_user(): void
    {
        // Should not throw error
        $this->headerService->invalidateNotificationCache(null);
        $this->assertTrue(true);
    }
    
    /**
     * Test invalidateAllCaches
     */
    public function test_invalidate_all_caches(): void
    {
        $user = User::factory()->create();
        
        // Set caches
        Cache::put("header_notifications_{$user->id}", ['test'], 60);
        Cache::put("header_navigation_{$user->id}_app", ['test'], 60);
        Cache::put("header_alert_count_{$user->id}", 5, 60);
        
        // Invalidate all
        $this->headerService->invalidateAllCaches($user);
        
        // Verify all caches cleared
        $this->assertFalse(Cache::has("header_notifications_{$user->id}"));
        $this->assertFalse(Cache::has("header_navigation_{$user->id}_app"));
        $this->assertFalse(Cache::has("header_alert_count_{$user->id}"));
    }
    
    /**
     * Test getUserTheme
     */
    public function test_get_user_theme(): void
    {
        $user = User::factory()->create();
        
        $theme = $this->headerService->getUserTheme($user);
        
        $this->assertIsString($theme);
        $this->assertContains($theme, ['light', 'dark']);
    }
    
    /**
     * Test getUserTheme with null user
     */
    public function test_get_user_theme_null_user(): void
    {
        $theme = $this->headerService->getUserTheme(null);
        
        $this->assertEquals('light', $theme);
    }
    
    /**
     * Test getAlertCount for admin
     */
    public function test_get_alert_count_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        
        $count = $this->headerService->getAlertCount($user);
        
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }
    
    /**
     * Test getAlertCount for non-admin
     */
    public function test_get_alert_count_non_admin(): void
    {
        $user = User::factory()->create();
        
        $count = $this->headerService->getAlertCount($user);
        
        $this->assertEquals(0, $count);
    }
}

