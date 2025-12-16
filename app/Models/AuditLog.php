<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AuditLog Model - System-wide audit trail
 * 
 * Round 235: Audit Log Framework
 * 
 * @property string $id ULID primary key
 * @property string|null $tenant_id Tenant ID (nullable for system-wide actions)
 * @property string|null $user_id User ID (nullable for system actions)
 * @property string $action Action name (e.g., 'role.created', 'co.approved')
 * @property string|null $entity_type Entity type (e.g., 'Role', 'User', 'Contract')
 * @property string|null $entity_id Entity ID (ULID)
 * @property string|null $project_id Project ID (ULID, for project-related actions)
 * @property array|null $payload_before State before change
 * @property array|null $payload_after State after change
 * @property string|null $ip_address IP address
 * @property string|null $user_agent User agent
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AuditLog extends Model
{
    use HasUlids, HasFactory;

    /**
     * Cấu hình ULID primary key
     */
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'project_id',
        'payload_before',
        'payload_after',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'payload_before' => 'array',
        'payload_after' => 'array',
    ];

    /**
     * Relationship with User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship with Project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Relationship with Tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope: Filter by entity type
     */
    public function scopeByEntityType($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    /**
     * Scope: Filter by action
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope: Filter by user
     */
    public function scopeByUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Filter by tenant
     */
    public function scopeByTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: Filter by project
     */
    public function scopeByProject($query, string $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope: Filter by date range
     */
    public function scopeByDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get formatted action description
     */
    public function getFormattedActionAttribute(): string
    {
        return ucfirst(str_replace('.', ' ', $this->action));
    }

    /**
     * Get entity name
     */
    public function getEntityNameAttribute(): ?string
    {
        if (!$this->entity_type) {
            return null;
        }
        return ucfirst(str_replace('_', ' ', $this->entity_type));
    }
}