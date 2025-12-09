<?php declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model ChangeOrder - Quản lý Change Orders (CO) cho contracts
 * 
 * Round 220: Change Orders for Contracts
 * 
 * @property string $id ULID của change order (primary key)
 * @property string $tenant_id Tenant ID
 * @property string $project_id ID dự án (ULID)
 * @property string $contract_id ID hợp đồng (ULID)
 * @property string $code CO number (e.g. CO-001)
 * @property string $title Short description/title
 * @property string|null $reason Reason for change (e.g. 'design_change', 'site_condition', 'client_request')
 * @property string $status Status ('draft', 'proposed', 'approved', 'rejected', 'cancelled')
 * @property float $amount_delta Total increase (+) or decrease (-) of contract value
 * @property \Carbon\Carbon|null $effective_date Effective date
 * @property array|null $metadata Additional metadata
 * @property string|null $created_by Creator user ID
 * @property string|null $updated_by Last updater user ID
 */
class ChangeOrder extends Model
{
    use HasUlids, HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'change_orders';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'tenant_id',
        'project_id',
        'contract_id',
        'code',
        'title',
        'reason',
        'status',
        'amount_delta',
        'effective_date',
        'metadata',
        'created_by',
        'updated_by',
        // Round 241: Dual approval fields
        'first_approved_by',
        'first_approved_at',
        'second_approved_by',
        'second_approved_at',
        'requires_dual_approval',
    ];

    protected $casts = [
        'amount_delta' => 'decimal:2',
        'effective_date' => 'date',
        'metadata' => 'array',
        'first_approved_at' => 'datetime',
        'second_approved_at' => 'datetime',
        'requires_dual_approval' => 'boolean',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * Relationship: Change order belongs to project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Relationship: Change order belongs to contract
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    /**
     * Relationship: Change order has many change order lines
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ChangeOrderLine::class, 'change_order_id');
    }

    /**
     * Scope: Filter by contract
     */
    public function scopeForContract($query, string $contractId)
    {
        return $query->where('contract_id', $contractId);
    }

    /**
     * Scope: Filter by project
     */
    public function scopeForProject($query, string $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
