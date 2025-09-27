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

class ReportGenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 900; // 15 minutes
    public $tries = 2;
    public $backoff = [60, 120];

    protected $userId;
    protected $reportType;
    protected $filters;
    protected $format;
    protected $options;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $userId,
        string $reportType,
        array $filters = [],
        string $format = 'pdf',
        array $options = []
    ) {
        $this->userId = $userId;
        $this->reportType = $reportType;
        $this->filters = $filters;
        $this->format = $format;
        $this->options = $options;
        $this->onQueue('report-generation');
    }

    /**
     * Execute the job.
     */
    public function handle(ExportService $exportService): void
    {
        try {
            $user = User::find($this->userId);
            if (!$user) {
                Log::warning('ReportGenerationJob: User not found', ['user_id' => $this->userId]);
                return;
            }

            Log::info('Starting report generation', [
                'user_id' => $this->userId,
                'report_type' => $this->reportType,
                'format' => $this->format
            ]);

            // Generate report data
            $reportData = $this->generateReportData($user);

            // Export report
            $filePath = $exportService->export($reportData, $this->format, array_merge([
                'title' => $this->getReportTitle(),
                'prefix' => $this->reportType . '_report'
            ], $this->options));

            // Notify user
            $this->notifyUser($user, $filePath);

            Log::info('Report generation completed successfully', [
                'user_id' => $this->userId,
                'report_type' => $this->reportType,
                'file_path' => $filePath
            ]);

        } catch (\Exception $e) {
            Log::error('Report generation failed', [
                'user_id' => $this->userId,
                'report_type' => $this->reportType,
                'error' => $e->getMessage()
            ]);

            // Notify user of failure
            $this->notifyUserOfFailure($user ?? null, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Generate report data based on type
     */
    protected function generateReportData(User $user): array
    {
        switch ($this->reportType) {
            case 'project_summary':
                return $this->getProjectSummaryData($user);
            case 'task_performance':
                return $this->getTaskPerformanceData($user);
            case 'team_productivity':
                return $this->getTeamProductivityData($user);
            case 'financial_summary':
                return $this->getFinancialSummaryData($user);
            case 'monthly_report':
                return $this->getMonthlyReportData($user);
            default:
                return [];
        }
    }

    /**
     * Get project summary data
     */
    protected function getProjectSummaryData(User $user): array
    {
        $projects = \App\Models\Project::where('tenant_id', $user->tenant_id)
            ->when(isset($this->filters['status']), function ($query) {
                $query->where('status', $this->filters['status']);
            })
            ->with(['team', 'tasks'])
            ->get();

        return [
            'Project Summary' => [
                'Total Projects' => $projects->count(),
                'Active Projects' => $projects->where('status', 'active')->count(),
                'Completed Projects' => $projects->where('status', 'completed')->count(),
                'Total Budget' => $projects->sum('budget_total'),
                'Actual Cost' => $projects->sum('actual_cost'),
                'Average Progress' => $projects->avg('progress')
            ],
            'Projects' => $projects->map(function ($project) {
                return [
                    'Name' => $project->name,
                    'Status' => $project->status,
                    'Progress' => $project->progress . '%',
                    'Budget' => $project->budget_total,
                    'Actual Cost' => $project->actual_cost,
                    'Team Size' => $project->team->count(),
                    'Tasks Count' => $project->tasks->count(),
                    'Start Date' => $project->start_date,
                    'End Date' => $project->end_date
                ];
            })->toArray()
        ];
    }

    /**
     * Get task performance data
     */
    protected function getTaskPerformanceData(User $user): array
    {
        $tasks = \App\Models\Task::where('tenant_id', $user->tenant_id)
            ->when(isset($this->filters['date_from']), function ($query) {
                $query->where('created_at', '>=', $this->filters['date_from']);
            })
            ->when(isset($this->filters['date_to']), function ($query) {
                $query->where('created_at', '<=', $this->filters['date_to']);
            })
            ->with(['project', 'assignee'])
            ->get();

        return [
            'Task Performance Summary' => [
                'Total Tasks' => $tasks->count(),
                'Completed Tasks' => $tasks->where('status', 'completed')->count(),
                'In Progress Tasks' => $tasks->where('status', 'in_progress')->count(),
                'Pending Tasks' => $tasks->where('status', 'pending')->count(),
                'Overdue Tasks' => $tasks->where('due_date', '<', now())->where('status', '!=', 'completed')->count()
            ],
            'Tasks by Priority' => $tasks->groupBy('priority')->map(function ($group) {
                return $group->count();
            })->toArray(),
            'Tasks by Status' => $tasks->groupBy('status')->map(function ($group) {
                return $group->count();
            })->toArray()
        ];
    }

    /**
     * Get team productivity data
     */
    protected function getTeamProductivityData(User $user): array
    {
        $teamMembers = User::where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->with(['tasks'])
            ->get();

        return [
            'Team Productivity Summary' => [
                'Total Team Members' => $teamMembers->count(),
                'Active Members' => $teamMembers->where('is_active', true)->count(),
                'Average Tasks per Member' => $teamMembers->avg(function ($member) {
                    return $member->tasks->count();
                })
            ],
            'Team Members' => $teamMembers->map(function ($member) {
                return [
                    'Name' => $member->name,
                    'Email' => $member->email,
                    'Role' => $member->roles->first()->name ?? 'N/A',
                    'Tasks Assigned' => $member->tasks->count(),
                    'Completed Tasks' => $member->tasks->where('status', 'completed')->count(),
                    'Last Login' => $member->last_login_at
                ];
            })->toArray()
        ];
    }

    /**
     * Get financial summary data
     */
    protected function getFinancialSummaryData(User $user): array
    {
        $projects = \App\Models\Project::where('tenant_id', $user->tenant_id)->get();

        return [
            'Financial Summary' => [
                'Total Budget Allocated' => $projects->sum('budget_total'),
                'Total Actual Cost' => $projects->sum('actual_cost'),
                'Budget Variance' => $projects->sum('budget_total') - $projects->sum('actual_cost'),
                'Budget Utilization' => $projects->sum('budget_total') > 0 
                    ? round(($projects->sum('actual_cost') / $projects->sum('budget_total')) * 100, 2) 
                    : 0
            ],
            'Projects Financial' => $projects->map(function ($project) {
                return [
                    'Project Name' => $project->name,
                    'Budget Allocated' => $project->budget_total,
                    'Actual Cost' => $project->actual_cost,
                    'Variance' => $project->budget_total - $project->actual_cost,
                    'Utilization %' => $project->budget_total > 0 
                        ? round(($project->actual_cost / $project->budget_total) * 100, 2) 
                        : 0
                ];
            })->toArray()
        ];
    }

    /**
     * Get monthly report data
     */
    protected function getMonthlyReportData(User $user): array
    {
        $month = $this->filters['month'] ?? now()->month;
        $year = $this->filters['year'] ?? now()->year;

        return array_merge(
            $this->getProjectSummaryData($user),
            $this->getTaskPerformanceData($user),
            $this->getTeamProductivityData($user),
            $this->getFinancialSummaryData($user),
            [
                'Report Period' => [
                    'Month' => $month,
                    'Year' => $year,
                    'Generated At' => now()->toISOString()
                ]
            ]
        );
    }

    /**
     * Get report title
     */
    protected function getReportTitle(): string
    {
        return ucfirst(str_replace('_', ' ', $this->reportType)) . ' Report - ' . now()->format('Y-m-d H:i:s');
    }

    /**
     * Notify user of successful generation
     */
    protected function notifyUser(User $user, string $filePath): void
    {
        \App\Models\Notification::create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'type' => 'report',
            'priority' => 'normal',
            'title' => 'Report Generated Successfully',
            'body' => "Your {$this->reportType} report has been generated successfully.",
            'link_url' => '/app/files',
            'data' => [
                'report_type' => $this->reportType,
                'file_path' => $filePath,
                'download_url' => \Illuminate\Support\Facades\Storage::url($filePath)
            ]
        ]);
    }

    /**
     * Notify user of failure
     */
    protected function notifyUserOfFailure(?User $user, string $errorMessage): void
    {
        if (!$user) return;

        \App\Models\Notification::create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'type' => 'report',
            'priority' => 'critical',
            'title' => 'Report Generation Failed',
            'body' => "Your {$this->reportType} report generation failed: {$errorMessage}",
            'data' => [
                'report_type' => $this->reportType,
                'error' => $errorMessage
            ]
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ReportGenerationJob: Job failed permanently', [
            'error' => $exception->getMessage(),
            'user_id' => $this->userId,
            'report_type' => $this->reportType
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
            'report-generation',
            'user:' . $this->userId,
            'type:' . $this->reportType,
            'format:' . $this->format
        ];
    }
}
