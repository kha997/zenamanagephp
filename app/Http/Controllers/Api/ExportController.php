<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;

class ExportController extends Controller
{
    /**
     * Export tasks to CSV
     */
    public function exportTasks(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'task_ids' => 'array',
                'format' => 'string|in:csv,excel,json',
                'filters' => 'array'
            ]);

            $format = $request->input('format', 'csv');
            $taskIds = $request->input('task_ids', []);
            $filters = $request->input('filters', []);

            // Build query
            $query = Task::with(['project', 'assignments']);
            
            if (!empty($taskIds)) {
                $query->whereIn('id', $taskIds);
            }

            // Apply filters
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            if (isset($filters['priority'])) {
                $query->where('priority', $filters['priority']);
            }
            if (isset($filters['project_id'])) {
                $query->where('project_id', $filters['project_id']);
            }

            $tasks = $query->get();

            // Generate filename
            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = "tasks_export_{$timestamp}.{$format}";

            if ($format === 'csv') {
                $filePath = $this->generateCsv($tasks, $filename);
            } elseif ($format === 'excel') {
                $filePath = $this->generateExcel($tasks, $filename);
            } else {
                $filePath = $this->generateJson($tasks, $filename);
            }

            return response()->json([
                'success' => true,
                'message' => 'Export completed successfully',
                'data' => [
                    'filename' => $filename,
                    'download_url' => Storage::url($filePath),
                    'total_tasks' => $tasks->count()
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Export tasks error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export projects to CSV
     */
    public function exportProjects(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'project_ids' => 'array',
                'format' => 'string|in:csv,excel,json'
            ]);

            $format = $request->input('format', 'csv');
            $projectIds = $request->input('project_ids', []);

            $query = Project::with(['tasks']);
            
            if (!empty($projectIds)) {
                $query->whereIn('id', $projectIds);
            }

            $projects = $query->get();

            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = "projects_export_{$timestamp}.{$format}";

            if ($format === 'csv') {
                $filePath = $this->generateProjectsCsv($projects, $filename);
            } elseif ($format === 'excel') {
                $filePath = $this->generateProjectsExcel($projects, $filename);
            } else {
                $filePath = $this->generateProjectsJson($projects, $filename);
            }

            return response()->json([
                'success' => true,
                'message' => 'Export completed successfully',
                'data' => [
                    'filename' => $filename,
                    'download_url' => Storage::url($filePath),
                    'total_projects' => $projects->count()
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Export projects error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate CSV file for tasks
     */
    private function generateCsv($tasks, $filename): string
    {
        $filePath = "exports/{$filename}";
        
        $csvData = [];
        $csvData[] = [
            'ID', 'Name', 'Description', 'Status', 'Priority', 'Project', 
            'Assignee', 'Start Date', 'End Date', 'Progress %', 
            'Estimated Hours', 'Actual Hours', 'Tags', 'Created At'
        ];

        foreach ($tasks as $task) {
            $csvData[] = [
                $task->id,
                $task->name,
                $task->description,
                $task->status,
                $task->priority,
                $task->project->name ?? 'N/A',
                $task->assignee_id ? 'User ' . $task->assignee_id : 'Unassigned',
                $task->start_date,
                $task->end_date,
                $task->progress_percent,
                $task->estimated_hours,
                $task->actual_hours,
                $task->tags,
                $task->created_at
            ];
        }

        $csvContent = '';
        foreach ($csvData as $row) {
            $csvContent .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }

        Storage::put($filePath, $csvContent);
        
        return $filePath;
    }

    /**
     * Generate CSV file for projects
     */
    private function generateProjectsCsv($projects, $filename): string
    {
        $filePath = "exports/{$filename}";
        
        $csvData = [];
        $csvData[] = [
            'ID', 'Code', 'Name', 'Description', 'Status', 'Priority', 
            'Progress %', 'Budget Total', 'Budget Planned', 'Budget Actual',
            'Start Date', 'End Date', 'Total Tasks', 'Completed Tasks', 'Created At'
        ];

        foreach ($projects as $project) {
            $totalTasks = $project->tasks->count();
            $completedTasks = $project->tasks->where('status', 'completed')->count();
            
            $csvData[] = [
                $project->id,
                $project->code,
                $project->name,
                $project->description,
                $project->status,
                $project->priority,
                $project->progress,
                $project->budget_total,
                $project->budget_planned,
                $project->budget_actual,
                $project->start_date,
                $project->end_date,
                $totalTasks,
                $completedTasks,
                $project->created_at
            ];
        }

        $csvContent = '';
        foreach ($csvData as $row) {
            $csvContent .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }

        Storage::put($filePath, $csvContent);
        
        return $filePath;
    }

    /**
     * Generate Excel file (simplified - just CSV with .xlsx extension)
     */
    private function generateExcel($tasks, $filename)
    {
        // For now, just generate CSV with .xlsx extension
        // In production, you'd 
    }

    /**
     * Generate Excel file for projects
     */
    private function generateProjectsExcel($projects, $filename): string
    {
        return $this->generateProjectsCsv($projects, str_replace('.xlsx', '.csv', $filename));
    }

    /**
     * Generate JSON file
     */
    private function generateJson($tasks, $filename): string
    {
        $filePath = "exports/{$filename}";
        
        $data = [
            'export_info' => [
                'exported_at' => now()->toISOString(),
                'total_records' => $tasks->count(),
                'format' => 'json'
            ],
            'tasks' => $tasks->toArray()
        ];

        Storage::put($filePath, json_encode($data, JSON_PRETTY_PRINT));
        
        return $filePath;
    }

    /**
     * Generate JSON file for projects
     */
    private function generateProjectsJson($projects, $filename): string
    {
        $filePath = "exports/{$filename}";
        
        $data = [
            'export_info' => [
                'exported_at' => now()->toISOString(),
                'total_records' => $projects->count(),
                'format' => 'json'
            ],
            'projects' => $projects->toArray()
        ];

        Storage::put($filePath, json_encode($data, JSON_PRETTY_PRINT));
        
        return $filePath;
    }
}