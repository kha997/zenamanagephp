<?php declare(strict_types=1);

namespace Src\RBAC\Services;

use Src\RBAC\Models\Role;
use Src\RBAC\Models\Permission;
use Src\Foundation\EventBus;
use Illuminate\Support\Collection;

/**
 * Service xử lý import/export Permission Matrix CSV
 * Format: role_name,module,action,permission_code,allow:boolean
 */
class PermissionMatrixService
{
    private EventBus $eventBus;

    public function __construct(EventBus $eventBus)
    {
        $this->eventBus = $eventBus;
    }

    /**
     * Export permission matrix ra CSV
     * 
     * @return string CSV content
     */
    public function exportToCSV(): string
    {
        $roles = Role::with('permissions')->get();
        
        $csvData = [];
        $csvData[] = ['role_name', 'module', 'action', 'permission_code', 'allow'];
        
        foreach ($roles as $role) {
            foreach ($role->permissions as $permission) {
                $csvData[] = [
                    $role->name,
                    $permission->module,
                    $permission->action,
                    $permission->code,
                    'true'
                ];
            }
        }
        
        // Convert to CSV string
        $output = fopen('php://temp', 'r+');
        foreach ($csvData as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        return $csvContent;
    }

    /**
     * Import permission matrix từ CSV
     * 
     * @param string $csvContent Nội dung CSV
     * @param int $actorId ID của user thực hiện import
     * @return array Kết quả import
     */
    public function importFromCSV(string $csvContent, int $actorId): array
    {
        $lines = str_getcsv($csvContent, "\n");
        
        if (empty($lines)) {
            return [
                'success' => false,
                'message' => 'File CSV rỗng',
                'errors' => []
            ];
        }
        
        // Bỏ qua header
        $header = str_getcsv(array_shift($lines));
        
        // Validate header
        $expectedHeader = ['role_name', 'module', 'action', 'permission_code', 'allow'];
        if ($header !== $expectedHeader) {
            return [
                'success' => false,
                'message' => 'Header CSV không đúng định dạng. Cần: ' . implode(',', $expectedHeader),
                'errors' => []
            ];
        }
        
        $errors = [];
        $processed = 0;
        $skipped = 0;
        $rolePermissions = []; // role_name => [permission_codes]
        
        foreach ($lines as $lineNumber => $line) {
            $data = str_getcsv($line);
            
            if (count($data) !== 5) {
                $errors[] = "Dòng " . ($lineNumber + 2) . ": Không đủ cột dữ liệu";
                $skipped++;
                continue;
            }
            
            [$roleName, $module, $action, $permissionCode, $allow] = $data;
            
            // Validate dữ liệu
            if (empty($roleName) || empty($module) || empty($action) || empty($permissionCode)) {
                $errors[] = "Dòng " . ($lineNumber + 2) . ": Thiếu dữ liệu bắt buộc";
                $skipped++;
                continue;
            }
            
            // Validate allow boolean
            $allowBool = strtolower($allow) === 'true';
            if (!$allowBool) {
                // Nếu allow = false, bỏ qua (không gán permission)
                $skipped++;
                continue;
            }
            
            // Validate permission code format
            $expectedCode = Permission::generateCode($module, $action);
            if ($permissionCode !== $expectedCode) {
                $errors[] = "Dòng " . ($lineNumber + 2) . ": Permission code không khớp với module.action. Mong đợi: {$expectedCode}";
                $skipped++;
                continue;
            }
            
            // Tạo permission nếu chưa tồn tại
            $permission = Permission::firstOrCreate(
                ['code' => $permissionCode],
                [
                    'module' => $module,
                    'action' => $action,
                    'description' => "Auto-created from CSV import"
                ]
            );
            
            // Thêm vào danh sách role permissions
            if (!isset($rolePermissions[$roleName])) {
                $rolePermissions[$roleName] = [];
            }
            
            if (!in_array($permissionCode, $rolePermissions[$roleName], true)) {
                $rolePermissions[$roleName][] = $permissionCode;
                $processed++;
            }
        }
        
        // Sync permissions cho từng role
        $rolesUpdated = 0;
        foreach ($rolePermissions as $roleName => $permissionCodes) {
            // Tìm role (ưu tiên system scope)
            $role = Role::where('name', $roleName)
                       ->orderByRaw("FIELD(scope, 'system', 'custom', 'project')")
                       ->first();
            
            if (!$role) {
                $errors[] = "Role '{$roleName}' không tồn tại";
                continue;
            }
            
            // Sync permissions
            $role->permissions()->sync($permissionCodes);
            $rolesUpdated++;
            
            // Phát sự kiện
            $this->eventBus->publish('rbac.role.permissions.imported', [
                'roleId' => $role->id,
                'roleName' => $roleName,
                'permissionCodes' => $permissionCodes,
                'actorId' => $actorId,
                'timestamp' => now()->toISOString()
            ]);
        }
        
        return [
            'success' => true,
            'message' => "Import hoàn thành. Xử lý: {$processed}, Bỏ qua: {$skipped}, Roles cập nhật: {$rolesUpdated}",
            'stats' => [
                'processed' => $processed,
                'skipped' => $skipped,
                'roles_updated' => $rolesUpdated,
                'errors_count' => count($errors)
            ],
            'errors' => $errors
        ];
    }

    /**
     * Validate CSV content trước khi import
     */
    public function validateCSV(string $csvContent): array
    {
        $lines = str_getcsv($csvContent, "\n");
        
        if (empty($lines)) {
            return [
                'valid' => false,
                'errors' => ['File CSV rỗng']
            ];
        }
        
        $header = str_getcsv(array_shift($lines));
        $expectedHeader = ['role_name', 'module', 'action', 'permission_code', 'allow'];
        
        if ($header !== $expectedHeader) {
            return [
                'valid' => false,
                'errors' => ['Header CSV không đúng định dạng']
            ];
        }
        
        $errors = [];
        $duplicates = [];
        $seen = [];
        
        foreach ($lines as $lineNumber => $line) {
            $data = str_getcsv($line);
            
            if (count($data) !== 5) {
                $errors[] = "Dòng " . ($lineNumber + 2) . ": Không đủ cột";
                continue;
            }
            
            [$roleName, $module, $action, $permissionCode, $allow] = $data;
            
            // Kiểm tra trùng lặp
            $key = $roleName . '|' . $permissionCode;
            if (isset($seen[$key])) {
                $duplicates[] = "Dòng " . ($lineNumber + 2) . ": Trùng lặp role '{$roleName}' với permission '{$permissionCode}'";
            } else {
                $seen[$key] = true;
            }
        }
        
        return [
            'valid' => empty($errors) && empty($duplicates),
            'errors' => array_merge($errors, $duplicates),
            'total_rows' => count($lines),
            'duplicate_count' => count($duplicates)
        ];
    }
}