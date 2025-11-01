<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\FeatureFlagService;
use App\Models\UserPreference;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class FeatureFlagServiceTest extends TestCase
{
    use RefreshDatabase;

    protected FeatureFlagService $featureFlagService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->featureFlagService = app(FeatureFlagService::class);
        
        // Clear cache before each test
        Cache::flush();
    }

    /**
     * Test feature flag is enabled globally
     */
    public function test_feature_flag_is_enabled_globally(): void
    {
        // Set global config
        Config::set('features.ui.enable_focus_mode', true);
        
        $result = $this->featureFlagService->isEnabled('ui.enable_focus_mode');
        
        $this->assertTrue($result);
    }

    /**
     * Test feature flag is disabled globally
     */
    public function test_feature_flag_is_disabled_globally(): void
    {
        // Set global config
        Config::set('features.ui.enable_focus_mode', false);
        
        $result = $this->featureFlagService->isEnabled('ui.enable_focus_mode');
        
        $this->assertFalse($result);
    }

    /**
     * Test user-specific feature flag override
     */
    public function test_user_specific_feature_flag_override(): void
    {
        // Create user
        $user = User::factory()->create();
        
        // Set global config to false
        Config::set('features.ui.enable_focus_mode', false);
        
        // Set user-specific preference to true
        UserPreference::create([
            'user_id' => $user->id,
            'preferences' => [
                'feature_flags' => [
                    'ui' => [
                        'enable_focus_mode' => true
                    ]
                ]
            ]
        ]);
        
        $result = $this->featureFlagService->isEnabled('ui.enable_focus_mode', null, $user->id);
        
        $this->assertTrue($result);
    }

    /**
     * Test tenant-specific feature flag override
     */
    public function test_tenant_specific_feature_flag_override(): void
    {
        // Create tenant
        $tenant = Tenant::factory()->create();
        
        // Set global config to false
        Config::set('features.ui.enable_focus_mode', false);
        
        // Set tenant-specific preference to true
        $tenant->update([
            'preferences' => [
                'feature_flags' => [
                    'ui' => [
                        'enable_focus_mode' => true
                    ]
                ]
            ]
        ]);
        
        $result = $this->featureFlagService->isEnabled('ui.enable_focus_mode', $tenant->id);
        
        $this->assertTrue($result);
    }

    /**
     * Test setting user feature flag
     */
    public function test_setting_user_feature_flag(): void
    {
        $user = User::factory()->create();
        
        $result = $this->featureFlagService->setEnabled('ui.enable_focus_mode', true, null, $user->id);
        
        $this->assertTrue($result);
        
        // Verify the preference was created
        $preference = UserPreference::where('user_id', $user->id)->first();
        $this->assertNotNull($preference);
        $this->assertTrue($preference->isFocusModeEnabled());
    }

    /**
     * Test setting tenant feature flag
     */
    public function test_setting_tenant_feature_flag(): void
    {
        $tenant = Tenant::factory()->create();
        
        $result = $this->featureFlagService->setEnabled('ui.enable_focus_mode', true, $tenant->id);
        
        $this->assertTrue($result);
        
        // Verify the preference was set
        $tenant->refresh();
        $preferences = $tenant->preferences ?? [];
        $this->assertTrue($preferences['feature_flags']['ui']['enable_focus_mode'] ?? false);
    }

    /**
     * Test getting all feature flags
     */
    public function test_getting_all_feature_flags(): void
    {
        $user = User::factory()->create();
        
        // Set some feature flags
        Config::set('features.ui.enable_focus_mode', true);
        Config::set('features.ui.enable_rewards', false);
        
        $flags = $this->featureFlagService->getAllFlags(null, $user->id);
        
        $this->assertIsArray($flags);
        $this->assertTrue($flags['ui.enable_focus_mode']);
        $this->assertFalse($flags['ui.enable_rewards']);
    }

    /**
     * Test cache functionality
     */
    public function test_cache_functionality(): void
    {
        Config::set('features.ui.enable_focus_mode', true);
        
        // First call should hit the database/config
        $result1 = $this->featureFlagService->isEnabled('ui.enable_focus_mode');
        $this->assertTrue($result1);
        
        // Second call should hit cache
        $result2 = $this->featureFlagService->isEnabled('ui.enable_focus_mode');
        $this->assertTrue($result2);
        
        // Clear cache and verify it's cleared
        $this->featureFlagService->clearCache('ui.enable_focus_mode');
        
        // This should work the same way
        $result3 = $this->featureFlagService->isEnabled('ui.enable_focus_mode');
        $this->assertTrue($result3);
    }

    /**
     * Test feature flag hierarchy (user > tenant > global)
     */
    public function test_feature_flag_hierarchy(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        // Set global to false
        Config::set('features.ui.enable_focus_mode', false);
        
        // Set tenant to true
        $tenant->update([
            'preferences' => [
                'feature_flags' => [
                    'ui.enable_focus_mode' => true
                ]
            ]
        ]);
        
        // Set user to false (should override tenant)
        UserPreference::create([
            'user_id' => $user->id,
            'preferences' => [
                'feature_flags' => [
                    'ui.enable_focus_mode' => false
                ]
            ]
        ]);
        
        $result = $this->featureFlagService->isEnabled('ui.enable_focus_mode', $tenant->id, $user->id);
        
        // User preference should win
        $this->assertFalse($result);
    }

    /**
     * Test invalid feature flag returns false
     */
    public function test_invalid_feature_flag_returns_false(): void
    {
        $result = $this->featureFlagService->isEnabled('invalid.feature.flag');
        
        $this->assertFalse($result);
    }

    /**
     * Test feature flag with null values
     */
    public function test_feature_flag_with_null_values(): void
    {
        $user = User::factory()->create();
        
        // Set global to true
        Config::set('features.ui.enable_focus_mode', true);
        
        // Set user preference to null (should fall back to global)
        UserPreference::create([
            'user_id' => $user->id,
            'preferences' => [
                'feature_flags' => [
                    'ui.enable_focus_mode' => null
                ]
            ]
        ]);
        
        $result = $this->featureFlagService->isEnabled('ui.enable_focus_mode', null, $user->id);
        
        $this->assertTrue($result);
    }
}
