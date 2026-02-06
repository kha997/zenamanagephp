<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;

class WebSocketTest extends TestCase
{
    use RefreshDatabase, WithFaker, AuthenticationTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->apiActingAsTenantAdmin();
        Cache::flush();
    }

    /**
     * Test WebSocket connection info endpoint
     */
    public function test_websocket_connection_info()
    {
        $response = $this->apiGet('/api/websocket/info');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'websocket_url',
                'channels',
                'event_types',
                'online_users',
                'connection_id'
            ]
        ]);

        $data = $response->json('data');
        $this->assertTrue($response->json('success'));
        $this->assertIsString($data['websocket_url']);
        $this->assertIsArray($data['channels']);
        $this->assertIsArray($data['event_types']);
        $this->assertIsInt($data['online_users']);
        $this->assertIsString($data['connection_id']);
    }

    /**
     * Test WebSocket stats endpoint
     */
    public function test_websocket_stats()
    {
        $response = $this->apiGet('/api/websocket/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'total_connections',
                'active_connections',
                'total_messages_sent',
                'total_messages_received',
                'uptime',
                'cpu_usage',
                'online_users',
                'channels',
                'event_types',
                'redis_connected'
            ]
        ]);

        $data = $response->json('data');
        $this->assertTrue($response->json('success'));
        $this->assertIsInt($data['total_connections']);
        $this->assertIsInt($data['active_connections']);
        $this->assertIsInt($data['total_messages_sent']);
        $this->assertIsInt($data['total_messages_received']);
        $this->assertIsInt($data['uptime']);
        $this->assertIsNumeric($data['cpu_usage']);
        $this->assertIsInt($data['online_users']);
        $this->assertIsArray($data['channels']);
        $this->assertIsArray($data['event_types']);
        $this->assertIsBool($data['redis_connected']);
    }

    /**
     * Test WebSocket channels endpoint
     */
    public function test_websocket_channels()
    {
        $response = $this->apiGet('/api/websocket/channels');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data'
        ]);

        $data = $response->json('data');
        $this->assertTrue($response->json('success'));
        $this->assertIsArray($data);

        foreach ($data as $channelKey => $channelInfo) {
            $this->assertArrayHasKey('name', $channelInfo);
            $this->assertArrayHasKey('events', $channelInfo);
            $this->assertIsString($channelInfo['name']);
            $this->assertIsArray($channelInfo['events']);
        }
    }

    /**
     * Test WebSocket connection test endpoint
     */
    public function test_websocket_connection_test()
    {
        $response = $this->apiGet('/api/websocket/test');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'connection_status',
                'response_time',
                'server_info',
                'test_timestamp'
            ]
        ]);

        $data = $response->json('data');
        $this->assertIsString($data['connection_status']);
        $this->assertIsFloat($data['response_time']);
        $this->assertIsArray($data['server_info']);
        $this->assertIsString($data['test_timestamp']);
    }

    /**
     * Test marking user online
     */
    public function test_mark_user_online()
    {
        $response = $this->apiPost('/api/websocket/online', [
            'user_id' => $this->apiFeatureUser->id,
            'connection_id' => 'test_connection_' . uniqid(),
            'metadata' => [
                'browser' => 'Chrome',
                'os' => 'macOS',
                'ip_address' => '127.0.0.1'
            ]
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'user_id'
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals((string) $this->apiFeatureUser->id, (string) $response->json('user_id'));
        $this->assertIsString($response->json('message'));
    }

    /**
     * Test marking user offline
     */
    public function test_mark_user_offline()
    {
        $connectionId = 'test_connection_' . uniqid();

        // First mark user online
        $this->apiPost('/api/websocket/online', [
            'user_id' => $this->apiFeatureUser->id,
            'connection_id' => $connectionId
        ]);

        // Then mark user offline
        $response = $this->apiPost('/api/websocket/offline', [
            'user_id' => $this->apiFeatureUser->id,
            'connection_id' => $connectionId,
            'reason' => 'user_disconnect'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'user_id'
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals((string) $this->apiFeatureUser->id, (string) $response->json('user_id'));
        $this->assertIsString($response->json('message'));
    }

    /**
     * Test updating user activity
     */
    public function test_update_user_activity()
    {
        $response = $this->apiPost('/api/websocket/activity', [
            'user_id' => $this->apiFeatureUser->id,
            'activity_type' => 'page_view',
            'activity_data' => [
                'page' => '/dashboard',
                'duration' => 30
            ]
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'user_id',
            'activity'
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals((string) $this->apiFeatureUser->id, (string) $response->json('user_id'));
        $this->assertEquals('page_view', $response->json('activity'));
        $this->assertIsString($response->json('message'));
    }

    /**
     * Test broadcasting message
     */
    public function test_broadcast_message()
    {
        $response = $this->apiPost('/api/websocket/broadcast', [
            'channel' => 'notifications',
            'event' => 'system_notification',
            'data' => [
                'title' => 'Test Notification',
                'message' => 'This is a test notification',
                'type' => 'info'
            ],
            'target_users' => [$this->apiFeatureUser->id]
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
        $this->assertEquals('system_notification', $response->json('event'));
        $this->assertIsString($response->json('message'));
    }

    /**
     * Test sending notification
     */
    public function test_send_notification()
    {
        $response = $this->apiPost('/api/websocket/notification', [
            'user_id' => $this->apiFeatureUser->id,
            'type' => 'task_assigned',
            'title' => 'New Task Assigned',
            'message' => 'You have been assigned a new task',
            'data' => [
                'task_id' => 123,
                'project_id' => 456,
                'due_date' => '2024-01-15'
            ],
            'priority' => 'normal'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'user_id'
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals((string) $this->apiFeatureUser->id, (string) $response->json('user_id'));
        $this->assertIsString($response->json('message'));
    }

    /**
     * Test WebSocket authentication
     */
    public function test_websocket_authentication()
    {
        // Test without authentication
        $response = $this->withHeaders($this->tenantHeaders())->getJson('/api/websocket/info');
        $response->assertStatus(401);

        // Test with invalid token
        $response = $this->withHeaders(array_merge($this->apiHeaders, [
            'Authorization' => 'Bearer invalid_token',
        ]))->getJson('/api/websocket/info');
        $response->assertStatus(401);

        // Test with valid token
        $response = $this->apiGet('/api/websocket/info');
        $response->assertStatus(200);
    }

    /**
     * Test WebSocket error handling
     */
    public function test_websocket_error_handling()
    {
        // Test invalid user ID
        $response = $this->apiPost('/api/websocket/online', [
            'user_id' => '99999',
            'connection_id' => 'test_connection'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'user_id'
        ]);
        $this->assertTrue($response->json('success'));
        $this->assertEquals('99999', (string) $response->json('user_id'));

        // Test missing required fields
        $response = $this->apiPost('/api/websocket/broadcast', [
            'channel' => 'notifications'
            // Missing event and data
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'errors' => [
                'event',
                'data'
            ]
        ]);
        $this->assertIsString($response->json('message'));
        $errors = $response->json('errors');
        $this->assertArrayHasKey('event', $errors);
        $this->assertArrayHasKey('data', $errors);
    }

    /**
     * Test WebSocket performance metrics
     */
    public function test_websocket_performance_metrics()
    {
        $response = $this->apiGet('/api/websocket/stats');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Validate performance metrics
        $this->assertGreaterThanOrEqual(0, $data['total_connections']);
        $this->assertGreaterThanOrEqual(0, $data['active_connections']);
        $this->assertGreaterThanOrEqual(0, $data['total_messages_sent']);
        $this->assertGreaterThanOrEqual(0, $data['total_messages_received']);
        $this->assertGreaterThanOrEqual(0, $data['uptime']);
        $this->assertGreaterThanOrEqual(0, $data['cpu_usage']);
        $this->assertLessThanOrEqual(100, $data['cpu_usage']);
    }
}
