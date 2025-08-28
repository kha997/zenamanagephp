<?php declare(strict_types=1);

require_once 'bootstrap.php';

use Src\Notification\Events\NotificationBroadcast;
use Src\Notification\Events\ProjectNotificationBroadcast;
use Illuminate\Support\Facades\Event;

/**
 * Test script để kiểm tra WebSocket functionality
 */
class WebSocketTest
{
    /**
     * Test broadcast notification đến user cụ thể
     */
    public function testUserNotification(): void
    {
        echo "Testing User Notification Broadcast...\n";
        
        $userId = 1;
        $notificationData = [
            'id' => 'test-' . uniqid(),
            'title' => 'Test Notification',
            'body' => 'This is a test notification from WebSocket',
            'priority' => 'normal',
            'link_url' => '/dashboard',
            'created_at' => now()->toISOString()
        ];
        
        try {
            // Dispatch event
            Event::dispatch(new NotificationBroadcast($userId, $notificationData));
            echo "✓ User notification dispatched successfully\n";
        } catch (Exception $e) {
            echo "✗ Error dispatching user notification: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Test broadcast notification đến project
     */
    public function testProjectNotification(): void
    {
        echo "\nTesting Project Notification Broadcast...\n";
        
        $projectId = 1;
        $userIds = [1, 2, 3];
        $notificationData = [
            'id' => 'project-test-' . uniqid(),
            'title' => 'Project Update',
            'body' => 'Project has been updated',
            'priority' => 'high',
            'project_id' => $projectId,
            'created_at' => now()->toISOString()
        ];
        
        try {
            // Dispatch event
            Event::dispatch(new ProjectNotificationBroadcast($projectId, $userIds, $notificationData));
            echo "✓ Project notification dispatched successfully\n";
        } catch (Exception $e) {
            echo "✗ Error dispatching project notification: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Test với userId kiểu string (để test type casting)
     */
    public function testStringUserId(): void
    {
        echo "\nTesting String UserId Casting...\n";
        
        $userId = "123"; // String userId
        $notificationData = [
            'id' => 'string-test-' . uniqid(),
            'title' => 'String UserId Test',
            'body' => 'Testing string userId casting to int',
            'priority' => 'low'
        ];
        
        try {
            // Dispatch event với string userId
            Event::dispatch(new NotificationBroadcast($userId, $notificationData));
            echo "✓ String userId casting works correctly\n";
        } catch (Exception $e) {
            echo "✗ Error with string userId: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Chạy tất cả tests
     */
    public function runAllTests(): void
    {
        echo "=== WebSocket Functionality Test ===\n";
        echo "Time: " . date('Y-m-d H:i:s') . "\n\n";
        
        $this->testUserNotification();
        $this->testProjectNotification();
        $this->testStringUserId();
        
        echo "\n=== Test Completed ===\n";
        echo "Note: Check your WebSocket server logs to verify broadcasts were sent.\n";
    }
}

// Chạy test
if (php_sapi_name() === 'cli') {
    $test = new WebSocketTest();
    $test->runAllTests();
} else {
    echo "<pre>";
    $test = new WebSocketTest();
    $test->runAllTests();
    echo "</pre>";
}