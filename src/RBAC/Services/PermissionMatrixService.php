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
    public function exportToCSV(?string $tenantId = null): string
    {
        $roles = Role::query()->tenantVisible($tenantId)->with('permissions')->get();
        
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
     * @param string $actorId ID của user thực hiện import (real authenticated
     *        user id, or the established 'system' fallback — GAP-042
     *        Gate-3 Round-1 Correction 6; was previously (int), silently
     *        truncating any real ULID actor id to 0).
     * @param string|null $tenantId GAP-042 §6: caller's tenant context. When
     *        provided, role lookup uses the grouped tenant-visibility
     *        predicate and a resolved global role is skipped (§6 global-role
     *        read/write policy) instead of being silently mutated.
     * @return array Kết quả import
     */
    public function importFromCSV(string $csvContent, string $actorId, ?string $tenantId = null): array
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
            // Tìm role (ưu tiên system scope) — GAP-042 §6 grouped tenant-visibility
            // predicate: never chain a bare orWhere before the name/orderBy filters.
            // Portable "prefer system scope" tie-break: FIELD() is MySQL-only
            // (breaks on SQLite, where this whole path is also exercised by
            // tests) — sort candidates in PHP instead of at the DB layer.
            $scopeOrder = [Role::SCOPE_SYSTEM => 0, Role::SCOPE_CUSTOM => 1, Role::SCOPE_PROJECT => 2];
            $role = Role::where(function ($q) use ($tenantId) {
                    $q->whereNull('tenant_id');
                    if ($tenantId !== null) {
                        $q->orWhere('tenant_id', $tenantId);
                    }
                })
                ->where('name', $roleName)
                ->get()
                ->sortBy(fn ($r) => $scopeOrder[$r->scope] ?? 99)
                ->first();

            if (!$role) {
                $errors[] = "Role '{$roleName}' không tồn tại";
                continue;
            }

            // GAP-042 §6 global-role read/write policy: a CSV import must not
            // silently write to a global (tenant_id IS NULL) role.
            if ($role->tenant_id === null) {
                $errors[] = "Role '{$roleName}' là role hệ thống, không thể import permissions qua CSV";
                continue;
            }

            // Sync permissions — resolve code -> id, sync() requires primary keys.
            $permissionIds = Permission::whereIn('code', $permissionCodes)->pluck('id')->toArray();
            $role->permissions()->sync($permissionIds);
            $rolesUpdated++;

            // Phát sự kiện — GAP-042 Gate-3 Round-1 Correction 2: the prior
            // event name ('rbac.role.permissions.imported', 4 segments) is
            // rejected by EventBus::validateEventName(); the payload also
            // omitted validator-required entityId/projectId. This call runs
            // AFTER the sync() mutation above, inside the per-role loop — a
            // thrown exception here previously would have left prior
            // iterations' syncs committed while aborting the rest of the
            // request with an uncaught 500 (mutation-then-500). Fixed to a
            // valid, complete payload so it cannot throw for a reachable
            // input.
            $this->eventBus->publish('rbac.role.permissionsImported', [
                'entityId' => $role->id,
                'projectId' => (string) ($tenantId ?? 'system'),
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