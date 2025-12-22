<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Models\Concerns\BelongsToTenant;

/**
 * IdempotencyKey Model
 * 
 * Stores idempotency keys for critical operations to prevent duplicate processing.
 */
class IdempotencyKey extends Model
{
    use HasUlids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'idempotency_key',
        'route',
        'method',
        'request_body',
        'response_body',
        'response_status',
        'processed_at',
    ];

    protected $casts = [
        'request_body' => 'array',
        'response_body' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Find or create idempotency key record
     */
    public static function findOrCreate(
        string $idempotencyKey,
        string $route,
        string $method,
        ?string $tenantId = null,
        ?string $userId = null,
        ?array $requestBody = null
    ): self {
        return self::firstOrCreate(
            [
                'idempotency_key' => $idempotencyKey,
            ],
            [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'route' => $route,
                'method' => $method,
                'request_body' => $requestBody,
            ]
        );
    }

    /**
     * Check if key was already processed
     */
    public function isProcessed(): bool
    {
        return $this->processed_at !== null && $this->response_status !== null;
    }

    /**
     * Mark as processed with response
     */
    public function markAsProcessed(array $responseBody, int $responseStatus): void
    {
        $this->update([
            'response_body' => $responseBody,
            'response_status' => $responseStatus,
            'processed_at' => now(),
        ]);
    }

    /**
     * Get cached response if exists
     */
    public function getCachedResponse(): ?array
    {
        if (!$this->isProcessed()) {
            return null;
        }

        return [
            'data' => $this->response_body,
            'status' => $this->response_status,
        ];
    }
}

