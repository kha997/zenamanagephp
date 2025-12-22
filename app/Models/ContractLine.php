<?php declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model ContractLine - Quản lý dòng hợp đồng (BOQ-style line items)
 * 
 * Round 219: Core Contracts & Budget (Backend-first)
 * 
 * @property string $id ULID primary key
 * @property string $tenant_id Tenant ID
 * @property string $contract_id Contract ID
 * @property string $project_id Project ID (denormalized)
 * @property string|null $budget_line_id Budget line ID (if mapped)
 * @property string|null $item_code Item code
 * @property string $description Description
 * @property string|null $unit Unit of measurement
 * @property float $quantity Quantity
 * @property float $unit_price Unit price
 * @property float $amount Amount
 * @property array|null $metadata Additional metadata
 * @property string|null $created_by Creator user ID
 * @property string|null $updated_by Last updater user ID
 */
class ContractLine extends Model
{
    use HasUlids, HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'contract_lines';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'tenant_id',
        'contract_id',
        'project_id',
        'budget_line_id',
        'item_code',
        'description',
        'unit',
        'quantity',
        'unit_price',
        'amount',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Relationship: Contract line belongs to contract
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    /**
     * Relationship: Contract line belongs to project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Relationship: Contract line may map to budget line
     */
    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(ProjectBudgetLine::class, 'budget_line_id');
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
}
