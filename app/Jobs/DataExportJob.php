<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\ExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DataExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes
    public $tries = 2;
    public $backoff = [60, 120];

    public $userId;
    public $exportType;
    public $filters;
    public $format;
    public $options;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $userId,
        string $exportType,
        array $filters = [],
        string $format = 'excel',
        array $options = []
    ) {
        $this->userId = $userId;
        $this->exportType = $exportType;
        $this->filters = $filters;
        $this->format = $format;
        $this->options = $options;
        $this->onQueue('data-export');
    }

    /**
     * Execute the job.
     */
    public function handle(ExportService $exportService): void
    {
        try {
            $user = User::find($this->userId);
            if (!$user) {
                Log::warning('DataExportJob: User not found', ['user_id' => $this->userId]);
                return;
            }

            Log::info('Starting data export', [
                'user_id' => $this->userId,
                'export_type' => $this->exportType,
                'format' => $this->format
            ]);

            // Generate export data
            $exportData = $this->generateExportData($user);

            // Export to file
            $filePath = $exportService->export($exportData, $this->format, array_merge([
                'title' => $this->getExportTitle(),
                'prefix' => $this->exportType . '_export'
            ], $this->options));

            // Send notification to user
            $this->notifyUser($user, $filePath);

            Log::info('Data export completed successfully', [
                'user_id' => $this->userId,
                'export_type' => $this->exportType,
                'file_path' => $filePath
            ]);

        } catch (\Exception $e) {
            Log::error('Data export failed', [
                'user_id' => $this->userId,
                'export_type' => $this->exportType,
                'error' => $e->getMessage()
            ]);

            // Notify user of failure
            $this->notifyUserOfFailure($user ?? null, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Generate export data based on type
     */
    protected function generateExportData(User $user): array
    {
        switch ($this->exportType) {
            case 'projects':
                return $this->getProjectsData($user);
            case 'tasks':
                return $this->getTasksData($user);
            case 'team':
                return $this->getTeamData($user);
            case 'documents':
                return $this->getDocumentsData($user);
            case 'reports':
                return $this->getReportsData($user);
            case 'all':
                return $this->getAllData($user);
            default:
                return [];
        }
    }

    /**
     * Get projects data
     */
    protected function getProjectsData(User $user): array
    {
        $projects = \App\Models\Project::where('tenant_id', $user->tenant_id)
            ->when(isset($this->filters['status']), function ($query) {
                $query->where('status', $this->filters['status']);
            })
            ->when(isset($this->filters['date_from']), function ($query) {
                $query->where('created_at', '>=', $this->filters['date_from']);
            })
            ->when(isset($this->filters['date_to']), function ($query) {
                $query->where('created_at', '<=', $this->filters['date_to']);
            })
            ->with(['team', 'tasks'])
            ->get();

        return [
            'Projects' => $projects->map(function ($project) {
                return [
                    'ID' => $project->id,
                    'Name' => $project->name,
                    'Description' => $project->description,
                    'Status' => $project->status,
                    'Progress' => $project->progress . '%',
                    'Budget' => $project->budget_total,
                    'Actual Cost' => $project->actual_cost,
                    'Start Date' => $project->start_date,
                    'End Date' => $project->end_date,
                    'Team Members' => $project->team->count(),
                    'Tasks Count' => $project->tasks->count(),
                    'Created At' => $project->created_at,
                    'Updated At' => $project->updated_at
                ];
            })->toArray()
        ];
    }

    /**
     * Get tasks data
     */
    protected function getTasksData(User $user): array
    {
        $tasks = \App\Models\Task::where('tenant_id', $user->tenant_id)
            ->when(isset($this->filters['status']), function ($query) {
                $query->where('status', $this->filters['status']);
            })
            ->when(isset($this->filters['priority']), function ($query) {
                $query->where('priority', $this->filters['priority']);
            })
            ->with(['project', 'assignee', 'creator'])
            ->get();

        return [
            'Tasks' => $tasks->map(function ($task) {
                return [
                    'ID' => $task->id,
                    'Title' => $task->title,
                    'Description' => $task->description,
                    'Status' => $task->status,
                    'Priority' => $task->priority,
                    'Project' => $task->project->name ?? 'N/A',
                    'Assignee' => $task->assignee->name ?? 'Unassigned',
                    'Creator' => $task->creator->name ?? 'N/A',
                    'Due Date' => $task->due_date,
                    'Created At' => $task->created_at,
                    'Updated At' => $task->updated_at
                ];
            })->toArray()
        ];
    }

    /**
     * Get team data
     */
    protected function getTeamData(User $user): array
    {
        $teamMembers = User::where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->with(['roles'])
            ->get();

        return [
            'Team Members' => $teamMembers->map(function ($member) {
                return [
                    'ID' => $member->id,
                    'Name' => $member->name,
                    'Email' => $member->email,
                    'Role' => $member->roles->first()->name ?? 'N/A',
                    'Status' => $member->is_active ? 'Active' : 'Inactive',
                    'Last Login' => $member->last_login_at,
                    'Created At' => $member->created_at
                ];
            })->toArray()
        ];
    }

    /**
     * Get documents data
     */
    protected function getDocumentsData(User $user): array
    {
        $documents = \App\Models\Document::where('tenant_id', $user->tenant_id)
            ->when(isset($this->filters['type']), function ($query) {
                $query->where('type', $this->filters['type']);
            })
            ->with(['project', 'creator'])
            ->get();

        return [
            'Documents' => $documents->map(function ($document) {
                return [
                    'ID' => $document->id,
                    'Title' => $document->title,
                    'Type' => $document->type,
                    'Status' => $document->status,
                    'Project' => $document->project->name ?? 'N/A',
                    'Creator' => $document->creator->name ?? 'N/A',
                    'File Size' => $document->file_size,
                    'Word Count' => $document->word_count,
                    'Created At' => $document->created_at,
                    'Updated At' => $document->updated_at
                ];
            })->toArray()
        ];
    }

    /**
     * Get reports data
     */
    protected function getReportsData(User $user): array
    {
        $reports = \App\Models\ReportSchedule::where('tenant_id', $user->tenant_id)
            ->with(['user'])
            ->get();

        return [
            'Report Schedules' => $reports->map(function ($report) {
                return [
                    'ID' => $report->id,
                    'Name' => $report->name,
                    'Type' => $report->type,
                    'Format' => $report->format,
                    'Frequency' => $report->frequency,
                    'Is Active' => $report->is_active ? 'Yes' : 'No',
                    'Last Sent' => $report->last_sent_at,
                    'Next Send' => $report->next_send_at,
                    'Created By' => $report->user->name ?? 'N/A',
                    'Created At' => $report->created_at
                ];
            })->toArray()
        ];
    }

    /**
     * Get all data
     */
    protected function getAllData(User $user): array
    {
        return array_merge(
            $this->getProjectsData($user),
            $this->getTasksData($user),
            $this->getTeamData($user),
            $this->getDocumentsData($user),
            $this->getReportsData($user)
        );
    }

    /**
     * Get export title
     */
    protected function getExportTitle(): string
    {
        return ucfirst($this->exportType) . ' Export - ' . now()->format('Y-m-d H:i:s');
    }

    /**
     * Notify user of successful export
     */
    protected function notifyUser(User $user, string $filePath): void
    {
        // Create notification
        \App\Models\Notification::create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'type' => 'export',
            'priority' => 'normal',
            'title' => 'Data Export Completed',
            'body' => "Your {$this->exportType} export has been completed successfully.",
            'link_url' => '/app/files',
            'data' => [
                'export_type' => $this->exportType,
                'file_path' => $filePath,
                'download_url' => Storage::url($filePath)
            ]
        ]);

        // Send email notification if enabled
        if ($user->email_preferences['exports'] ?? true) {
            \App\Jobs\EmailNotificationJob::dispatch(
                \App\Models\Notification::latest()->first()->id,
                $user->id,
                [
                    'subject' => 'Data Export Completed',
                    'message' => "Your {$this->exportType} export has been completed successfully.",
                    'type' => 'export'
                ]
            );
        }
    }

    /**
     * Notify user of export failure
     */
    protected function notifyUserOfFailure(?User $user, string $errorMessage): void
    {
        if (!$user) return;

        \App\Models\Notification::create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'type' => 'export',
            'priority' => 'critical',
            'title' => 'Data Export Failed',
            'body' => "Your {$this->exportType} export failed: {$errorMessage}",
            'data' => [
                'export_type' => $this->exportType,
                'error' => $errorMessage
            ]
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('DataExportJob: Job failed permanently', [
            'error' => $exception->getMessage(),
            'user_id' => $this->userId,
            'export_type' => $this->exportType
        ]);

        $user = User::find($this->userId);
        if ($user) {
            $this->notifyUserOfFailure($user, $exception->getMessage());
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'data-export',
            'user:' . $this->userId,
            'type:' . $this->exportType,
            'format:' . $this->format
        ];
    }
}
