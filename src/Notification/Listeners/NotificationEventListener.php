<?php declare(strict_types=1);

namespace Src\Notification\Listeners;

use Illuminate\Events\Dispatcher;
use Src\Foundation\Events\EventBus;
use Src\Notification\Services\NotificationService;
use Src\Notification\Services\NotificationRuleService;
use Src\Notification\Models\NotificationRule;

/**
 * Event Listener cho notification rules engine
 * Xử lý các sự kiện business và trigger notifications theo rules đã định nghĩa
 */
class NotificationEventListener
{
    private NotificationService $notificationService;
    private NotificationRuleService $ruleService;

    public function __construct(
        NotificationService $notificationService,
        NotificationRuleService $ruleService
    ) {
        $this->notificationService = $notificationService;
        $this->ruleService = $ruleService;
    }

    /**
     * Đăng ký các event listeners
     *
     * @param Dispatcher $events
     * @return void
     */
    public function subscribe(Dispatcher $events): void
    {
        // Lắng nghe các sự kiện từ Project module
        $events->listen(
            'Project.Task.Completed',
            [NotificationEventListener::class, 'handleTaskCompleted']
        );

        $events->listen(
            'Project.Task.Ready',
            [NotificationEventListener::class, 'handleTaskReady']
        );

        $events->listen(
            'Project.Project.ProgressUpdated',
            [NotificationEventListener::class, 'handleProjectProgressUpdated']
        );

        $events->listen(
            'Project.Component.ProgressUpdated',
            [NotificationEventListener::class, 'handleComponentProgressUpdated']
        );

        // Lắng nghe các sự kiện từ ChangeRequest module
        $events->listen(
            'ChangeRequest.ChangeRequest.Approved',
            [NotificationEventListener::class, 'handleChangeRequestApproved']
        );

        $events->listen(
            'ChangeRequest.ChangeRequest.Rejected',
            [NotificationEventListener::class, 'handleChangeRequestRejected']
        );

        $events->listen(
            'ChangeRequest.ChangeRequest.Submitted',
            [NotificationEventListener::class, 'handleChangeRequestSubmitted']
        );

        // Lắng nghe các sự kiện từ Document module
        $events->listen(
            'Document.Document.Approved',
            [NotificationEventListener::class, 'handleDocumentApproved']
        );

        $events->listen(
            'Document.Document.Created',
            [NotificationEventListener::class, 'handleDocumentCreated']
        );
    }

    /**
     * Xử lý sự kiện Task Completed
     *
     * @param array $payload
     * @return void
     */
    public function handleTaskCompleted(array $payload): void
    {
        $this->processNotificationRules('Project.Task.Completed', $payload);
    }

    /**
     * Xử lý sự kiện Task Ready
     *
     * @param array $payload
     * @return void
     */
    public function handleTaskReady(array $payload): void
    {
        $this->processNotificationRules('Project.Task.Ready', $payload);
    }

    /**
     * Xử lý sự kiện Project Progress Updated
     *
     * @param array $payload
     * @return void
     */
    public function handleProjectProgressUpdated(array $payload): void
    {
        $this->processNotificationRules('Project.Project.ProgressUpdated', $payload);
    }

    /**
     * Xử lý sự kiện Component Progress Updated
     *
     * @param array $payload
     * @return void
     */
    public function handleComponentProgressUpdated(array $payload): void
    {
        $this->processNotificationRules('Project.Component.ProgressUpdated', $payload);
    }

    /**
     * Xử lý sự kiện Change Request Approved
     *
     * @param array $payload
     * @return void
     */
    public function handleChangeRequestApproved(array $payload): void
    {
        $this->processNotificationRules('ChangeRequest.ChangeRequest.Approved', $payload);
    }

    /**
     * Xử lý sự kiện Change Request Rejected
     *
     * @param array $payload
     * @return void
     */
    public function handleChangeRequestRejected(array $payload): void
    {
        $this->processNotificationRules('ChangeRequest.ChangeRequest.Rejected', $payload);
    }

    /**
     * Xử lý sự kiện Change Request Submitted
     *
     * @param array $payload
     * @return void
     */
    public function handleChangeRequestSubmitted(array $payload): void
    {
        $this->processNotificationRules('ChangeRequest.ChangeRequest.Submitted', $payload);
    }

    /**
     * Xử lý sự kiện Document Approved
     *
     * @param array $payload
     * @return void
     */
    public function handleDocumentApproved(array $payload): void
    {
        $this->processNotificationRules('Document.Document.Approved', $payload);
    }

    /**
     * Xử lý sự kiện Document Created
     *
     * @param array $payload
     * @return void
     */
    public function handleDocumentCreated(array $payload): void
    {
        $this->processNotificationRules('Document.Document.Created', $payload);
    }

