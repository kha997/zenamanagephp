<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;

/**
 * Task Bulk Operations Controller
 * Handles bulk operations for tasks
 */
class TaskBulkController extends Controller
{
    /**
     * Bulk export tasks
     */
    public function bulkExport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'task_ids' => 'required|array',
            'task_ids.*' => 'integer|exists:tasks,id',
            'format' => 'in:csv,excel,pdf,json'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $taskIds = $request->task_ids;
            $format = $request->get('format', 'csv');
            
            $tasks = Task::with(['project', 'assignee'])
                ->whereIn('id', $taskIds)
                ->get();

            if ($tasks->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tasks found for export'
                ], 404);
            }

            $filename = 'tasks_export_' . date('Y-m-d_H-i-s') . '.' . $format;
            $filePath = 'exports/' . $filename;

            switch ($format) {
                case 'csv':
                    $this->exportToCsv($tasks, $filePath);
                    break;
                case 'excel':
                    $this->exportToExcel($tasks, $filePath);
                    break;
                case 'pdf':
                    $this->exportToPdf($tasks, $filePath);
                    break;
                case 'json':
                    $this->exportToJson($tasks, $filePath);
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => 'Tasks exported successfully',
                'data' => [
                    'filename' => $filename,
                    'download_url' => route('tasks.download-export', ['filename' => $filename]),
                    'tasks_count' => $tasks->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export tasks: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk status change
     */
    public function bulkStatusChange(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'task_ids' => 'required|array',
            'task_ids.*' => 'integer|exists:tasks,id',
            'status' => 'required|in:pending,in_progress,completed,cancelled,archived'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $taskIds = $request->task_ids;
            $newStatus = $request->status;

            $updatedCount = Task::whereIn('id', $taskIds)
                ->update([
                    'status' => $newStatus,
                    'updated_at' => now()
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$updatedCount} tasks to {$newStatus} status",
                'data' => [
                    'updated_count' => $updatedCount,
                    'new_status' => $newStatus
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update task status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk assign tasks
     */
    public function bulkAssign(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'task_ids' => 'required|array',
            'task_ids.*' => 'integer|exists:tasks,id',
            'assignee_id' => 'required|integer|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $taskIds = $request->task_ids;
            $assigneeId = $request->assignee_id;

            $updatedCount = Task::whereIn('id', $taskIds)
                ->update([
                    'assignee_id' => $assigneeId,
                    'updated_at' => now()
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully assigned {$updatedCount} tasks",
                'data' => [
                    'updated_count' => $updatedCount,
                    'assignee_id' => $assigneeId
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign tasks: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk archive tasks
     */
    public function bulkArchive(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'task_ids' => 'required|array',
            'task_ids.*' => 'integer|exists:tasks,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $taskIds = $request->task_ids;

            $updatedCount = Task::whereIn('id', $taskIds)
                ->update([
                    'status' => 'archived',
                    'updated_at' => now()
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully archived {$updatedCount} tasks",
                'data' => [
                    'updated_count' => $updatedCount
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to archive tasks: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete tasks
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'task_ids' => 'required|array',
            'task_ids.*' => 'integer|exists:tasks,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $taskIds = $request->task_ids;

            $deletedCount = Task::whereIn('id', $taskIds)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully deleted {$deletedCount} tasks",
                'data' => [
                    'deleted_count' => $deletedCount
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete tasks: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Duplicate task
     */
    public function duplicate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'task_id' => 'required|integer|exists:tasks,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $originalTask = Task::findOrFail($request->task_id);
            
            $newTask = $originalTask->replicate();
            $newTask->name = $originalTask->name . ' (Copy)';
            $newTask->status = 'pending';
            $newTask->progress_percent = 0;
            $newTask->actual_hours = 0;
            $newTask->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Task duplicated successfully',
                'data' => [
                    'original_task_id' => $originalTask->id,
                    'new_task_id' => $newTask->id,
                    'new_task_name' => $newTask->name
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate task: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export to CSV
     */
    private function exportToCsv($tasks, $filePath): void
    {
        $csvData = [];
        $csvData[] = ['ID', 'Name', 'Description', 'Status', 'Priority', 'Project', 'Assignee', 'Due Date', 'Progress', 'Estimated Hours', 'Actual Hours', 'Created At'];

        foreach ($tasks as $task) {
            $csvData[] = [
                $task->id,
                $task->name,
                $task->description,
                $task->status,
                $task->priority,
                $task->project->name ?? 'N/A',
                $task->assignee->name ?? 'Unassigned',
                $task->due_date,
                $task->progress_percent . '%',
                $task->estimated_hours,
                $task->actual_hours,
                $task->created_at->format('Y-m-d H:i:s')
            ];
        }

        $csvContent = '';
        foreach ($csvData as $row) {
            $csvContent .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }

        Storage::put($filePath, $csvContent);
    }

    /**
     * Export to Excel (simplified - using CSV format)
     */
    private function exportToExcel($tasks, $filePath): void
    {
        // For now, use CSV format for Excel export
        // In production, you would use a library like PhpSpreadsheet
        $this->exportToCsv($tasks, $filePath);
    }

    /**
     * Export to PDF
     */
    private function exportToPdf($tasks, $filePath): void
    {
        // For now, create a simple text file
        // In production, you would use a library like DomPDF or TCPDF
        $pdfContent = "TASK EXPORT REPORT\n";
        $pdfContent .= "Generated: " . now()->format('Y-m-d H:i:s') . "\n";
        $pdfContent .= "Total Tasks: " . $tasks->count() . "\n\n";

        foreach ($tasks as $task) {
            $pdfContent .= "Task ID: {$task->id}\n";
            $pdfContent .= "Name: {$task->name}\n";
            $pdfContent .= "Status: {$task->status}\n";
            $pdfContent .= "Project: " . ($task->project->name ?? 'N/A') . "\n";
            $pdfContent .= "Assignee: " . ($task->assignee->name ?? 'Unassigned') . "\n";
            $pdfContent .= "Progress: {$task->progress_percent}%\n";
            $pdfContent .= "Due Date: {$task->due_date}\n";
            $pdfContent .= "---\n\n";
        }

        Storage::put($filePath, $pdfContent);
    }

    /**
     * Export to JSON
     */
    private function exportToJson($tasks, $filePath): void
    {
        $jsonData = [
            'export_info' => [
                'generated_at' => now()->toISOString(),
                'total_tasks' => $tasks->count(),
                'format' => 'json'
            ],
            'tasks' => $tasks->map(function($task) {
                return [
                    'id' => $task->id,
                    'name' => $task->name,
                    'description' => $task->description,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'project' => $task->project->name ?? null,
                    'assignee' => $task->assignee->name ?? null,
                    'due_date' => $task->due_date,
                    'progress_percent' => $task->progress_percent,
                    'estimated_hours' => $task->estimated_hours,
                    'actual_hours' => $task->actual_hours,
                    'created_at' => $task->created_at->toISOString(),
                    'updated_at' => $task->updated_at->toISOString()
                ];
            })
        ];

        Storage::put($filePath, json_encode($jsonData, JSON_PRETTY_PRINT));
    }

    /**
     * Download exported file
     */
    public function downloadExport(Request $request, string $filename)
    {
        $filePath = 'exports/' . $filename;
        
        if (!Storage::exists($filePath)) {
            abort(404, 'Export file not found');
        }

        $mimeType = $this->getMimeType($filename);
        
        return Response::download(
            Storage::path($filePath),
            $filename,
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]
        );
    }

    /**
     * Get MIME type for file
     */
    private function getMimeType(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        $mimeTypes = [
            'csv' => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf',
            'json' => 'application/json'
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
}
