<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CsvExportService
{
    /**
     * Export users to CSV
     */
    public function exportUsers(array $filters = []): string
    {
        try {
            $query = User::query();
            
            // Apply filters
            if (isset($filters['role'])) {
                $query->whereHas('roles', function ($q) use ($filters) {
                    $q->where('name', $filters['role']);
                });
            }
            
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            
            if (isset($filters['created_from'])) {
                $query->whereDate('created_at', '>=', $filters['created_from']);
            }
            
            if (isset($filters['created_to'])) {
                $query->whereDate('created_at', '<=', $filters['created_to']);
            }

            $users = $query->with(['roles', 'tenant'])->get();

            $csvData = [];
            $csvData[] = [
                'ID',
                'Name',
                'Email',
                'Role',
                'Status',
                'Tenant',
                'Created At',
                'Last Login',
                'Phone',
                'Department'
            ];

            foreach ($users as $user) {
                $csvData[] = [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->roles->pluck('name')->join(', '),
                    $user->status ?? 'active',
                    $user->tenant->name ?? '',
                    $user->created_at?->format('Y-m-d H:i:s'),
                    $user->last_login_at?->format('Y-m-d H:i:s'),
                    $user->phone ?? '',
                    $user->department ?? ''
                ];
            }

            return $this->arrayToCsv($csvData);

        } catch (\Exception $e) {
            Log::error('User CSV export failed', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            throw $e;
        }
    }

    /**
     * Export projects to CSV
     */
    public function exportProjects(array $filters = []): string
    {
        try {
            $query = Project::query();
            
            // Apply filters
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            
            if (isset($filters['priority'])) {
                $query->where('priority', $filters['priority']);
            }
            
            if (isset($filters['created_from'])) {
                $query->whereDate('created_at', '>=', $filters['created_from']);
            }
            
            if (isset($filters['created_to'])) {
                $query->whereDate('created_at', '<=', $filters['created_to']);
            }

            $projects = $query->with(['owner', 'tenant', 'tasks'])->get();

            $csvData = [];
            $csvData[] = [
                'ID',
                'Name',
                'Description',
                'Status',
                'Priority',
                'Owner',
                'Budget',
                'Start Date',
                'End Date',
                'Progress',
                'Task Count',
                'Created At'
            ];

            foreach ($projects as $project) {
                $csvData[] = [
                    $project->id,
                    $project->name,
                    $project->description,
                    $project->status,
                    $project->priority,
                    $project->owner->name ?? '',
                    $project->budget ?? '',
                    $project->start_date?->format('Y-m-d'),
                    $project->end_date?->format('Y-m-d'),
                    $project->progress ?? 0,
                    $project->tasks->count(),
                    $project->created_at?->format('Y-m-d H:i:s')
                ];
            }

            return $this->arrayToCsv($csvData);

        } catch (\Exception $e) {
            Log::error('Project CSV export failed', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            throw $e;
        }
    }

    /**
     * Export tasks to CSV
     */
    public function exportTasks(array $filters = []): string
    {
        try {
            $query = Task::query();
            
            // Apply filters
            if (isset($filters['project_id'])) {
                $query->where('project_id', $filters['project_id']);
            }
            
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            
            if (isset($filters['priority'])) {
                $query->where('priority', $filters['priority']);
            }
            
            if (isset($filters['assigned_to'])) {
                $query->where('assigned_to', $filters['assigned_to']);
            }

            $tasks = $query->with(['project', 'assignedUser', 'creator'])->get();

            $csvData = [];
            $csvData[] = [
                'ID',
                'Title',
                'Description',
                'Status',
                'Priority',
                'Project',
                'Assigned To',
                'Creator',
                'Due Date',
                'Created At',
                'Updated At'
            ];

            foreach ($tasks as $task) {
                $csvData[] = [
                    $task->id,
                    $task->title,
                    $task->description,
                    $task->status,
                    $task->priority,
                    $task->project->name ?? '',
                    $task->assignedUser->name ?? '',
                    $task->creator->name ?? '',
                    $task->due_date?->format('Y-m-d'),
                    $task->created_at?->format('Y-m-d H:i:s'),
                    $task->updated_at?->format('Y-m-d H:i:s')
                ];
            }

            return $this->arrayToCsv($csvData);

        } catch (\Exception $e) {
            Log::error('Task CSV export failed', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            throw $e;
        }
    }

    /**
     * Export custom data to CSV
     */
    public function exportCustom(array $data, array $headers, string $filename = 'export'): string
    {
        try {
            $csvData = [];
            $csvData[] = $headers;

            foreach ($data as $row) {
                $csvRow = [];
                foreach ($headers as $header) {
                    $csvRow[] = $row[$header] ?? '';
                }
                $csvData[] = $csvRow;
            }

            return $this->arrayToCsv($csvData);

        } catch (\Exception $e) {
            Log::error('Custom CSV export failed', [
                'error' => $e->getMessage(),
                'headers' => $headers
            ]);
            throw $e;
        }
    }

    /**
     * Convert array to CSV string
     */
    private function arrayToCsv(array $data): string
    {
        $output = fopen('php://temp', 'r+');
        
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        return $csvContent;
    }

    /**
     * Get export statistics
     */
    public function getExportStats(): array
    {
        try {
            $stats = [
                'users' => User::count(),
                'projects' => Project::count(),
                'tasks' => Task::count(),
                'last_export' => $this->getLastExportTime(),
                'export_formats' => ['csv', 'xlsx'],
                'max_rows' => config('csv.max_rows', 10000)
            ];

            return $stats;

        } catch (\Exception $e) {
            Log::error('Export stats failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get last export time
     */
    private function getLastExportTime(): ?string
    {
        // This could be stored in cache or database
        return cache()->get('last_csv_export_time');
    }

    /**
     * Set last export time
     */
    public function setLastExportTime(): void
    {
        cache()->put('last_csv_export_time', now()->toISOString(), 3600); // 1 hour
    }
}