    /**
     * Xử lý notification rules cho một sự kiện
     *
     * @param string $eventKey
     * @param array $payload
     * @return void
     */
    private function processNotificationRules(string $eventKey, array $payload): void
    {
        try {
            // Tìm tất cả notification rules cho event này
            $rules = NotificationRule::where('event_key', $eventKey)
                ->where('is_enabled', true)
                ->get();

            foreach ($rules as $rule) {
                // Kiểm tra project scope nếu rule có project_id
                if ($rule->project_id && $rule->project_id !== ($payload['projectId'] ?? null)) {
                    continue;
                }

                // Đánh giá conditions nếu có
                if ($rule->conditions && !$this->evaluateConditions($rule->conditions, $payload)) {
                    continue;
                }

                // Tạo notification cho từng channel
                foreach ($rule->channels as $channel) {
                    $this->createNotificationForRule($rule, $channel, $payload, $eventKey);
                }
            }

            Log::info('Processed notification rules', [
                'event_key' => $eventKey,
                'rules_count' => $rules->count(),
                'payload' => $payload
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing notification rules', [
                'event_key' => $eventKey,
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);
        }
    }

    /**
     * Tạo notification cho một rule
     *
     * @param NotificationRule $rule
     * @param string $channel
     * @param array $payload
     * @param string $eventKey
     * @return void
     */
    private function createNotificationForRule(
        NotificationRule $rule,
        string $channel,
        array $payload,
        string $eventKey
    ): void {
        // Tạo title và body dựa trên event type
        $notificationData = $this->generateNotificationContent($eventKey, $payload);

        $this->notificationService->createNotification([
            'user_id' => $rule->user_id,
            'project_id' => $payload['projectId'] ?? null,
            'priority' => $this->determinePriority($rule, $payload),
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'link_url' => $notificationData['link_url'] ?? null,
            'channel' => $channel,
            'event_key' => $eventKey,
            'metadata' => [
                'rule_id' => $rule->ulid,
                'original_payload' => $payload,
                'generated_at' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Đánh giá conditions của rule
     *
     * @param array $conditions
     * @param array $payload
     * @return bool
     */
    private function evaluateConditions(array $conditions, array $payload): bool
    {
        // Implement logic đánh giá conditions
        // Ví dụ: conditions có thể chứa các điều kiện như:
        // - priority >= 'high'
        // - project_id in [list]
        // - actor_id != user_id (không tự notify)
        
        foreach ($conditions as $condition) {
            if (!$this->evaluateSingleCondition($condition, $payload)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Đánh giá một condition đơn lẻ
     *
     * @param array $condition
     * @param array $payload
     * @return bool
     */
    private function evaluateSingleCondition(array $condition, array $payload): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? null;

        if (!$field || !isset($payload[$field])) {
            return false;
        }

        $payloadValue = $payload[$field];

        switch ($operator) {
            case '=':
            case '==':
                return $payloadValue == $value;
            case '!=':
                return $payloadValue != $value;
            case '>':
                return $payloadValue > $value;
            case '>=':
                return $payloadValue >= $value;
            case '<':
                return $payloadValue < $value;
            case '<=':
                return $payloadValue <= $value;
            case 'in':
                return in_array($payloadValue, (array) $value);
            case 'not_in':
                return !in_array($payloadValue, (array) $value);
            case 'contains':
                return str_contains((string) $payloadValue, (string) $value);
            default:
                return false;
        }
    }

    /**
     * Xác định priority cho notification
     *
     * @param NotificationRule $rule
     * @param array $payload
     * @return string
     */
    private function determinePriority(NotificationRule $rule, array $payload): string
    {
        // Sử dụng priority từ payload nếu có và >= min_priority của rule
        $payloadPriority = $payload['priority'] ?? 'normal';
        $minPriority = $rule->min_priority;

        $priorityLevels = ['low' => 1, 'normal' => 2, 'critical' => 3];

        if (($priorityLevels[$payloadPriority] ?? 2) >= ($priorityLevels[$minPriority] ?? 2)) {
            return $payloadPriority;
        }

        return $minPriority;
    }

    /**
     * Tạo nội dung notification dựa trên event type
     *
     * @param string $eventKey
     * @param array $payload
     * @return array
     */
    private function generateNotificationContent(string $eventKey, array $payload): array
    {
        $entityId = $payload['entityId'] ?? 'N/A';
        $projectId = $payload['projectId'] ?? null;
        $actorId = $payload['actorId'] ?? 'System';

        switch ($eventKey) {
            case 'Project.Task.Completed':
                return [
                    'title' => 'Task đã hoàn thành',
                    'body' => "Task {$entityId} đã được hoàn thành bởi {$actorId}",
                    'link_url' => $projectId ? "/projects/{$projectId}/tasks/{$entityId}" : null
                ];

            case 'Project.Task.Ready':
                return [
                    'title' => 'Task sẵn sàng thực hiện',
                    'body' => "Task {$entityId} đã sẵn sàng để bắt đầu",
                    'link_url' => $projectId ? "/projects/{$projectId}/tasks/{$entityId}" : null
                ];

            case 'Project.Project.ProgressUpdated':
                $progress = $payload['progress'] ?? 'N/A';
                return [
                    'title' => 'Tiến độ dự án cập nhật',
                    'body' => "Dự án {$entityId} đã cập nhật tiến độ: {$progress}%",
                    'link_url' => "/projects/{$entityId}"
                ];

            case 'ChangeRequest.ChangeRequest.Approved':
                return [
                    'title' => 'Change Request được phê duyệt',
                    'body' => "Change Request {$entityId} đã được phê duyệt bởi {$actorId}",
                    'link_url' => $projectId ? "/projects/{$projectId}/change-requests/{$entityId}" : null
                ];

            case 'ChangeRequest.ChangeRequest.Rejected':
                return [
                    'title' => 'Change Request bị từ chối',
                    'body' => "Change Request {$entityId} đã bị từ chối bởi {$actorId}",
                    'link_url' => $projectId ? "/projects/{$projectId}/change-requests/{$entityId}" : null
                ];

            case 'Document.Document.Approved':
                return [
                    'title' => 'Tài liệu được phê duyệt',
                    'body' => "Tài liệu {$entityId} đã được phê duyệt cho client",
                    'link_url' => $projectId ? "/projects/{$projectId}/documents/{$entityId}" : null
                ];

            default:
                return [
                    'title' => 'Thông báo hệ thống',
                    'body' => "Sự kiện {$eventKey} đã xảy ra cho {$entityId}",
                    'link_url' => null
                ];
        }
    }
}