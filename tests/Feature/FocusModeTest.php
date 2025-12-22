<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

class FocusModeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected FeatureFlagService $featureFlagService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->featureFlagService = app(FeatureFlagService::class);
        
        // Enable focus mode feature globally
        Config::set('features.ui.enable_focus_mode', true);
    }

    /**
     * Test focus mode toggle when feature is enabled
     */
    public function test_focus_mode_toggle_when_feature_enabled(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/app/focus-mode/toggle');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'focus_mode_enabled',
                    'message'
                ]
            ]);

        $this->assertTrue($response->json('data.focus_mode_enabled'));
    }

    /**
     * Test focus mode toggle when feature is disabled
     */
    public function test_focus_mode_toggle_when_feature_disabled(): void
    {
        // Disable feature globally
        Config::set('features.ui.enable_focus_mode', false);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/app/focus-mode/toggle');

        $response->assertStatus(403)
            ->assertJsonStructure([
                'success',
                'message',
                'error' => [
                    'id',
                    'code',
                    'message'
                ]
            ]);

        $this->assertFalse($response->json('success'));
        $this->assertEquals('FEATURE_DISABLED', $response->json('error.id'));
    }

    /**
     * Test focus mode status endpoint
     */
    public function test_focus_mode_status_endpoint(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/app/focus-mode/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'feature_enabled',
                    'user_enabled',
                    'focus_mode_active'
                ]
            ]);

        $this->assertTrue($response->json('data.feature_enabled'));
        $this->assertFalse($response->json('data.user_enabled')); // Default false
        $this->assertFalse($response->json('data.focus_mode_active'));
    }

    /**
     * Test setting focus mode state explicitly
     */
    public function test_setting_focus_mode_state(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/app/focus-mode/set-state', [
                'enabled' => true
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'focus_mode_enabled',
                    'message'
                ]
            ]);

        $this->assertTrue($response->json('data.focus_mode_enabled'));

        // Verify database persistence
        $preference = UserPreference::where('user_id', $this->user->id)->first();
        $this->assertNotNull($preference);
        $this->assertTrue($preference->isFocusModeEnabled());
    }

    /**
     * Test focus mode configuration endpoint
     */
    public function test_focus_mode_configuration_endpoint(): void
    {
        // Set user preference
        UserPreference::create([
            'user_id' => $this->user->id,
            'preferences' => [
                'ui' => [
                    'focus_mode' => true
                ]
            ]
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/app/focus-mode/config');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'feature_enabled',
                    'user_enabled',
                    'config' => [
                        'sidebar_collapsed',
                        'hide_secondary_kpis',
                        'minimal_theme',
                        'show_main_content_only',
                        'theme_class'
                    ]
                ]
            ]);

        $this->assertTrue($response->json('data.feature_enabled'));
        $this->assertTrue($response->json('data.user_enabled'));
        $this->assertTrue($response->json('data.config.sidebar_collapsed'));
        $this->assertTrue($response->json('data.config.hide_secondary_kpis'));
        $this->assertTrue($response->json('data.config.minimal_theme'));
        $this->assertTrue($response->json('data.config.show_main_content_only'));
        $this->assertEquals('focus-mode', $response->json('data.config.theme_class'));
    }

    /**
     * Test focus mode configuration when feature is disabled
     */
    public function test_focus_mode_configuration_when_feature_disabled(): void
    {
        // Disable feature globally
        Config::set('features.ui.enable_focus_mode', false);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/app/focus-mode/config');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'feature_enabled',
                    'config'
                ]
            ]);

        $this->assertFalse($response->json('data.feature_enabled'));
        $this->assertNull($response->json('data.config'));
    }

    /**
     * Test focus mode toggle persistence
     */
    public function test_focus_mode_toggle_persistence(): void
    {
        // First toggle - should enable
        $response1 = $this->actingAs($this->user)
            ->postJson('/api/v1/app/focus-mode/toggle');

        $this->assertTrue($response1->json('data.focus_mode_enabled'));

        // Second toggle - should disable
        $response2 = $this->actingAs($this->user)
            ->postJson('/api/v1/app/focus-mode/toggle');

        $this->assertFalse($response2->json('data.focus_mode_enabled'));

        // Verify database persistence
        $preference = UserPreference::where('user_id', $this->user->id)->first();
        $this->assertNotNull($preference);
        $this->assertFalse($preference->isFocusModeEnabled());
    }

    /**
     * Test focus mode with unauthenticated user
     */
    public function test_focus_mode_with_unauthenticated_user(): void
    {
        $response = $this->postJson('/api/v1/app/focus-mode/toggle');

        $response->assertStatus(401)
            ->assertJsonStructure([
                'success',
                'message',
                'error' => [
                    'id',
                    'code',
                    'message'
                ]
            ]);

        $this->assertFalse($response->json('success'));
        $this->assertEquals('UNAUTHENTICATED', $response->json('error.id'));
    }

    /**
     * Test focus mode validation
     */
    public function test_focus_mode_validation(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/app/focus-mode/set-state', [
                'enabled' => 'invalid'
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'error' => [
                    'id',
                    'code',
                    'message',
                    'details'
                ]
            ]);
    }

    /**
     * Test focus mode cache clearing
     */
    public function test_focus_mode_cache_clearing(): void
    {
        // Enable focus mode
        $this->actingAs($this->user)
            ->postJson('/api/v1/app/focus-mode/set-state', [
                'enabled' => true
            ]);

        // Check status
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/app/focus-mode/status');

        $this->assertTrue($response->json('data.focus_mode_active'));

        // Disable focus mode
        $this->actingAs($this->user)
            ->postJson('/api/v1/app/focus-mode/set-state', [
                'enabled' => false
            ]);

        // Check status again
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/app/focus-mode/status');

        $this->assertFalse($response->json('data.focus_mode_active'));
    }
}
