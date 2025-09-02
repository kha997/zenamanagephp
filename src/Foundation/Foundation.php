<?php
declare(strict_types=1);

namespace Src\Foundation;

use DateTime;
use DateTimeZone;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Lớp Foundation cung cấp các tiêu chuẩn dùng chung cho toàn bộ dự án
 * Bao gồm: ULID, thời gian, tag đa cấp, visibility, và event payload
 */
class Foundation {
    private ?object $database = null;
    private ?object $eventBus = null;
    
    /**
     * Tạo ULID (Universally Unique Lexicographically Sortable Identifier)
     * ULID có thể sắp xếp theo thời gian và duy nhất toàn cầu
     * 
     * @return string ULID mới
     */
    public static function generateUlid(): string {
        return (string) Str::ulid();
    }
    
    /**
     * Lấy thời gian hiện tại theo định dạng ISO 8601 ở múi giờ UTC
     * Đảm bảo tính nhất quán về múi giờ trong toàn bộ hệ thống
     * 
     * @return string Thời gian hiện tại theo định dạng ISO 8601
     */
    public static function getCurrentTime(): string {
        $dateTime = new DateTime('now', new DateTimeZone('UTC'));
        return $dateTime->format('Y-m-d\TH:i:s\Z'); // ISO 8601
    }
    
    /**
     * Tạo cấu trúc tag đa cấp từ đường dẫn tag
     * Hỗ trợ tối đa 3 cấp độ như yêu cầu: Material/Flooring/Wood
     * 
     * @param string $tagPath Đường dẫn tag (VD: "Material/Flooring/Wood")
     * @return array Mảng chứa thông tin tag với tag_path và levels
     * @throws Exception Nếu tag path vượt quá 3 cấp độ
     */
    public static function createTagStructure(string $tagPath): array {
        $levels = explode('/', trim($tagPath, '/'));
        
        // Kiểm tra giới hạn 3 cấp độ
        if (count($levels) > 3) {
            throw new Exception('Tag path không được vượt quá 3 cấp độ');
        }
        
        // Loại bỏ các cấp độ trống
        $levels = array_filter($levels, function($level) {
            return !empty(trim($level));
        });
        
        return [
            'tag_path' => implode('/', $levels),
            'levels' => array_values($levels)
        ];
    }
    
    /**
     * Kiểm tra tính hợp lệ của visibility
     * Chỉ cho phép 'internal' hoặc 'client'
     * 
     * @param string $visibility Giá trị visibility cần kiểm tra
     * @return bool True nếu hợp lệ, ngược lại là False
     */
    public static function isValidVisibility(string $visibility): bool {
        return in_array($visibility, ['internal', 'client'], true);
    }
    
    /**
     * Tạo cấu trúc visibility chuẩn với client_approved
     * 
     * @param string $visibility Loại visibility ('internal' hoặc 'client')
     * @param bool $clientApproved Trạng thái duyệt của client (mặc định false)
     * @return array Cấu trúc visibility chuẩn
     * @throws Exception Nếu visibility không hợp lệ
     */
    public static function createVisibilityStructure(string $visibility, bool $clientApproved = false): array {
        if (!self::isValidVisibility($visibility)) {
            throw new Exception('Visibility phải là "internal" hoặc "client"');
        }
        
        return [
            'visibility' => $visibility,
            'client_approved' => $clientApproved
        ];
    }
    
    /**
     * Tạo tên sự kiện chuẩn theo format domain.action.performed
     * 
     * @param string $domain Tên miền (VD: project)
     * @param string $action Hành động (VD: component)
     * @param string $performed Trạng thái (VD: progress_updated)
     * @return string Tên sự kiện chuẩn
     */
    public static function createEventName(string $domain, string $action, string $performed): string {
        return sprintf('%s.%s.%s', $domain, $action, $performed);
    }
    
