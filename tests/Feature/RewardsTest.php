<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

class RewardsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected FeatureFlagService $featureFlagService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->featureFlagService = app(FeatureFlagService::class);
        
        // Enable rewards feature globally
        Config::set('features.ui.enable_rewards', true);
    }

    /**
     * Test rewards toggle when feature is enabled
     */
    public function test_rewards_toggle_when_feature_enabled(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/app/rewards/toggle');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'rewards_enabled',
                    'message'
                ]
            ]);

        $this->assertTrue($response->json('data.rewards_enabled'));
    }

    /**
     * Test rewards toggle when feature is disabled
     */
    public function test_rewards_toggle_when_feature_disabled(): void
    {
        // Disable feature globally
        Config::set('features.ui.enable_rewards', false);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/app/rewards/toggle');

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
     * Test rewards status endpoint
     */
    public function test_rewards_status_endpoint(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/app/rewards/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'feature_enabled',
                    'user_enabled',
                    'rewards_active'
                ]
            ]);

        $this->assertTrue($response->json('data.feature_enabled'));
        $this->assertTrue($response->json('data.user_enabled')); // Default true for rewards
        $this->assertTrue($response->json('data.rewards_active'));
    }

    /**
     * Test task completion rewards trigger
     */
    public function test_task_completion_rewards_trigger(): void
    {
        $taskData = [
            'task_id' => 'test-task-123',
            'task_title' => 'Complete test task',
            'completion_time' => now()->toISOString()
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/app/rewards/trigger-task-completion', $taskData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'rewards_triggered',
                    'reward_data' => [
                        'task_id',
                        'task_title',
                        'completion_time',
                        'animation_type',
                        'duration',
                        'messages' => [
                            'congrats_message',
                            'celebration_title',
                            'task_completed',
                            'keep_it_up'
                        ],
                        'config' => [
                            'particle_count',
                            'spread',
                            'start_velocity',
                            'colors'
                        ]
                    ],
                    'message'
                ]
            ]);

        $this->assertTrue($response->json('data.rewards_triggered'));
        $this->assertEquals('test-task-123', $response->json('data.reward_data.task_id'));
        $this->assertEquals('Complete test task', $response->json('data.reward_data.task_title'));
        $this->assertEquals('confetti', $response->json('data.reward_data.animation_type'));
        $this->assertEquals(4000, $response->json('data.reward_data.duration'));
    }

    /**
     * Test task completion rewards when user has disabled rewards
     */
    public function test_task_completion_rewards_when_user_disabled(): void
    {
        // Disable rewards for user
        UserPreference::create([
            'user_id' => $this->user->id,
            'preferences' => [
                'ui' => [
                    'rewards' => false
                ]
            ]
        ]);

        $taskData = [
            'task_id' => 'test-task-123',
            'task_title' => 'Complete test task',
            'completion_time' => now()->toISOString()
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/app/rewards/trigger-task-completion', $taskData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'rewards_triggered',
                    'message'
                ]
            ]);

        $this->assertFalse($response->json('data.rewards_triggered'));
        $this->assertEquals('User has rewards disabled', $response->json('data.message'));
    }

    /**
     * Test task completion rewards when feature is disabled
     */
    public function test_task_completion_rewards_when_feature_disabled(): void
    {
        // Disable feature globally
        Config::set('features.ui.enable_rewards', false);

        $taskData = [
            'task_id' => 'test-task-123',
            'task_title' => 'Complete test task',
            'completion_time' => now()->toISOString()
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/app/rewards/trigger-task-completion', $taskData);

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
     * Test rewards messages endpoint
     */
    public function test_rewards_messages_endpoint(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/app/rewards/messages?locale=en');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'locale',
                    'messages' => [
                        'congrats_message',
                        'celebration_title',
                        'task_completed',
                        'keep_it_up'
                    ]
                ]
            ]);

        $this->assertEquals('en', $response->json('data.locale'));
        $this->assertStringContains('Great job!', $response->json('data.messages.congrats_message'));
        $this->assertStringContains('Congratulations!', $response->json('data.messages.celebration_title'));
    }

    /**
     * Test rewards messages endpoint with Vietnamese locale
     */
    public function test_rewards_messages_endpoint_vietnamese(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/app/rewards/messages?locale=vi');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'locale',
                    'messages' => [
                        'congrats_message',
                        'celebration_title',
                        'task_completed',
                        'keep_it_up'
                    ]
                ]
            ]);

        $this->assertEquals('vi', $response->json('data.locale'));
        $this->assertStringContains('Xuất sắc!', $response->json('data.messages.congrats_message'));
        $this->assertStringContains('Chúc mừng!', $response->json('data.messages.celebration_title'));
    }

    /**
     * Test rewards messages endpoint with invalid locale
     */
    public function test_rewards_messages_endpoint_invalid_locale(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/app/rewards/messages?locale=invalid');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'locale',
                    'messages'
                ]
            ]);

        // Should fallback to English
        $this->assertEquals('invalid', $response->json('data.locale'));
        $this->assertStringContains('Great job!', $response->json('data.messages.congrats_message'));
    }

    /**
     * Test rewards toggle persistence
     */
    public function test_rewards_toggle_persistence(): void
    {
        // First toggle - should disable (default is true)
        $response1 = $this->actingAs($this->user)
            ->postJson('/api/v1/app/rewards/toggle');

        $this->assertFalse($response1->json('data.rewards_enabled'));

        // Second toggle - should enable
        $response2 = $this->actingAs($this->user)
            ->postJson('/api/v1/app/rewards/toggle');

        $this->assertTrue($response2->json('data.rewards_enabled'));

        // Verify database persistence
        $preference = UserPreference::where('user_id', $this->user->id)->first();
        $this->assertNotNull($preference);
        $this->assertTrue($preference->isRewardsEnabled());
    }

    /**
     * Test rewards with unauthenticated user
     */
    public function test_rewards_with_unauthenticated_user(): void
    {
        $response = $this->postJson('/api/v1/app/rewards/toggle');

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
     * Test task completion validation
     */
    public function test_task_completion_validation(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/app/rewards/trigger-task-completion', [
                'task_id' => '', // Invalid empty task_id
                'task_title' => 'Complete test task'
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
     * Test reward data generation
     */
    public function test_reward_data_generation(): void
    {
        $taskData = [
            'task_id' => 'test-task-123',
            'task_title' => 'Complete test task',
            'completion_time' => '2025-10-06T10:30:00Z'
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/app/rewards/trigger-task-completion', $taskData);

        $rewardData = $response->json('data.reward_data');

        $this->assertEquals('test-task-123', $rewardData['task_id']);
        $this->assertEquals('Complete test task', $rewardData['task_title']);
        $this->assertEquals('2025-10-06T10:30:00Z', $rewardData['completion_time']);
        $this->assertEquals('confetti', $rewardData['animation_type']);
        $this->assertEquals(4000, $rewardData['duration']);
        $this->assertIsArray($rewardData['messages']);
        $this->assertIsArray($rewardData['config']);
        $this->assertIsArray($rewardData['config']['colors']);
        $this->assertGreaterThan(0, $rewardData['config']['particle_count']);
    }
}
