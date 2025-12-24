<?php declare(strict_types=1);

namespace Src\Foundation\Listeners;

use Src\Foundation\Helpers\AuthHelper;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Listener để lưu trữ tất cả events vào bảng event_logs
 * Subscribe tới tất cả events (*) để audit và debug
 */
class EventLogListener
{
    /**
     * Xử lý event và lưu vào database
     *
     * @param string $eventName Tên event
     * @param array $data Dữ liệu event
     */
    public function handle(string $eventName, array $data): void
    {
        if (!$this->isEventLogTableReady()) {
            return;
        }

        try {
            // Chỉ log các events của hệ thống, bỏ qua Laravel internal events
            if ($this->shouldLogEvent($eventName)) {
                $event = $data[0] ?? null;
                
                if ($event && method_exists($event, 'getEventName')) {
                    $this->logCustomEvent($event);
                } else {
                    $this->logGenericEvent($eventName, $data);
                }
            }
        } catch (\Throwable $e) {
            $this->handleLoggingFailure($eventName, $e);
        }
    }

    /**
     * Log custom event với payload đầy đủ
     */
    private function logCustomEvent($event): void
    {
        $payload = method_exists($event, 'getPayload') ? $event->getPayload() : [];
        
        DB::table('event_logs')->insert([
            'event_name' => $event->getEventName(),
            'event_class' => get_class($event),
            'entity_id' => $payload['component_id'] ?? $payload['project_id'] ?? $payload['task_id'] ?? null,
            'project_id' => $payload['project_id'] ?? null,
            'actor_id' => $payload['actor_id'] ?? null,
            'tenant_id' => $payload['tenant_id'] ?? null,
            'payload' => json_encode($payload),
            'changed_fields' => isset($payload['changed_fields']) ? json_encode($payload['changed_fields']) : null,
            'source_module' => $this->extractModuleFromClass(get_class($event)),
            'severity' => $this->determineSeverity($event->getEventName()),
            'event_timestamp' => $payload['timestamp'] ?? now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Log generic Laravel event
     */
    private function logGenericEvent($eventName, $payload)
    {
        // Xử lý an toàn auth() helper trong môi trường test
        $actorId = 'system'; // Giá trị mặc định
        
        try {
            if (AuthHelper::check()) {
                $actorId = AuthHelper::id();
            }
        } catch (\Throwable $e) {
            // Giữ giá trị mặc định 'system' nếu auth() không hoạt động
        }
        
        // Loại bỏ dòng lỗi: $event->actor_id = $actorId ?? 'system';
        
        DB::table('event_logs')->insert([
            'event_name' => $eventName,
            'payload' => json_encode($payload),
            'actor_id' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Kiểm tra có nên log event này không
     */
    private function shouldLogEvent(string $eventName): bool
    {
        // Bỏ qua các Laravel internal events
        $skipEvents = [
            'Illuminate\\',
            'Laravel\\',
            'eloquent.retrieved',
            'eloquent.creating',
            'eloquent.created',
            'eloquent.updating',
            'eloquent.updated',
            'eloquent.saving',
            'eloquent.saved',
            'eloquent.deleting',
            'eloquent.deleted'
        ];

        foreach ($skipEvents as $skip) {
            if (str_contains($eventName, $skip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Trích xuất module name từ class name
     */
    private function extractModuleFromClass(string $className): string
    {
        if (str_contains($className, '\\CoreProject\\')) {
            return 'CoreProject';
        } elseif (str_contains($className, '\\RBAC\\')) {
            return 'RBAC';
        } elseif (str_contains($className, '\\ChangeRequest\\')) {
            return 'ChangeRequest';
        } elseif (str_contains($className, '\\DocumentManagement\\')) {
            return 'DocumentManagement';
        } elseif (str_contains($className, '\\Notification\\')) {
            return 'Notification';
        } elseif (str_contains($className, '\\InteractionLogs\\')) {
            return 'InteractionLogs';
        }
        
        return 'Unknown';
    }

    /**
     * Xác định mức độ nghiêm trọng của event
     */
    private function determineSeverity(string $eventName): string
    {
        if (str_contains($eventName, 'Error') || str_contains($eventName, 'Failed')) {
            return 'error';
        } elseif (str_contains($eventName, 'Warning') || str_contains($eventName, 'Rejected')) {
            return 'warning';
        } elseif (str_contains($eventName, 'Critical') || str_contains($eventName, 'Deleted')) {
            return 'critical';
        }
        
        return 'info';
    }

    /**
     * Kiểm tra bảng log sự kiện có sẵn hay không
     */
    private function isEventLogTableReady(): bool
    {
        try {
            return Schema::hasTable('event_logs');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Xử lý lỗi logging mà không phá vỡ ứng dụng
     */
    private function handleLoggingFailure(string $eventName, \Throwable $exception): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        Log::error('Failed to log event', [
            'event_name' => $eventName,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
