<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WebSocketTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * Test WebSocket connection info endpoint
     */
    public function test_websocket_connection_info()
    {
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/websocket/info', [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'websocket_url',
                'channels',
                'event_types',
                'online_users',
                'connection_id'
            ],
            'timestamp'
        ]);

        $data = $response->json('data');
        $this->assertIsString($data['websocket_url']);
        $this->assertIsArray($data['channels']);
        $this->assertArrayHasKey('dashboard', $data['channels']);
        $this->assertIsArray($data['event_types']);
        $this->assertIsInt($data['online_users']);
        $this->assertIsString($data['connection_id']);
        $this->assertIsString($response->json('timestamp'));
    }

    /**
     * Test WebSocket stats endpoint
     */
    public function test_websocket_stats()
    {
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/websocket/stats', [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'online_users',
                'total_connections',
                'active_connections',
                'total_messages_sent',
                'total_messages_received',
                'channels',
                'event_types',
                'redis_connected'
            ],
            'timestamp'
        ]);

        $data = $response->json('data');
        $this->assertIsInt($data['online_users']);
        $this->assertIsInt($data['total_connections']);
        $this->assertIsInt($data['active_connections']);
        $this->assertIsInt($data['total_messages_sent']);
        $this->assertIsInt($data['total_messages_received']);
        $this->assertIsArray($data['channels']);
        $this->assertIsArray($data['event_types']);
        $this->assertIsBool($data['redis_connected']);
        $this->assertIsString($response->json('timestamp'));
    }

    /**
     * Test WebSocket channels endpoint
     */
    public function test_websocket_channels()
    {
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/websocket/channels', [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'name',
                    'events'
                ]
            ]
        ]);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('dashboard', $data);
        foreach ($data as $channelInfo) {
            $this->assertIsString($channelInfo['name']);
            $this->assertIsArray($channelInfo['events']);
        }
    }

    /**
     * Test WebSocket connection test endpoint
     */
    public function test_websocket_connection_test()
    {
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/websocket/test', [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'test_data' => [
                'message',
                'timestamp',
                'test_id'
            ]
        ]);

        $this->assertEquals('WebSocket connection test successful', $response->json('message'));
        $testData = $response->json('test_data');
        $this->assertIsArray($testData);
        $this->assertIsString($testData['message']);
        $this->assertIsInt($testData['timestamp']);
        $this->assertIsString($testData['test_id']);
    }

    /**
     * Test marking user online
     */
    public function test_mark_user_online()
    {
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->postJson('/api/websocket/online', [
            'user_id' => $user->id,
            'connection_id' => 'test_connection_' . uniqid(),
            'metadata' => [
                'browser' => 'Chrome',
                'os' => 'macOS',
                'ip_address' => '127.0.0.1'
            ]
        ], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'user_id'
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals($user->id, $response->json('user_id'));
        $this->assertIsString($response->json('message'));
    }

    /**
     * Test marking user offline
     */
    public function test_mark_user_offline()
    {
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $connectionId = 'test_connection_' . uniqid();

        // First mark user online
        $this->postJson('/api/websocket/online', [
            'user_id' => $user->id,
            'connection_id' => $connectionId
        ], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ]);

        // Then mark user offline
        $response = $this->postJson('/api/websocket/offline', [
            'user_id' => $user->id,
            'connection_id' => $connectionId,
            'reason' => 'user_disconnect'
        ], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'user_id'
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals($user->id, $response->json('user_id'));
        $this->assertIsString($response->json('message'));
    }

    /**
     * Test updating user activity
     */
    public function test_update_user_activity()
    {
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->postJson('/api/websocket/activity', [
            'user_id' => $user->id,
            'activity' => 'page_view',
            'metadata' => [
                'page' => '/dashboard',
                'duration' => 30
            ]
        ], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'user_id',
            'activity'
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals($user->id, $response->json('user_id'));
        $this->assertEquals('page_view', $response->json('activity'));
        $this->assertIsString($response->json('message'));
    }

    /**
     * Test broadcasting message
     */
    public function test_broadcast_message()
    {
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->postJson('/api/websocket/broadcast', [
            'channel' => 'notifications',
            'event' => 'new_notification',
            'data' => [
                'title' => 'Test Notification',
                'message' => 'This is a test notification',
                'type' => 'info'
            ],
            'target_users' => [$user->id]
        ], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'channel',
            'event'
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals('notifications', $response->json('channel'));
        $this->assertEquals('new_notification', $response->json('event'));
        $this->assertIsString($response->json('message'));
    }

    /**
     * Test sending notification
     */
    public function test_send_notification()
    {
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->postJson('/api/websocket/notification', [
            'user_id' => $user->id,
            'notification' => [
                'type' => 'task_assigned',
                'title' => 'New Task Assigned',
                'message' => 'You have been assigned a new task',
                'metadata' => [
                    'task_id' => 123,
                    'project_id' => 456,
                    'due_date' => '2024-01-15'
                ],
                'priority' => 'normal'
            ]
        ], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'user_id'
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals($user->id, $response->json('user_id'));
        $this->assertIsString($response->json('message'));
    }

    /**
     * Test WebSocket authentication
     */
    public function test_websocket_authentication()
    {
        // Test without authentication
        $response = $this->getJson('/api/websocket/info');
        $response->assertStatus(200);

        // Test with invalid token
        $response = $this->getJson('/api/websocket/info', [
            'Authorization' => 'Bearer invalid_token',
            'Accept' => 'application/json'
        ]);
        $response->assertStatus(200);

        // Test with valid token
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/websocket/info', [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ]);
        $response->assertStatus(200);
    }

    /**
     * Test WebSocket error handling
     */
    public function test_websocket_error_handling()
    {
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Test invalid user ID
        $response = $this->postJson('/api/websocket/online', [
            'user_id' => 99999,
            'connection_id' => 'test_connection'
        ], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'error' => [
                'id',
                'code',
                'message',
                'details'
            ]
        ]);
        $this->assertIsArray($response->json('error.details'));

        // Test missing required fields
        $response = $this->postJson('/api/websocket/broadcast', [
            'channel' => 'notifications'
            // Missing event and data
        ], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'error' => [
                'id',
                'code',
                'message',
                'details'
            ]
        ]);
        $this->assertIsArray($response->json('error.details'));
    }

    /**
     * Test WebSocket performance metrics
     */
    public function test_websocket_performance_metrics()
    {
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/websocket/stats', [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'online_users',
                'total_connections',
                'active_connections',
                'total_messages_sent',
                'total_messages_received',
                'channels',
                'event_types',
                'redis_connected'
            ],
            'timestamp'
        ]);

        $data = $response->json('data');

        $this->assertGreaterThanOrEqual(0, $data['online_users']);
        $this->assertGreaterThanOrEqual(0, $data['total_connections']);
        $this->assertGreaterThanOrEqual(0, $data['active_connections']);
        $this->assertGreaterThanOrEqual(0, $data['total_messages_sent']);
        $this->assertGreaterThanOrEqual(0, $data['total_messages_received']);
        $this->assertIsArray($data['channels']);
        $this->assertIsArray($data['event_types']);
        $this->assertArrayHasKey('dashboard', $data['event_types']);
        $this->assertIsBool($data['redis_connected']);
    }
}
