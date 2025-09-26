<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Controller quản lý Permission Matrix CSV import/export
 */
class PermissionMatrixController
{
    private PermissionMatrixService $permissionMatrixService;
    private EventBus $eventBus;

    public function __construct(
        PermissionMatrixService $permissionMatrixService,
        EventBus $eventBus
    ) {
        $this->permissionMatrixService = $permissionMatrixService;
        $this->eventBus = $eventBus;
    }

    /**
     * Export permission matrix ra CSV
     * GET /api/v1/rbac/permission-matrix/export
     */
    public function export(Request $request): Response
    {
        try {
            $csvContent = $this->permissionMatrixService->exportToCSV();
            
            // Phát sự kiện
            $this->eventBus->publish('rbac.permission.matrix.exported', [
                'actorId' => $request->get('user_id'),
                'timestamp' => now()->toISOString()
            ]);
            
            $filename = 'permission_matrix_' . date('Y-m-d_H-i-s') . '.csv';
            
            return response($csvContent, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi export permission matrix: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import permission matrix từ CSV
     * POST /api/v1/rbac/permission-matrix/import
     */
    public function import(Request $request): JsonResponse
    {
        // Validation
        if (!$request->hasFile('csv_file')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vui lòng upload file CSV'
            ], 400);
        }
        
        $file = $request->file('csv_file');
        
        // Validate file type
        if ($file->getClientOriginalExtension() !== 'csv') {
            return response()->json([
                'status' => 'error',
                'message' => 'File phải có định dạng CSV'
            ], 400);
        }
        
        // Validate file size (max 5MB)
        if ($file->getSize() > 5 * 1024 * 1024) {
            return response()->json([
                'status' => 'error',
                'message' => 'File CSV không được vượt quá 5MB'
            ], 400);
        }
        
        try {
            $csvContent = file_get_contents($file->getPathname());
            
            if (empty($csvContent)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File CSV rỗng'
                ], 400);
            }
            
            // Validate CSV trước khi import
            $validation = $this->permissionMatrixService->validateCSV($csvContent);
            
            if (!$validation['valid']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File CSV không hợp lệ',
                    'errors' => $validation['errors']
                ], 400);
            }
            
            // Thực hiện import
            $result = $this->permissionMatrixService->importFromCSV(
                $csvContent,
                (int) $request->get('user_id')
            );
            
            if (!$result['success']) {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                    'errors' => $result['errors'] ?? []
                ], 400);
            }
            
            // Phát sự kiện
            $this->eventBus->publish('rbac.permission.matrix.imported', [
                'actorId' => $request->get('user_id'),
                'stats' => $result['stats'],
                'timestamp' => now()->toISOString()
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => [
                    'stats' => $result['stats'],
                    'errors' => $result['errors']
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi import permission matrix: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate CSV file trước khi import
     * POST /api/v1/rbac/permission-matrix/validate
     */
    public function validateCsv(Request $request): JsonResponse
    {
        if (!$request->hasFile('csv_file')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vui lòng upload file CSV'
            ], 400);
        }
        
        $file = $request->file('csv_file');
        
        if ($file->getClientOriginalExtension() !== 'csv') {
            return response()->json([
                'status' => 'error',
                'message' => 'File phải có định dạng CSV'
            ], 400);
        }
        
        try {
            $csvContent = file_get_contents($file->getPathname());
            
            $validation = $this->permissionMatrixService->validateCSV($csvContent);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'valid' => $validation['valid'],
                    'errors' => $validation['errors'] ?? [],
                    'stats' => $validation['stats'] ?? []
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi validate CSV: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy template CSV mẫu
     * GET /api/v1/rbac/permission-matrix/template
     */
    public function getTemplate(): Response
    {
        $templateData = [
            ['role_name', 'module', 'action', 'permission_code', 'allow'],
            ['Admin', 'project', 'create', 'project.create', 'true'],
            ['Admin', 'project', 'update', 'project.update', 'true'],
            ['Manager', 'project', 'view', 'project.view', 'true'],
            ['User', 'task', 'view', 'task.view', 'true']
        ];
        
        $output = fopen('php://temp', 'r+');
        foreach ($templateData as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="permission_matrix_template.csv"'
        ]);
    }
}