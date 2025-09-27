<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class BulkOperationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes
    public $tries = 2;
    public $backoff = [60, 120];

    protected $userId;
    protected $operation;
    protected $modelType;
    protected $recordIds;
    protected $data;
    protected $options;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $userId,
        string $operation,
        string $modelType,
        array $recordIds,
        array $data = [],
        array $options = []
    ) {
        $this->userId = $userId;
        $this->operation = $operation;
        $this->modelType = $modelType;
        $this->recordIds = $recordIds;
        $this->data = $data;
        $this->options = $options;
        $this->onQueue('bulk-operations');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $user = User::find($this->userId);
            if (!$user) {
                Log::warning('BulkOperationJob: User not found', ['user_id' => $this->userId]);
                return;
            }

            Log::info('Starting bulk operation', [
                'user_id' => $this->userId,
                'operation' => $this->operation,
                'model_type' => $this->modelType,
                'record_count' => count($this->recordIds)
            ]);

            // Execute the bulk operation
            $result = $this->executeBulkOperation($user);

            // Log the result
            Log::info('Bulk operation completed', [
                'user_id' => $this->userId,
                'operation' => $this->operation,
                'model_type' => $this->modelType,
                'success_count' => $result['success_count'],
                'error_count' => $result['error_count'],
                'errors' => $result['errors']
            ]);

            // Notify user of completion
            $this->notifyUser($user, $result);

        } catch (\Exception $e) {
            Log::error('Bulk operation failed', [
                'user_id' => $this->userId,
                'operation' => $this->operation,
                'model_type' => $this->modelType,
                'error' => $e->getMessage()
            ]);

            // Notify user of failure
            $this->notifyUserOfFailure($user ?? null, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Execute the bulk operation
     */
    protected function executeBulkOperation(User $user): array
    {
        $modelClass = $this->getModelClass();
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        // Start database transaction
        DB::beginTransaction();

        try {
            foreach ($this->recordIds as $recordId) {
                try {
                    $this->processRecord($modelClass, $recordId, $user);
                    $successCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = [
                        'record_id' => $recordId,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        return [
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $errors
        ];
    }

    /**
     * Process individual record
     */
    protected function processRecord(string $modelClass, int $recordId, User $user): void
    {
        $record = $modelClass::find($recordId);

        if (!$record) {
            throw new \Exception("Record not found: {$recordId}");
        }

        // Check tenant isolation
        if (isset($record->tenant_id) && $record->tenant_id !== $user->tenant_id) {
            throw new \Exception("Access denied: Record belongs to different tenant");
        }

        // Check permissions
        if (!$this->hasPermission($user, $record)) {
            throw new \Exception("Permission denied for record: {$recordId}");
        }

        // Execute operation
        switch ($this->operation) {
            case 'update':
                $this->updateRecord($record);
                break;
            case 'delete':
                $this->deleteRecord($record);
                break;
            case 'archive':
                $this->archiveRecord($record);
                break;
            case 'restore':
                $this->restoreRecord($record);
                break;
            case 'activate':
                $this->activateRecord($record);
                break;
            case 'deactivate':
                $this->deactivateRecord($record);
                break;
            default:
                throw new \Exception("Unknown operation: {$this->operation}");
        }
    }

    /**
     * Update record
     */
    protected function updateRecord($record): void
    {
        $record->update($this->data);
    }

    /**
     * Delete record
     */
    protected function deleteRecord($record): void
    {
        if (method_exists($record, 'softDelete')) {
            $record->softDelete();
        } else {
            $record->delete();
        }
    }

    /**
     * Archive record
     */
    protected function archiveRecord($record): void
    {
        if (method_exists($record, 'archive')) {
            $record->archive();
        } else {
            $record->update(['status' => 'archived']);
        }
    }

    /**
     * Restore record
     */
    protected function restoreRecord($record): void
    {
        if (method_exists($record, 'restore')) {
            $record->restore();
        } else {
            $record->update(['status' => 'active']);
        }
    }

    /**
     * Activate record
     */
    protected function activateRecord($record): void
    {
        $record->update(['is_active' => true]);
    }

    /**
     * Deactivate record
     */
    protected function deactivateRecord($record): void
    {
        $record->update(['is_active' => false]);
    }

    /**
     * Get model class
     */
    protected function getModelClass(): string
    {
        $modelMap = [
            'project' => \App\Models\Project::class,
            'task' => \App\Models\Task::class,
            'document' => \App\Models\Document::class,
            'user' => \App\Models\User::class,
            'team' => \App\Models\Team::class,
            'file' => \App\Models\File::class,
            'notification' => \App\Models\Notification::class,
        ];

        if (!isset($modelMap[$this->modelType])) {
            throw new \Exception("Unknown model type: {$this->modelType}");
        }

        return $modelMap[$this->modelType];
    }

    /**
     * Check if user has permission for the record
     */
    protected function hasPermission(User $user, $record): bool
    {
        // Admin can do anything
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check specific permissions based on model type
        switch ($this->modelType) {
            case 'project':
                return $user->hasRole('project_manager') || $record->team->contains($user);
            case 'task':
                return $user->hasRole('project_manager') || $record->assignee_id === $user->id;
            case 'document':
                return $user->hasRole('project_manager') || $record->created_by === $user->id;
            case 'user':
                return $user->hasRole('admin');
            case 'team':
                return $user->hasRole('admin') || $user->hasRole('project_manager');
            case 'file':
                return $user->hasRole('project_manager') || $record->user_id === $user->id;
            case 'notification':
                return $record->user_id === $user->id;
            default:
                return false;
        }
    }

    /**
     * Notify user of successful completion
     */
    protected function notifyUser(User $user, array $result): void
    {
        \App\Models\Notification::create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'type' => 'bulk_operation',
            'priority' => 'normal',
            'title' => 'Bulk Operation Completed',
            'body' => "Bulk {$this->operation} operation on {$this->modelType}s completed. Success: {$result['success_count']}, Errors: {$result['error_count']}.",
            'data' => [
                'operation' => $this->operation,
                'model_type' => $this->modelType,
                'result' => $result
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
            'type' => 'bulk_operation',
            'priority' => 'critical',
            'title' => 'Bulk Operation Failed',
            'body' => "Bulk {$this->operation} operation on {$this->modelType}s failed: {$errorMessage}",
            'data' => [
                'operation' => $this->operation,
                'model_type' => $this->modelType,
                'error' => $errorMessage
            ]
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BulkOperationJob: Job failed permanently', [
            'error' => $exception->getMessage(),
            'user_id' => $this->userId,
            'operation' => $this->operation,
            'model_type' => $this->modelType
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
            'bulk-operation',
            'user:' . $this->userId,
            'operation:' . $this->operation,
            'model:' . $this->modelType
        ];
    }
}
