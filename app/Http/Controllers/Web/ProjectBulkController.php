<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Src\CoreProject\Models\LegacyProjectAdapter as Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Project Bulk Operations Controller
 * Handles bulk operations for projects
 */
class ProjectBulkController extends Controller
{
    /**
     * Bulk export projects
     */
    public function bulkExport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'project_ids' => 'required|array',
            'project_ids.*' => 'integer|exists:projects,id',
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
            $projectIds = $request->project_ids;
            $format = $request->get('format', 'csv');
            
            $projects = Project::with(['client', 'pm', 'tasks'])
                ->whereIn('id', $projectIds)
                ->get();

            if ($projects->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No projects found for export'
                ], 404);
            }

            $filename = 'projects_export_' . date('Y-m-d_H-i-s') . '.' . $format;
            $filePath = 'exports/' . $filename;

            switch ($format) {
                case 'csv':
                    $this->exportToCsv($projects, $filePath);
                    break;
                case 'excel':
                    $this->exportToExcel($projects, $filePath);
                    break;
                case 'pdf':
                    $this->exportToPdf($projects, $filePath);
                    break;
                case 'json':
                    $this->exportToJson($projects, $filePath);
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => 'Projects exported successfully',
                'data' => [
                    'filename' => $filename,
                    'download_url' => route('projects.download-export', ['filename' => $filename]),
                    'projects_count' => $projects->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export projects: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk status change
     */
    public function bulkStatusChange(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'project_ids' => 'required|array',
            'project_ids.*' => 'integer|exists:projects,id',
            'status' => 'required|in:draft,active,on_hold,completed,archived'
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

            $projectIds = $request->project_ids;
            $newStatus = $request->status;

            $updatedCount = Project::whereIn('id', $projectIds)
                ->update([
                    'status' => $newStatus,
                    'updated_at' => now()
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$updatedCount} projects to {$newStatus} status",
                'data' => [
                    'updated_count' => $updatedCount,
                    'new_status' => $newStatus
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update project status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk assign projects
     */
    public function bulkAssign(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'project_ids' => 'required|array',
            'project_ids.*' => 'integer|exists:projects,id',
            'pm_id' => 'required|integer|exists:users,id'
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

            $projectIds = $request->project_ids;
            $pmId = $request->pm_id;

            $updatedCount = Project::whereIn('id', $projectIds)
                ->update([
                    'pm_id' => $pmId,
                    'updated_at' => now()
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully assigned {$updatedCount} projects",
                'data' => [
                    'updated_count' => $updatedCount,
                    'pm_id' => $pmId
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign projects: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk archive projects
     */
    public function bulkArchive(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'project_ids' => 'required|array',
            'project_ids.*' => 'integer|exists:projects,id'
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

            $projectIds = $request->project_ids;

            $updatedCount = Project::whereIn('id', $projectIds)
                ->update([
                    'status' => 'archived',
                    'updated_at' => now()
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully archived {$updatedCount} projects",
                'data' => [
                    'updated_count' => $updatedCount
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to archive projects: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete projects
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'project_ids' => 'required|array',
            'project_ids.*' => 'integer|exists:projects,id'
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

            $projectIds = $request->project_ids;

            // Soft delete projects
            $deletedCount = Project::whereIn('id', $projectIds)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully deleted {$deletedCount} projects",
                'data' => [
                    'deleted_count' => $deletedCount
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete projects: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Duplicate project
     */
    public function duplicate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|integer|exists:projects,id'
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

            $originalProject = Project::with('tasks')->findOrFail($request->project_id);
            
            $newProject = $originalProject->replicate();
            $newProject->code = $originalProject->code . '-COPY-' . time();
            $newProject->name = $originalProject->name . ' (Copy)';
            $newProject->status = 'draft';
            $newProject->progress = 0;
            $newProject->save();

            // Duplicate tasks
            foreach ($originalProject->tasks as $task) {
                $newTask = $task->replicate();
                $newTask->project_id = $newProject->id;
                $newTask->status = 'pending';
                $newTask->progress_percent = 0;
                $newTask->actual_hours = 0;
                $newTask->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Project duplicated successfully',
                'data' => [
                    'original_project_id' => $originalProject->id,
                    'new_project_id' => $newProject->id,
                    'new_project_name' => $newProject->name,
                    'duplicated_tasks_count' => $originalProject->tasks->count()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate project: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export to CSV
     */
    private function exportToCsv($projects, $filePath): void
    {
        $csvData = [];
        $csvData[] = ['ID', 'Code', 'Name', 'Description', 'Status', 'Client', 'PM', 'Start Date', 'End Date', 'Budget', 'Progress', 'Tasks Count', 'Created At'];

        foreach ($projects as $project) {
            $csvData[] = [
                $project->id,
                $project->code,
                $project->name,
                $project->description,
                $project->status,
                $project->client->name ?? 'N/A',
                $project->pm->name ?? 'N/A',
                $project->start_date,
                $project->end_date,
                $project->budget_total,
                $project->progress . '%',
                $project->tasks->count(),
                $project->created_at->format('Y-m-d H:i:s')
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
    private function exportToExcel($projects, $filePath): void
    {
        // For now, 
    }

    /**
     * Export to PDF
     */
    private function exportToPdf($projects, $filePath): void
    {
        // For now, create a simple text file
        // In production, you would 
        $pdfContent .= "Generated: " . now()->format('Y-m-d H:i:s') . "\n";
        $pdfContent .= "Total Projects: " . $projects->count() . "\n\n";

        foreach ($projects as $project) {
            $pdfContent .= "Project ID: {$project->id}\n";
            $pdfContent .= "Code: {$project->code}\n";
            $pdfContent .= "Name: {$project->name}\n";
            $pdfContent .= "Status: {$project->status}\n";
            $pdfContent .= "Client: " . ($project->client->name ?? 'N/A') . "\n";
            $pdfContent .= "PM: " . ($project->pm->name ?? 'N/A') . "\n";
            $pdfContent .= "Progress: {$project->progress}%\n";
            $pdfContent .= "Budget: {$project->budget_total}\n";
            $pdfContent .= "Tasks: " . $project->tasks->count() . "\n";
            $pdfContent .= "---\n\n";
        }

        Storage::put($filePath, $pdfContent);
    }

    /**
     * Export to JSON
     */
    private function exportToJson($projects, $filePath): void
    {
        $jsonData = [
            'export_info' => [
                'generated_at' => now()->toISOString(),
                'total_projects' => $projects->count(),
                'format' => 'json'
            ],
            'projects' => $projects->map(function($project) {
                return [
                    'id' => $project->id,
                    'code' => $project->code,
                    'name' => $project->name,
                    'description' => $project->description,
                    'status' => $project->status,
                    'client' => $project->client->name ?? null,
                    'pm' => $project->pm->name ?? null,
                    'start_date' => $project->start_date,
                    'end_date' => $project->end_date,
                    'budget_total' => $project->budget_total,
                    'progress' => $project->progress,
                    'tasks_count' => $project->tasks->count(),
                    'created_at' => $project->created_at->toISOString(),
                    'updated_at' => $project->updated_at->toISOString()
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