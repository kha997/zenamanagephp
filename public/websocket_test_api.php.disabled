<?php declare(strict_types=1);

require_once '../bootstrap.php';

use Src\Notification\Events\NotificationBroadcast;
use Src\Notification\Events\ProjectNotificationBroadcast;
use Illuminate\Support\Facades\Event;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_GET['action'] ?? 'user';

try {
    switch ($action) {
        case 'user':
            $userId = 1;
            $notificationData = [
                'id' => 'test-' . uniqid(),
                'title' => 'Test User Notification',
                'body' => 'This is a test notification from WebSocket API',
                'priority' => 'normal',
                'link_url' => '/dashboard',
                'created_at' => now()->toISOString()
            ];
            
            Event::dispatch(new NotificationBroadcast($userId, $notificationData));
            echo json_encode(['status' => 'success', 'message' => 'User notification sent']);
            break;
            
        case 'project':
            $projectId = 1;
            $userIds = [1, 2, 3];
            $notificationData = [
                'id' => 'project-test-' . uniqid(),
                'title' => 'Project Update Test',
                'body' => 'Project has been updated via API test',
                'priority' => 'high',
                'project_id' => $projectId,
                'created_at' => now()->toISOString()
            ];
            
            Event::dispatch(new ProjectNotificationBroadcast($projectId, $userIds, $notificationData));
            echo json_encode(['status' => 'success', 'message' => 'Project notification sent']);
            break;
            
        case 'string':
            $userId = "123"; // String userId for testing
            $notificationData = [
                'id' => 'string-test-' . uniqid(),
                'title' => 'String UserId Test',
                'body' => 'Testing string userId casting to int',
                'priority' => 'low'
            ];
            
            Event::dispatch(new NotificationBroadcast($userId, $notificationData));
            echo json_encode(['status' => 'success', 'message' => 'String userId test sent']);
            break;
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}