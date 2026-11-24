<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Services\BulkOperationsService;
use App\Services\ImportExportService;
use App\Support\TaskCreationAuthority;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Bulk Operations Controller.
 *
 * Handles bulk operations and import/export functionality
 */
class BulkOperationsController extends Controller
{
    private BulkOperationsService $bulkOperationsService;

    private ImportExportService $importExportService;

    public function __construct(
        BulkOperationsService $bulkOperationsService,
        ImportExportService $importExportService
    ) {
        $this->bulkOperationsService = $bulkOperationsService;
        $this->importExportService = $importExportService;
    }

    /*
    |--------------------------------------------------------------------------
    | User Bulk Operations
    |--------------------------------------------------------------------------
    */

    /**
     * Bulk create users.
     */
    public function bulkCreateUsers(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'users' => 'required|array|max:1000',
                'users.*.name' => 'required|string|max:255',
                'users.*.email' => 'required|email|max:255',
                'users.*.password' => 'nullable|string|min:8',
                'tenant_id' => 'nullable|string',
            ]);

            $results = $this->bulkOperationsService->bulkCreateUsers(
                $request->users,
                $request->tenant_id
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Bulk user creation completed',
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bulk user creation failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Bulk update users.
     */
    public function bulkUpdateUsers(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'updates' => 'required|array|max:1000',
                'updates.*.id' => 'required|string',
                'updates.*.data' => 'required|array',
            ]);

            $results = $this->bulkOperationsService->bulkUpdateUsers($request->updates);

            return response()->json([
                'status' => 'success',
                'message' => 'Bulk user update completed',
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bulk user update failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Bulk delete users.
     */
    public function bulkDeleteUsers(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'user_ids' => 'required|array|max:1000',
                'user_ids.*' => 'required|string',
            ]);

            $results = $this->bulkOperationsService->bulkDeleteUsers($request->user_ids);

            return response()->json([
                'status' => 'success',
                'message' => 'Bulk user deletion completed',
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bulk user deletion failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Project Bulk Operations
    |--------------------------------------------------------------------------
    */

    /**
     * Bulk create projects.
     */
    public function bulkCreateProjects(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'projects' => 'required|array|max:1000',
                'projects.*.name' => 'required|string|max:255',
                'projects.*.description' => 'required|string',
                'tenant_id' => 'nullable|string',
            ]);

            $results = $this->bulkOperationsService->bulkCreateProjects(
                $request->projects,
                $request->tenant_id
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Bulk project creation completed',
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bulk project creation failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Bulk update projects.
     */
    public function bulkUpdateProjects(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'updates' => 'required|array|max:1000',
                'updates.*.id' => 'required|string',
                'updates.*.data' => 'required|array',
            ]);

            $results = $this->bulkOperationsService->bulkUpdateProjects($request->updates);

            return response()->json([
                'status' => 'success',
                'message' => 'Bulk project update completed',
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bulk project update failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Task Bulk Operations
    |--------------------------------------------------------------------------
    */

    /**
     * Bulk create tasks.
     */
    public function bulkCreateTasks(Request $request): JsonResponse
    {
        // CB-3 + O-17: tenant from the authenticated user only; project tenant-scoped; creation authority before anything else.
        $project = $this->authoriseTaskCreation($request, $request->input('tenant_id'), 'api.auth.bulk.tasks.create');
        if ($project instanceof JsonResponse) {
            return $project;
        }

        $validator = Validator::make($request->all(), [
            'tasks' => 'required|array|max:1000',
            'tasks.*.title' => 'required|string|max:255',
            'tasks.*.description' => 'required|string',
            'tasks.*.assignee_id' => ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail) use ($project): void {
                if (!TaskCreationAuthority::eligibleAssignee($project, $value)) {
                    $fail("The {$attribute} must be the project manager or an active participant of this project.");
                }
            }],
        ]);
        if ($validator->fails()) {
            return $this->taskCreationValidationError($validator->errors()->toArray());
        }

        try {
            $results = $this->bulkOperationsService->bulkCreateTasks(
                $request->tasks,
                (string) $project->id,
                (string) $project->tenant_id
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Bulk task creation completed',
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bulk task creation failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Bulk update task status.
     */
    public function bulkUpdateTaskStatus(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'task_ids' => 'required|array|max:1000',
                'task_ids.*' => 'required|string',
                'status' => 'required|string|in:pending,in_progress,completed,cancelled',
            ]);

            $results = $this->bulkOperationsService->bulkUpdateTaskStatus(
                $request->task_ids,
                $request->status
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Bulk task status update completed',
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bulk task status update failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Generic Bulk Operations
    |--------------------------------------------------------------------------
    */

    /**
     * Bulk assign users to projects.
     */
    public function bulkAssignUsersToProjects(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'assignments' => 'required|array|max:1000',
                'assignments.*.user_id' => 'required|string',
                'assignments.*.project_id' => 'required|string',
                'assignments.*.role' => 'nullable|string',
            ]);

            $results = $this->bulkOperationsService->bulkAssignUsersToProjects($request->assignments);

            return response()->json([
                'status' => 'success',
                'message' => 'Bulk user-project assignment completed',
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bulk user-project assignment failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Import/Export Operations
    |--------------------------------------------------------------------------
    */

    /**
     * Export users.
     */
    public function exportUsers(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'format' => 'nullable|string|in:xlsx,xls,csv',
                'filters' => 'nullable|array',
            ]);

            $filepath = $this->importExportService->exportUsers(
                $request->filters ?? [],
                $request->format ?? 'xlsx'
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Users exported successfully',
                'data' => [
                    'filepath' => $filepath,
                    'download_url' => url('api/v1/bulk/download/' . basename($filepath)),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Export failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Export projects.
     */
    public function exportProjects(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'format' => 'nullable|string|in:xlsx,xls,csv',
                'filters' => 'nullable|array',
            ]);

            $filepath = $this->importExportService->exportProjects(
                $request->filters ?? [],
                $request->format ?? 'xlsx'
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Projects exported successfully',
                'data' => [
                    'filepath' => $filepath,
                    'download_url' => url('api/v1/bulk/download/' . basename($filepath)),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Export failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Export tasks.
     */
    public function exportTasks(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'format' => 'nullable|string|in:xlsx,xls,csv',
                'filters' => 'nullable|array',
            ]);

            $filepath = $this->importExportService->exportTasks(
                $request->filters ?? [],
                $request->format ?? 'xlsx'
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Tasks exported successfully',
                'data' => [
                    'filepath' => $filepath,
                    'download_url' => url('api/v1/bulk/download/' . basename($filepath)),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Export failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Import users.
     */
    public function importUsers(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // 10MB max
                'options' => 'nullable|array',
            ]);

            $file = $request->file('file');
            $filepath = $file->store('imports');
            $fullPath = storage_path('app/' . $filepath);

            $results = $this->importExportService->importUsers($fullPath, $request->options ?? []);

            // Clean up uploaded file
            unlink($fullPath);

            return response()->json([
                'status' => 'success',
                'message' => 'Users import completed',
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Import projects.
     */
    public function importProjects(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
                'options' => 'nullable|array',
            ]);

            $file = $request->file('file');
            $filepath = $file->store('imports');
            $fullPath = storage_path('app/' . $filepath);

            $results = $this->importExportService->importProjects($fullPath, $request->options ?? []);

            // Clean up uploaded file
            unlink($fullPath);

            return response()->json([
                'status' => 'success',
                'message' => 'Projects import completed',
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Import tasks.
     */
    public function importTasks(Request $request): JsonResponse
    {
        // CB-3 + O-17: the request's options.tenant_id is never authority; project tenant-scoped; creation authority first.
        $project = $this->authoriseTaskCreation($request, data_get($request->input('options'), 'tenant_id'), 'api.auth.bulk.import.tasks');
        if ($project instanceof JsonResponse) {
            return $project;
        }

        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
                'project_id' => 'required|string',
                'options' => 'nullable|array',
            ]);

            $options = $request->options ?? [];
            $options['tenant_id'] = (string) $project->tenant_id;

            $file = $request->file('file');
            $filepath = $file->store('imports');
            $fullPath = storage_path('app/' . $filepath);

            $results = $this->importExportService->importTasks(
                $fullPath,
                (string) $project->id,
                $options
            );

            // Clean up uploaded file
            unlink($fullPath);

            return response()->json([
                'status' => 'success',
                'message' => 'Tasks import completed',
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get import template.
     */
    public function getImportTemplate(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'type' => 'required|string|in:users,projects,tasks',
            ]);

            $filepath = $this->importExportService->getImportTemplate($request->type);

            return response()->json([
                'status' => 'success',
                'message' => 'Import template generated',
                'data' => [
                    'filepath' => $filepath,
                    'download_url' => url('api/v1/bulk/download/' . basename($filepath)),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Template generation failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Download file.
     */
    public function downloadFile(string $filename)
    {
        $filepath = storage_path('app/exports/' . $filename);

        if (!file_exists($filepath)) {
            abort(404, 'File not found');
        }

        return response()->download($filepath);
    }

    /**
     * Get bulk operation status.
     */
    public function getBulkOperationStatus(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'operation_id' => 'required|string',
            ]);

            $status = $this->bulkOperationsService->getBulkOperationStatus($request->operation_id);

            if (!$status) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Operation not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get operation status: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Queue bulk operation.
     */
    public function queueBulkOperation(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'operation' => 'required|string',
                'data' => 'required|array',
            ]);

            $operationId = $this->bulkOperationsService->queueBulkOperation(
                $request->operation,
                $request->data
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Bulk operation queued',
                'data' => [
                    'operation_id' => $operationId,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to queue operation: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * CB-3 / O-17 gate shared by both bulk task-creation routes. Returns the authorised project, or the error response:
     *   - a tenant_id in the request that differs from the authenticated user's tenant -> 422 (never used as authority);
     *   - project_id missing, malformed, unknown or of another tenant                   -> 422;
     *   - TaskCreationAuthority denial (no task.create, not PM / participant)           -> 403.
     */
    private function authoriseTaskCreation(Request $request, mixed $requestedTenantId, string $source): Project|JsonResponse
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        // No canonical task.create: refuse before anything about the tenant or project is evaluated (CB-4: the route's
        // middleware lets a role named `super_admin` through without the permission).
        if (!$user->hasPermission(TaskCreationAuthority::PERMISSION)) {
            TaskCreationAuthority::logDenial($user, null, TaskCreationAuthority::DENY_PERMISSION, $source);

            return response()->json(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện thao tác này.'], 403);
        }

        $tenantId = (string) $user->tenant_id;
        $validator = Validator::make(
            ['project_id' => $request->input('project_id'), 'tenant_id' => $requestedTenantId],
            [
                'project_id' => ['required', 'string', Rule::exists('projects', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
                'tenant_id' => ['nullable', 'string', Rule::in([$tenantId])],
            ],
            ['tenant_id.in' => 'The tenant_id does not match the authenticated tenant.']
        );
        if ($validator->fails()) {
            return $this->taskCreationValidationError($validator->errors()->toArray());
        }

        $project = Project::query()->where('tenant_id', $tenantId)->whereKey((string) $request->input('project_id'))->firstOrFail();
        $denial = TaskCreationAuthority::denialReason($user, $project);
        if ($denial !== null) {
            TaskCreationAuthority::logDenial($user, $project, $denial, $source);

            return response()->json(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện thao tác này.'], 403);
        }

        return $project;
    }

    /** @param array<string, array<int, string>> $errors */
    private function taskCreationValidationError(array $errors): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $errors], 422);
    }
}
