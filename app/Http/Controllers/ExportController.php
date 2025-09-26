<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;


use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExportController extends Controller
{
    protected $exportService;
    
    public function __construct(ExportService $exportService)
    {
        $this->exportService = $exportService;
    }
    
    /**
     * Export data
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'type' => 'required|string|in:projects,tasks,documents,users,tenants',
                'format' => 'required|string|in:csv,excel,pdf',
                'includeFilters' => 'boolean',
                'columns' => 'nullable|array'
            ]);
            
            $type = $request->type;
            $format = $request->format;
            $includeFilters = $request->includeFilters ?? false;
            $columns = $request->columns ?? [];
            
            // Check permissions for admin-only exports
            if (in_array($type, ['users', 'tenants']) && !Auth::user()->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'id' => 'export_permission_' . uniqid(),
                        'code' => 'E403.PERMISSION_DENIED',
                        'message' => 'Permission denied for this export type',
                        'details' => []
                    ]
                ], 403);
            }
            
            $filters = $includeFilters ? $this->getCurrentFilters() : [];
            
            $result = match($type) {
                'projects' => $this->exportService->exportProjects($filters),
                'tasks' => $this->exportService->exportTasks($filters),
                'documents' => $this->exportService->exportDocuments($filters),
                'users' => $this->exportService->exportUsers($filters),
                'tenants' => $this->exportService->exportTenants($filters),
                default => throw new \InvalidArgumentException('Invalid export type')
            };
            
            $filePath = $result[$format];
            $downloadUrl = route('exports.download', ['file' => basename($filePath)]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'file_path' => $filePath,
                    'download_url' => $downloadUrl,
                    'format' => $format,
                    'type' => $type
                ],
                'message' => 'Export completed successfully'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'export_validation_' . uniqid(),
                    'code' => 'E422.VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $e->errors()
                ]
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'export_' . uniqid(),
                    'code' => 'E500.EXPORT_ERROR',
                    'message' => 'Export failed',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Export projects
     */
    public function projects(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'format' => 'required|string|in:csv,excel,pdf',
                'filters' => 'nullable|array'
            ]);
            
            $format = $request->format;
            $filters = $request->filters ?? [];
            
            $result = $this->exportService->exportProjects($filters);
            $filePath = $result[$format];
            $downloadUrl = route('exports.download', ['file' => basename($filePath)]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'file_path' => $filePath,
                    'download_url' => $downloadUrl,
                    'format' => $format
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'export_projects_' . uniqid(),
                    'code' => 'E500.EXPORT_PROJECTS_ERROR',
                    'message' => 'Failed to export projects',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Export tasks
     */
    public function tasks(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'format' => 'required|string|in:csv,excel,pdf',
                'filters' => 'nullable|array'
            ]);
            
            $format = $request->format;
            $filters = $request->filters ?? [];
            
            $result = $this->exportService->exportTasks($filters);
            $filePath = $result[$format];
            $downloadUrl = route('exports.download', ['file' => basename($filePath)]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'file_path' => $filePath,
                    'download_url' => $downloadUrl,
                    'format' => $format
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'export_tasks_' . uniqid(),
                    'code' => 'E500.EXPORT_TASKS_ERROR',
                    'message' => 'Failed to export tasks',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Export documents
     */
    public function documents(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'format' => 'required|string|in:csv,excel,pdf',
                'filters' => 'nullable|array'
            ]);
            
            $format = $request->format;
            $filters = $request->filters ?? [];
            
            $result = $this->exportService->exportDocuments($filters);
            $filePath = $result[$format];
            $downloadUrl = route('exports.download', ['file' => basename($filePath)]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'file_path' => $filePath,
                    'download_url' => $downloadUrl,
                    'format' => $format
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'export_documents_' . uniqid(),
                    'code' => 'E500.EXPORT_DOCUMENTS_ERROR',
                    'message' => 'Failed to export documents',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Export users (admin only)
     */
    public function users(Request $request): JsonResponse
    {
        try {
            if (!Auth::user()->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'id' => 'export_users_permission_' . uniqid(),
                        'code' => 'E403.PERMISSION_DENIED',
                        'message' => 'Permission denied',
                        'details' => []
                    ]
                ], 403);
            }
            
            $request->validate([
                'format' => 'required|string|in:csv,excel,pdf',
                'filters' => 'nullable|array'
            ]);
            
            $format = $request->format;
            $filters = $request->filters ?? [];
            
            $result = $this->exportService->exportUsers($filters);
            $filePath = $result[$format];
            $downloadUrl = route('exports.download', ['file' => basename($filePath)]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'file_path' => $filePath,
                    'download_url' => $downloadUrl,
                    'format' => $format
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'export_users_' . uniqid(),
                    'code' => 'E500.EXPORT_USERS_ERROR',
                    'message' => 'Failed to export users',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Export tenants (admin only)
     */
    public function tenants(Request $request): JsonResponse
    {
        try {
            if (!Auth::user()->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'id' => 'export_tenants_permission_' . uniqid(),
                        'code' => 'E403.PERMISSION_DENIED',
                        'message' => 'Permission denied',
                        'details' => []
                    ]
                ], 403);
            }
            
            $request->validate([
                'format' => 'required|string|in:csv,excel,pdf',
                'filters' => 'nullable|array'
            ]);
            
            $format = $request->format;
            $filters = $request->filters ?? [];
            
            $result = $this->exportService->exportTenants($filters);
            $filePath = $result[$format];
            $downloadUrl = route('exports.download', ['file' => basename($filePath)]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'file_path' => $filePath,
                    'download_url' => $downloadUrl,
                    'format' => $format
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'export_tenants_' . uniqid(),
                    'code' => 'E500.EXPORT_TENANTS_ERROR',
                    'message' => 'Failed to export tenants',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Get export history
     */
    public function history(): JsonResponse
    {
        try {
            $history = $this->exportService->getExportHistory();
            
            return response()->json([
                'success' => true,
                'data' => $history
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'export_history_' . uniqid(),
                    'code' => 'E500.EXPORT_HISTORY_ERROR',
                    'message' => 'Failed to get export history',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Delete export file
     */
    public function delete(string $filename): JsonResponse
    {
        try {
            $success = $this->exportService->deleteExport($filename);
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Export file deleted successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'id' => 'delete_export_' . uniqid(),
                        'code' => 'E404.FILE_NOT_FOUND',
                        'message' => 'Export file not found',
                        'details' => []
                    ]
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'delete_export_' . uniqid(),
                    'code' => 'E500.DELETE_EXPORT_ERROR',
                    'message' => 'Failed to delete export file',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Clean old exports
     */
    public function cleanOld(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'days_old' => 'integer|min:1|max:30'
            ]);
            
            $daysOld = $request->days_old ?? 7;
            $deletedCount = $this->exportService->cleanOldExports($daysOld);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'deleted_count' => $deletedCount,
                    'days_old' => $daysOld
                ],
                'message' => "Cleaned {$deletedCount} old export files"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'clean_old_exports_' . uniqid(),
                    'code' => 'E500.CLEAN_OLD_EXPORTS_ERROR',
                    'message' => 'Failed to clean old exports',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Get current filters from request
     */
    private function getCurrentFilters(): array
    {
        // This would typically get filters from the current request context
        // For now, return empty array
        return [];
    }
}
