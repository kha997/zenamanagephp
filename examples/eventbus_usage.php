<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Foundation/Foundation.php';
require_once __DIR__ . '/../src/Foundation/EventBus.php';
require_once __DIR__ . '/../src/Foundation/Events/BaseEvent.php';
require_once __DIR__ . '/../src/Foundation/Events/ProjectEvents.php';
require_once __DIR__ . '/../src/Foundation/Listeners/AuditListener.php';

use zenamanage\Foundation\EventBus;
use zenamanage\Foundation\Events\ComponentProgressUpdated;
use zenamanage\Foundation\Events\ChangeRequestApproved;
use zenamanage\Foundation\Listeners\AuditListener;

// Đăng ký audit listener cho tất cả sự kiện
$auditListener = new AuditListener();
EventBus::subscribe('*', [$auditListener, 'handle']);

// Đăng ký listener cho sự kiện cụ thể
EventBus::subscribe('Project.Component.ProgressUpdated', function($payload) {
    echo "Component {$payload['entityId']} progress updated in project {$payload['projectId']}\n";
    
    // Logic tính toán lại progress của project
    // TODO: Implement project progress recalculation
});

EventBus::subscribe('ChangeRequest.ChangeRequest.Approved', function($payload) {
    echo "Change Request {$payload['entityId']} approved with impact: " . 
         json_encode($payload['impactData']) . "\n";
    
    // Logic áp dụng thay đổi vào schedule và cost
    // TODO: Implement change request impact application
});

// Ví dụ phát sự kiện
echo "=== Event Bus Usage Example ===\n\n";

// Tạo và phát sự kiện ComponentProgressUpdated
$componentEvent = new ComponentProgressUpdated(
    'component_123',
    'project_456',
    'user_789',
    ['progress_percent' => ['old' => 50, 'new' => 75]]
);

echo "Firing ComponentProgressUpdated event...\n";
$results = $componentEvent->fire();
echo "Event fired with " . count($results) . " listeners processed\n\n";

// Tạo và phát sự kiện ChangeRequestApproved
$crEvent = new ChangeRequestApproved(
    'cr_456',
    'project_456',
    'manager_123',
    [
        'impact_days' => 5,
        'impact_cost' => 10000,
        'impact_kpi' => ['quality' => '+10%']
    ],
    ['status' => ['old' => 'awaiting_approval', 'new' => 'approved']]
);

echo "Firing ChangeRequestApproved event...\n";
$results = $crEvent->fire();
echo "Event fired with " . count($results) . " listeners processed\n\n";

// Hiển thị tất cả listeners đã đăng ký
echo "=== Registered Listeners ===\n";
foreach (EventBus::getListeners() as $eventName => $listeners) {
    echo "Event: {$eventName} - Listeners: " . count($listeners) . "\n";
}