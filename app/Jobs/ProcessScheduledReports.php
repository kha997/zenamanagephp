<?php

namespace App\Jobs;

use App\Models\ReportSchedule;
use App\Services\ExportService;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessScheduledReports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(ExportService $exportService, EmailService $emailService): void
    {
        Log::info('Processing scheduled reports...');

        $scheduledReports = ReportSchedule::active()
            ->dueForSending()
            ->with('user')
            ->get();

        $processedCount = 0;
        $errorCount = 0;

        foreach ($scheduledReports as $schedule) {
            try {
                $this->processReport($schedule, $exportService, $emailService);
                $processedCount++;
                
                Log::info('Scheduled report processed successfully', [
                    'schedule_id' => $schedule->id,
                    'type' => $schedule->type,
                    'recipients' => $schedule->recipients
                ]);
            } catch (\Exception $e) {
                $errorCount++;
                
                Log::error('Failed to process scheduled report', [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        Log::info('Scheduled reports processing completed', [
            'processed' => $processedCount,
            'errors' => $errorCount,
            'total' => $scheduledReports->count()
        ]);
    }

    /**
     * Process individual scheduled report
     */
    protected function processReport(ReportSchedule $schedule, ExportService $exportService, EmailService $emailService): void
    {
        // Generate report data
        $reportData = $this->generateReportData($schedule);
        
        // Export report
        $filePath = $exportService->export($reportData, $schedule->format, [
            'title' => $schedule->name,
            'prefix' => 'scheduled_report'
        ]);

        // Send email with attachment
        $this->sendReportEmail($schedule, $filePath, $emailService);

        // Mark as sent
        $schedule->markAsSent();

        // Clean up file after sending (optional)
        // Storage::delete($filePath);
    }

    /**
     * Generate report data based on schedule type
     */
    protected function generateReportData(ReportSchedule $schedule): array
    {
        // Mock data for now - replace with actual data generation
        switch ($schedule->type) {
            case 'dashboard':
                return $this->getDashboardData($schedule);
            case 'projects':
                return $this->getProjectsData($schedule);
            case 'tasks':
                return $this->getTasksData($schedule);
            case 'team':
                return $this->getTeamData($schedule);
            case 'financial':
                return $this->getFinancialData($schedule);
            default:
                return [];
        }
    }

    /**
     * Get dashboard data
     */
    protected function getDashboardData(ReportSchedule $schedule): array
    {
        return [
            [
                'metric' => 'Total Projects',
                'value' => 25,
                'change' => '+12%'
            ],
            [
                'metric' => 'Active Tasks',
                'value' => 156,
                'change' => '+8%'
            ],
            [
                'metric' => 'Team Members',
                'value' => 12,
                'change' => '+2%'
            ],
            [
                'metric' => 'Budget Used',
                'value' => '$125,000',
                'change' => '+5%'
            ]
        ];
    }

    /**
     * Get projects data
     */
    protected function getProjectsData(ReportSchedule $schedule): array
    {
        return [
            [
                'name' => 'Website Redesign',
                'status' => 'Active',
                'progress' => 75,
                'budget' => '$50,000',
                'due_date' => '2024-02-15'
            ],
            [
                'name' => 'Mobile App Development',
                'status' => 'Planning',
                'progress' => 25,
                'budget' => '$75,000',
                'due_date' => '2024-03-30'
            ],
            [
                'name' => 'Database Migration',
                'status' => 'Completed',
                'progress' => 100,
                'budget' => '$25,000',
                'due_date' => '2024-01-20'
            ]
        ];
    }

    /**
     * Get tasks data
     */
    protected function getTasksData(ReportSchedule $schedule): array
    {
        return [
            [
                'title' => 'Design System Implementation',
                'project' => 'Website Redesign',
                'assignee' => 'John Doe',
                'status' => 'In Progress',
                'priority' => 'High',
                'due_date' => '2024-02-10'
            ],
            [
                'title' => 'API Development',
                'project' => 'Mobile App Development',
                'assignee' => 'Jane Smith',
                'status' => 'Pending',
                'priority' => 'Medium',
                'due_date' => '2024-02-20'
            ],
            [
                'title' => 'Testing & QA',
                'project' => 'Database Migration',
                'assignee' => 'Mike Johnson',
                'status' => 'Completed',
                'priority' => 'High',
                'due_date' => '2024-01-15'
            ]
        ];
    }

    /**
     * Get team data
     */
    protected function getTeamData(ReportSchedule $schedule): array
    {
        return [
            [
                'name' => 'John Doe',
                'role' => 'Project Manager',
                'tasks_completed' => 45,
                'productivity_score' => 92,
                'last_active' => '2024-01-25'
            ],
            [
                'name' => 'Jane Smith',
                'role' => 'Developer',
                'tasks_completed' => 38,
                'productivity_score' => 88,
                'last_active' => '2024-01-25'
            ],
            [
                'name' => 'Mike Johnson',
                'role' => 'QA Engineer',
                'tasks_completed' => 42,
                'productivity_score' => 95,
                'last_active' => '2024-01-24'
            ]
        ];
    }

    /**
     * Get financial data
     */
    protected function getFinancialData(ReportSchedule $schedule): array
    {
        return [
            [
                'project' => 'Website Redesign',
                'budget_allocated' => '$50,000',
                'budget_spent' => '$37,500',
                'budget_remaining' => '$12,500',
                'utilization' => '75%'
            ],
            [
                'project' => 'Mobile App Development',
                'budget_allocated' => '$75,000',
                'budget_spent' => '$18,750',
                'budget_remaining' => '$56,250',
                'utilization' => '25%'
            ],
            [
                'project' => 'Database Migration',
                'budget_allocated' => '$25,000',
                'budget_spent' => '$25,000',
                'budget_remaining' => '$0',
                'utilization' => '100%'
            ]
        ];
    }

    /**
     * Send report email
     */
    protected function sendReportEmail(ReportSchedule $schedule, string $filePath, EmailService $emailService): void
    {
        $fileInfo = Storage::get($filePath);
        $fileName = basename($filePath);

        foreach ($schedule->recipients as $recipient) {
            $emailService->sendScheduledReport([
                'to' => $recipient,
                'subject' => $schedule->name . ' - ' . now()->format('M d, Y'),
                'schedule' => $schedule,
                'attachment' => [
                    'content' => $fileInfo,
                    'filename' => $fileName,
                    'mime_type' => $this->getMimeType($schedule->format)
                ]
            ]);
        }
    }

    /**
     * Get MIME type for file format
     */
    protected function getMimeType(string $format): string
    {
        return match (strtolower($format)) {
            'pdf' => 'application/pdf',
            'excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv',
            'json' => 'application/json',
            default => 'application/octet-stream'
        };
    }
}
