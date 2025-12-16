<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Models\Concerns\BelongsToTenant;

/**
 * Outbox Model
 * 
 * Stores events that need to be published reliably.
 * Uses transactional outbox pattern to ensure events are published
 * even if the main transaction succeeds but event publishing fails.
 */
class Outbox extends Model
{
    use HasUlids, BelongsToTenant;

    protected $table = 'outbox';

    protected $fillable = [
        'tenant_id',
        'event_type',
        'event_name',
        'payload',
        'status',
        'retry_count',
        'processed_at',
        'error_message',
        'correlation_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    /**
     * Scope for pending events
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for failed events that can be retried
     */
    public function scopeFailedRetryable($query, int $maxRetries = 3)
    {
        return $query->where('status', self::STATUS_FAILED)
            ->where('retry_count', '<', $maxRetries);
    }

    /**
     * Mark as processing
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
        ]);
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1,
        ]);
    }
}
