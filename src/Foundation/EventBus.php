<?php
declare(strict_types=1);

namespace Src\Foundation;

use Src\Foundation\Helpers\AuthHelper;

use Exception;
use Throwable;
/**
 * Event Bus cho hệ thống sự kiện chuẩn zenamanage
 * Tuân thủ naming convention: Domain.Entity.Action (e.g., Project.Component.ProgressUpdated)
 */
class EventBus {
    /**
     * Danh sách các listener đã đăng ký
     * 
     * @var array<string, array<callable>>
     */
    private static array $listeners = [];
    
    /**
     * Danh sách các middleware xử lý sự kiện
     * 
     * @var array<callable>
     */
    private static array $middleware = [];
    
    /**
     * Cờ để bật/tắt audit logging
     * 
     * @var bool
     */
    private static bool $auditEnabled = true;
    
    /**
     * Đăng ký một listener cho một sự kiện
     * 
     * @param string $eventName Tên sự kiện theo format Domain.Entity.Action
     * @param callable|array $callback Hàm callback xử lý sự kiện hoặc array [class, method]
     * @param int $priority Độ ưu tiên (số càng thấp càng ưu tiên)
     * @return void
     */
    public static function subscribe(string $eventName, $callback, int $priority = 100): void {
        self::validateEventName($eventName);
        
        // Chuyển đổi array callback thành callable nếu cần
        if (is_array($callback) && count($callback) === 2) {
            // Kiểm tra xem class có tồn tại không
            if (!class_exists($callback[0])) {
                throw new Exception("Class {$callback[0]} does not exist");
            }
            
            // Kiểm tra xem method có tồn tại không
            if (!method_exists($callback[0], $callback[1])) {
                throw new Exception("Method {$callback[1]} does not exist in class {$callback[0]}");
            }
            
            // Tạo callable từ array
            $callback = function($payload) use ($callback) {
                $instance = app()->make($callback[0]);
                return call_user_func([$instance, $callback[1]], $payload);
            };
        } elseif (!is_callable($callback)) {
            throw new Exception("Callback must be callable or array [class, method]");
        }
        
        if (!isset(self::$listeners[$eventName])) {
            self::$listeners[$eventName] = [];
        }
        
        self::$listeners[$eventName][] = [
            'callback' => $callback,
            'priority' => $priority
        ];
        
        // Sắp xếp theo priority (số thấp hơn = ưu tiên cao hơn)
        usort(self::$listeners[$eventName], function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
    }
    
    /**
     * Phát sự kiện với payload chuẩn
     * 
     * @param string $eventName Tên sự kiện
     * @param mixed $payload Dữ liệu sự kiện hoặc event object
     * @return array Kết quả xử lý từ các listeners
     * @throws Exception
     */
    public static function publish(string $eventName, $payload): array {
        self::validateEventName($eventName);

        $isEventObject = is_object($payload) && method_exists($payload, 'getPayload');
        $payloadArray = $isEventObject ? $payload->getPayload() : (array) $payload;
        self::validatePayload($payloadArray);
        
        // Chuẩn hóa payload
        $standardPayload = self::standardizePayload($eventName, $payloadArray);
        
        // Audit logging
        if (self::$auditEnabled) {
            self::logEvent($eventName, $standardPayload);
        }
        
        $results = [];
        
        // Áp dụng middleware trước khi xử lý
        foreach (self::$middleware as $middleware) {
            $standardPayload = call_user_func($middleware, $eventName, $standardPayload);
        }
        
        // Xử lý listeners
        if (isset(self::$listeners[$eventName])) {
            foreach (self::$listeners[$eventName] as $listenerData) {
                try {
                    $listenerPayload = $isEventObject ? $payload : $standardPayload;
                    $result = call_user_func($listenerData['callback'], $listenerPayload);
                    $results[] = [
                        'success' => true,
                        'result' => $result,
                        'listener' => self::getCallableName($listenerData['callback'])
                    ];
                } catch (Throwable $e) {
                    $results[] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'listener' => self::getCallableName($listenerData['callback'])
                    ];
                    
                    // Log lỗi nhưng không dừng xử lý các listeners khác
                    error_log("EventBus Error in {$eventName}: " . $e->getMessage());
                }
            }
        }
        
        // Phát sự kiện wildcard (*) cho audit listeners
        if ($eventName !== '*' && isset(self::$listeners['*'])) {
            foreach (self::$listeners['*'] as $listenerData) {
                try {
                    call_user_func($listenerData['callback'], $eventName, $standardPayload);
                } catch (Throwable $e) {
                    error_log("EventBus Wildcard Listener Error: " . $e->getMessage());
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Đăng ký middleware xử lý sự kiện
     * 
     * @param callable $middleware Middleware function
     * @return void
     */
    public static function addMiddleware(callable $middleware): void {
        self::$middleware[] = $middleware;
    }
    
    /**
     * Hủy đăng ký tất cả các listener cho một sự kiện
     * 
     * @param string $eventName Tên sự kiện
     * @return void
     */
    public static function unsubscribeAll(string $eventName): void {
        if (isset(self::$listeners[$eventName])) {
            unset(self::$listeners[$eventName]);
        }
    }
    
    /**
     * Hủy đăng ký một listener cụ thể
     * 
     * @param string $eventName Tên sự kiện
     * @param callable|array $callback Callback cần hủy
     * @return bool True nếu tìm thấy và hủy thành công
     */
    public static function unsubscribe(string $eventName, $callback): bool {
        if (!isset(self::$listeners[$eventName])) {
            return false;
        }
        
        $callableName = self::getCallableName($callback);
        
        foreach (self::$listeners[$eventName] as $index => $listenerData) {
            if (self::getCallableName($listenerData['callback']) === $callableName) {
                unset(self::$listeners[$eventName][$index]);
                self::$listeners[$eventName] = array_values(self::$listeners[$eventName]);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Lấy danh sách tất cả listeners đã đăng ký
     * 
     * @return array
     */
    public static function getListeners(): array {
        return self::$listeners;
    }
    
    /**
     * Bật/tắt audit logging
     * 
     * @param bool $enabled
     * @return void
     */
    public static function setAuditEnabled(bool $enabled): void {
        self::$auditEnabled = $enabled;
    }
    
    /**
     * Xóa tất cả listeners (dùng cho testing)
     * 
     * @return void
     */
    public static function clearAll(): void {
        self::$listeners = [];
        self::$middleware = [];
    }
    
    /**
     * Kiểm tra tính hợp lệ của tên sự kiện
     * 
     * @param string $eventName
     * @return void
     * @throws Exception
     */
    private static function validateEventName(string $eventName): void {
        // Cho phép wildcard (*) cho audit listeners
        if ($eventName === '*') {
            return;
        }
        
        // Cho phép cả format Domain.Entity.Action và legacy format (ComponentProgressUpdated)
        $isDomainFormat = preg_match('/^[A-Za-z]+\.[A-Za-z]+\.[A-Za-z]+$/', $eventName);
        $isLegacyFormat = preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $eventName);
        
        if (!$isDomainFormat && !$isLegacyFormat) {
            throw new Exception("Invalid event name format. Expected: Domain.Entity.Action or LegacyFormat, got: {$eventName}");
        }
    }
    
    /**
     * Kiểm tra tính hợp lệ của payload
     * 
     * @param array $payload
     * @return void
     * @throws Exception
     */
    private static function validatePayload(array $payload): void {
        $requiredFields = ['entityId', 'projectId', 'actorId'];
        
        foreach ($requiredFields as $field) {
            if (!isset($payload[$field])) {
                throw new Exception("Missing required field in event payload: {$field}");
            }
        }
    }
    
    /**
     * Chuẩn hóa payload theo format chuẩn
     * 
     * @param string $eventName
     * @param array $payload
     * @return array
     */
    private static function standardizePayload(string $eventName, array $payload): array {
        return array_merge($payload, [
            'eventName' => $eventName,
            'timestamp' => Foundation::getCurrentTime(),
            'eventId' => Foundation::generateUlid()
        ]);
    }
    
    /**
     * Ghi log sự kiện cho audit
     * 
     * @param string $eventName
     * @param array $payload
     * @return void
     */
    private static function logEvent(string $eventName, array $payload): void {
        // Tạo log entry chuẩn
        $logEntry = [
            'event_name' => $eventName,
            'event_id' => $payload['eventId'],
            'entity_id' => $payload['entityId'],
            'project_id' => $payload['projectId'],
            'actor_id' => $payload['actorId'],
            'payload' => json_encode($payload),
            'timestamp' => $payload['timestamp']
        ];
        
        // Ghi vào file log (có thể thay thế bằng database sau)
        $logFile = __DIR__ . '/../../logs/events.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents(
            $logFile,
            date('Y-m-d H:i:s') . " - " . json_encode($logEntry) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
    
    /**
     * Lấy tên của callable để debug
     * 
     * @param callable|array $callback
     * @return string
     */
    private static function getCallableName($callback): string {
        if (is_array($callback) && count($callback) === 2) {
            return $callback[0] . '::' . $callback[1];
        } elseif (is_string($callback)) {
            return $callback;
        } elseif (is_object($callback)) {
            return get_class($callback) . '::__invoke';
        } else {
            return 'unknown';
        }
    }
    
    /**
     * Alias cho publish() để tương thích với code hiện tại
     * 
     * @param string $eventName Tên sự kiện
     * @param array $payload Dữ liệu sự kiện
     * @return array Kết quả xử lý từ các listeners
     * @throws Exception
     */
    public static function dispatch(string $eventName, array $payload): array {
        // Tự động chuẩn hóa payload nếu thiếu các field bắt buộc
        $normalizedPayload = self::normalizePayload($payload);
        
        return self::publish($eventName, $normalizedPayload);
    }
    
    /**
     * Chuẩn hóa payload để đảm bảo có đủ các field bắt buộc
     * 
     * @param array $payload
     * @return array
     */
    private static function normalizePayload(array $payload): array {
        // Mapping các field name khác nhau về chuẩn
        $fieldMapping = [
            'component_id' => 'entityId',
            'project_id' => 'projectId', 
            'actor_id' => 'actorId',
            'user_id' => 'actorId'
        ];
        
        $normalized = $payload;
        
        // Áp dụng mapping
        foreach ($fieldMapping as $oldKey => $newKey) {
            if (isset($payload[$oldKey]) && !isset($payload[$newKey])) {
                $normalized[$newKey] = $payload[$oldKey];
            }
        }
        
        // Đảm bảo có các field bắt buộc với giá trị mặc định
        $normalized['entityId'] = $normalized['entityId'] ?? $normalized['id'] ?? null;
        $normalized['projectId'] = $normalized['projectId'] ?? null;
        // Xử lý actorId an toàn hơn
        try {
            if (function_exists('auth') && AuthHelper::check()) {
                $normalized['actorId'] = $normalized['actorId'] ?? AuthHelper::idOrSystem();
            } else {
                $normalized['actorId'] = $normalized['actorId'] ?? 'system';
            }
        } catch (\Throwable $e) {
            $normalized['actorId'] = $normalized['actorId'] ?? 'system';
        }
        
        return $normalized;
    }
}
