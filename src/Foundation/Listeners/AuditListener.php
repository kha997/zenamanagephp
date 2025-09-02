<?php declare(strict_types=1);

namespace Src\Foundation\Listeners;

use Src\Foundation\Helpers\AuthHelper;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Src\Foundation\Events\BaseEvent;
use Throwable;

/**
 * Audit Listener - Ghi log tất cả các sự kiện vào database
 * Listener này đăng ký lắng nghe tất cả các sự kiện (*) để audit
 */
class AuditListener
{
    /**
     * Xử lý sự kiện audit
     *
     * @param mixed $event Sự kiện được dispatch
     * @return void
     */
    public function handle($event): void
    {
        try {
            // Chỉ xử lý các sự kiện kế thừa từ BaseEvent
            if (!$event instanceof BaseEvent) {
                return;
            }

            // Chuẩn bị dữ liệu để lưu vào event_logs
            $eventData = [
                'event_id' => $event->eventId,
                'event_name' => $event->getEventName(),
                'event_class' => get_class($event),
                'entity_id' => $event->entityId,
                'project_id' => $event->projectId,
                'actor_id' => $event->actorId,
                'tenant_id' => $this->getTenantId($event),
                'payload' => json_encode($this->sanitizePayload($event)),
                'changed_fields' => json_encode($event->changedFields ?? []),
                'source_module' => $this->extractModuleFromEventName($event->getEventName()),
                'severity' => $this->determineSeverity($event->getEventName()),
                'event_timestamp' => $event->timestamp,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Lưu vào database
            DB::table('event_logs')->insert($eventData);

            // Log debug thông tin (optional)
            Log::debug('Event audited', [
                'event_name' => $event->getEventName(),
                'entity_id' => $event->entityId,
                'project_id' => $event->projectId,
            ]);

        } catch (Throwable $e) {
            // Không để lỗi audit làm crash ứng dụng
            // Ghi log lỗi để debug
            Log::error('Failed to audit event', [
                'event_class' => get_class($event),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Fallback: ghi vào error_log nếu database fail
            error_log(sprintf(
                '[AUDIT_FALLBACK] Event: %s, Entity: %s, Project: %s, Error: %s',
                $event instanceof BaseEvent ? $event->getEventName() : get_class($event),
                $event instanceof BaseEvent ? $event->entityId : 'unknown',
                $event instanceof BaseEvent ? $event->projectId : 'unknown',
                $e->getMessage()
            ));
        }
    }

    /**
     * Get tenant ID safely from authenticated user or event payload
     *
     * @param mixed $event
     * @return string|null
     */
    private function getTenantId($event): ?string
    {
        try {
            // Try to get tenant_id from authenticated user first
            if (AuthHelper::check()) {
                $user = AuthHelper::user();
                if ($user && property_exists($user, 'tenant_id')) {
                    return $user->tenant_id;
                }
            }
        } catch (\Throwable $e) {
            // Log error in debug mode
            if (config('app.debug')) {
                \Log::warning('AuditListener: Unable to get tenant from auth', [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Fallback to event payload if available
        if (is_object($event) && property_exists($event, 'tenantId')) {
            return $event->tenantId;
        }
        
        if (is_array($event) && isset($event['tenantId'])) {
            return $event['tenantId'];
        }
        
        return null;
    }

    /**
     * Làm sạch payload để tránh lưu thông tin nhạy cảm
     *
     * @param BaseEvent $event
     * @return array
     */
    private function sanitizePayload(BaseEvent $event): array
    {
        $payload = [
            'entityId' => $event->entityId,
            'projectId' => $event->projectId,
            'actorId' => $event->actorId,
            'timestamp' => $event->timestamp,
            'eventId' => $event->eventId,
        ];

        // Thêm các thuộc tính khác của event (trừ những field nhạy cảm)
        $reflection = new \ReflectionClass($event);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        
        $sensitiveFields = ['password', 'token', 'secret', 'key', 'credential'];
        
        foreach ($properties as $property) {
            $propertyName = $property->getName();
            
            // Bỏ qua các field đã có trong payload
            if (in_array($propertyName, ['entityId', 'projectId', 'actorId', 'timestamp', 'eventId'])) {
                continue;
            }
            
            // Bỏ qua các field nhạy cảm
            $isSenitiveField = false;
            foreach ($sensitiveFields as $sensitiveField) {
                if (stripos($propertyName, $sensitiveField) !== false) {
                    $isSenitiveField = true;
                    break;
                }
            }
            
            if (!$isSenitiveField) {
                $payload[$propertyName] = $property->getValue($event);
            }
        }

        return $payload;
    }

    /**
     * Trích xuất module từ event name
     *
     * @param string $eventName
     * @return string
     */
    private function extractModuleFromEventName(string $eventName): string
    {
        // Event name format: Domain.Entity.Action
        $parts = explode('.', $eventName);
        return $parts[0] ?? 'Unknown';
    }

    /**
     * Xác định mức độ nghiêm trọng của sự kiện
     *
     * @param string $eventName
     * @return string
     */
    private function determineSeverity(string $eventName): string
    {
        $criticalActions = ['Deleted', 'Failed', 'Rejected', 'Error'];
        $warningActions = ['Updated', 'Modified', 'Reverted'];
        
        foreach ($criticalActions as $action) {
            if (stripos($eventName, $action) !== false) {
                return 'critical';
            }
        }
        
        foreach ($warningActions as $action) {
            if (stripos($eventName, $action) !== false) {
                return 'warning';
            }
        }
        
        return 'info';
    }
}