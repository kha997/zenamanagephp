<?php declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Services\WebSocketClient;
use Src\Notification\Events\NotificationBroadcast;

// Test WebSocket client trực tiếp
echo "Testing WebSocket Client...\n";

$wsClient = new WebSocketClient();

// Test gửi notification đến user
$userNotification = [
    'title' => 'Test User Notification',
    'body' => 'This is a test notification for user',
    'priority' => 'normal',
    'link_url' => '/dashboard'
];

$result = $wsClient->sendToUser(1, $userNotification);
echo "Send to user result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";

// Test gửi notification đến project
$projectNotification = [
    'title' => 'Test Project Notification',
    'body' => 'This is a test notification for project',
    'priority' => 'high',
    'link_url' => '/projects/1'
];

$result = $wsClient->sendToProject(1, $projectNotification);
echo "Send to project result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";

// Test Laravel Event
echo "\nTesting Laravel Event...\n";
try {
    event(new NotificationBroadcast(1, [
        'title' => 'Laravel Event Test',
        'body' => 'This notification was sent via Laravel Event',
        'priority' => 'critical'
    ], 1));
    echo "Laravel event dispatched successfully\n";
} catch (Exception $e) {
    echo "Laravel event failed: " . $e->getMessage() . "\n";
}

echo "\nTest completed!\n";