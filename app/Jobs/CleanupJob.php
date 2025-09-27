<?php

namespace App\Jobs;

use App\Models\EmailTracking;
use App\Models\File;
use App\Models\Notification;
use App\Models\SearchHistory;
use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanupJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 1; // Only try once for cleanup jobs
    public $uniqueId = 'cleanup-job';

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('cleanup');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting cleanup job...');

        $this->cleanupOldEmailTracking();
        $this->cleanupOldNotifications();
        $this->cleanupOldSearchHistory();
        $this->cleanupOldAuditLogs();
        $this->cleanupOrphanedFiles();
        $this->cleanupTempFiles();
        $this->cleanupExpiredInvitations();

        Log::info('Cleanup job completed successfully');
    }

    /**
     * Clean up old email tracking records
     */
    protected function cleanupOldEmailTracking(): void
    {
        $retentionDays = config('cleanup.email_tracking_retention_days', 90);
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $deletedCount = EmailTracking::where('created_at', '<', $cutoffDate)->delete();

        if ($deletedCount > 0) {
            Log::info("Cleaned up {$deletedCount} old email tracking records");
        }
    }

    /**
     * Clean up old notifications
     */
    protected function cleanupOldNotifications(): void
    {
        $retentionDays = config('cleanup.notifications_retention_days', 30);
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $deletedCount = Notification::where('created_at', '<', $cutoffDate)
            ->where('read_at', '!=', null) // Only delete read notifications
            ->delete();

        if ($deletedCount > 0) {
            Log::info("Cleaned up {$deletedCount} old notifications");
        }
    }

    /**
     * Clean up old search history
     */
    protected function cleanupOldSearchHistory(): void
    {
        $retentionDays = config('cleanup.search_history_retention_days', 7);
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $deletedCount = SearchHistory::where('created_at', '<', $cutoffDate)->delete();

        if ($deletedCount > 0) {
            Log::info("Cleaned up {$deletedCount} old search history records");
        }
    }

    /**
     * Clean up old audit logs
     */
    protected function cleanupOldAuditLogs(): void
    {
        $retentionDays = config('cleanup.audit_logs_retention_days', 365);
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $deletedCount = AuditLog::where('created_at', '<', $cutoffDate)->delete();

        if ($deletedCount > 0) {
            Log::info("Cleaned up {$deletedCount} old audit log records");
        }
    }

    /**
     * Clean up orphaned files
     */
    protected function cleanupOrphanedFiles(): void
    {
        $orphanedFiles = File::whereDoesntHave('project')
            ->whereDoesntHave('task')
            ->whereDoesntHave('document')
            ->where('created_at', '<', Carbon::now()->subDays(30))
            ->get();

        $deletedCount = 0;
        foreach ($orphanedFiles as $file) {
            try {
                // Delete physical file
                if (Storage::disk($file->disk)->exists($file->path)) {
                    Storage::disk($file->disk)->delete($file->path);
                }

                // Delete database record
                $file->delete();
                $deletedCount++;
            } catch (\Exception $e) {
                Log::warning("Failed to delete orphaned file: {$file->id}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        if ($deletedCount > 0) {
            Log::info("Cleaned up {$deletedCount} orphaned files");
        }
    }

    /**
     * Clean up temporary files
     */
    protected function cleanupTempFiles(): void
    {
        $tempDirectories = [
            'temp',
            'exports',
            'uploads/temp'
        ];

        $deletedCount = 0;
        foreach ($tempDirectories as $directory) {
            try {
                $files = Storage::files($directory);
                foreach ($files as $file) {
                    $fileTime = Storage::lastModified($file);
                    if ($fileTime < Carbon::now()->subHours(24)->timestamp) {
                        Storage::delete($file);
                        $deletedCount++;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Failed to cleanup temp directory: {$directory}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        if ($deletedCount > 0) {
            Log::info("Cleaned up {$deletedCount} temporary files");
        }
    }

    /**
     * Clean up expired invitations
     */
    protected function cleanupExpiredInvitations(): void
    {
        $expiredInvitations = \App\Models\Invitation::where('expires_at', '<', Carbon::now())
            ->where('status', 'pending')
            ->get();

        $deletedCount = 0;
        foreach ($expiredInvitations as $invitation) {
            try {
                $invitation->update(['status' => 'expired']);
                $deletedCount++;
            } catch (\Exception $e) {
                Log::warning("Failed to expire invitation: {$invitation->id}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        if ($deletedCount > 0) {
            Log::info("Expired {$deletedCount} invitations");
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Cleanup job failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->uniqueId;
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['cleanup', 'maintenance'];
    }
}
