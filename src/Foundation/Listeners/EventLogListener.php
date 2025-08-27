<?php declare(strict_types=1);

namespace Src\Foundation\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        } catch (\Exception $e) {
            // Không để lỗi logging làm crash ứng dụng
            Log::error('Failed to log event', [
                'event_name' => $eventName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
    private function logGenericEvent(string $eventName, array $data): void
    {
        DB::table('event_logs')->insert([
            'event_name' => $eventName,
            'event_class' => $eventName,
            'entity_id' => null,
            'project_id' => null,
            'actor_id' => auth()->id(),
            'tenant_id' => session('tenant_id'),
            'payload' => json_encode($data),
            'changed_fields' => null,
            'source_module' => 'System',
            'severity' => 'info',
            'event_timestamp' => now(),
            'created_at' => now(),
            'updated_at' => now()
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
}