    /**
     * Tạo payload chuẩn cho sự kiện với các trường bắt buộc
     * 
     * @param string $id ID của đối tượng
     * @param string|null $projectId ID của dự án (nullable cho entity toàn cục)
     * @param string $actorId ID của người thực hiện
     * @param array $changedFields Các trường đã thay đổi
     * @param string|null $tenantId ID của tenant (đa công ty)
     * @return array Payload chuẩn cho sự kiện
     */
    public static function createEventPayload(
        string $id, 
        ?string $projectId, 
        string $actorId, 
        array $changedFields,
        ?string $tenantId = null
    ): array {
        $payload = [
            'id' => $id,
            'project_id' => $projectId,
            'actor_id' => $actorId,
            'changed_fields' => $changedFields,
            'at' => self::getCurrentTime()
        ];
        
        // Thêm tenant_id nếu có
        if ($tenantId !== null) {
            $payload['tenant_id'] = $tenantId;
        }
        
        return $payload;
    }
    
    /**
     * Tạo cấu trúc audit chuẩn cho các bảng
     * 
     * @param string $createdBy ID người tạo
     * @param string|null $updatedBy ID người cập nhật (nullable)
     * @param string|null $tenantId ID tenant (nullable)
     * @param string|null $projectId ID project (nullable cho entity toàn cục)
     * @return array Cấu trúc audit chuẩn
     */
    public static function createAuditStructure(
        string $createdBy,
        ?string $updatedBy = null,
        ?string $tenantId = null,
        ?string $projectId = null
    ): array {
        $audit = [
            'id' => self::generateUlid(),
            'created_by' => $createdBy,
            'updated_by' => $updatedBy ?? $createdBy,
            'created_at' => self::getCurrentTime(),
            'updated_at' => self::getCurrentTime(),
            'deleted_at' => null // Soft delete
        ];
        
        // Thêm tenant_id nếu có
        if ($tenantId !== null) {
            $audit['tenant_id'] = $tenantId;
        }
        
        // Thêm project_id nếu có
        if ($projectId !== null) {
            $audit['project_id'] = $projectId;
        }
        
        return $audit;
    }
    
    /**
     * Kiểm tra và validate enum string
     * 
     * @param string $value Giá trị cần kiểm tra
     * @param array $allowedValues Danh sách giá trị cho phép
     * @param string $fieldName Tên trường (để hiển thị lỗi)
     * @return bool True nếu hợp lệ
     * @throws Exception Nếu giá trị không hợp lệ
     */
    public static function validateEnum(string $value, array $allowedValues, string $fieldName): bool {
        if (!in_array($value, $allowedValues, true)) {
            $allowed = implode(', ', $allowedValues);
            throw new Exception("Giá trị '{$value}' không hợp lệ cho trường '{$fieldName}'. Các giá trị cho phép: {$allowed}");
        }
        
        return true;
    }
    
    /**
     * Constructor khởi tạo Foundation instance
     */
    public function __construct() {
        // Khởi tạo các service cần thiết
    }
    
    /**
     * Khởi tạo kết nối database
     */
    public function initializeDatabase(): void {
        try {
            // Sử dụng Laravel's Database Manager thay vì PDO thuần
            $config = [
                'driver' => 'mysql',
                'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
                'port' => $_ENV['DB_PORT'] ?? '3306',
                'database' => $_ENV['DB_DATABASE'] ?? 'zenamanage',
                'username' => $_ENV['DB_USERNAME'] ?? 'root',
                'password' => $_ENV['DB_PASSWORD'] ?? '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => 'InnoDB',
            ];
            
            // Tạo Database Manager instance
            $capsule = new \Illuminate\Database\Capsule\Manager;
            $capsule->addConnection($config);
            $capsule->setAsGlobal();
            $capsule->bootEloquent();
            
            // Lưu connection instance
            $this->database = $capsule->getConnection();
            
        } catch (\Exception $e) {
            throw new \Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Lấy database instance
     * 
     * @return object|null
     */
    public function getDatabase(): ?object {
        return $this->database;
    }
    
    /**
     * Lấy EventBus instance
     * 
     * @return EventBus
     */
    public function getEventBus(): EventBus {
        if (!$this->eventBus) {
            $this->eventBus = new EventBus();
        }
        return $this->eventBus;
    }
}