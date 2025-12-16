<?php declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model ChangeOrderLine - Quản lý dòng Change Order (line items trong CO)
 * 
 * Round 220: Change Orders for Contracts
 * 
 * @property string $id ULID primary key
 * @property string $tenant_id Tenant ID
 * @property string $project_id Project ID (denormalized)
 * @property string $contract_id Contract ID
 * @property string $change_order_id Change Order ID
 * @property string|null $contract_line_id Contract line ID (if adjusting an existing contract line)
 * @property string|null $budget_line_id Budget line ID (if mapping to a budget line)
 * @property string|null $item_code Item code
 * @property string $description Description
 * @property string|null $unit Unit of measurement
 * @property float|null $quantity_delta Quantity delta
 * @property float|null $unit_price_delta Unit price delta
 * @property float $amount_delta Amount delta (required)
 * @property array|null $metadata Additional metadata
 * @property string|null $created_by Creator user ID
 * @property string|null $updated_by Last updater user ID
 */
class ChangeOrderLine extends Model
{
    use HasUlids, HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'change_order_lines';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'tenant_id',
        'project_id',
        'contract_id',
        'change_order_id',
        'contract_line_id',
        'budget_line_id',
        'item_code',
        'description',
        'unit',
        'quantity_delta',
        'unit_price_delta',
        'amount_delta',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'quantity_delta' => 'decimal:2',
        'unit_price_delta' => 'decimal:2',
        'amount_delta' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Relationship: Change order line belongs to change order
     */
    public function changeOrder(): BelongsTo
    {
        return $this->belongsTo(ChangeOrder::class, 'change_order_id');
    }

    /**
     * Relationship: Change order line belongs to contract
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    /**
     * Relationship: Change order line belongs to project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Relationship: Change order line may map to contract line
     */
    public function contractLine(): BelongsTo
    {
        return $this->belongsTo(ContractLine::class, 'contract_line_id');
    }

    /**
     * Relationship: Change order line may map to budget line
     */
    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(ProjectBudgetLine::class, 'budget_line_id');
    }

    /**
     * Scope: Filter by change order
     */
    public function scopeForChangeOrder($query, string $changeOrderId)
    {
        return $query->where('change_order_id', $changeOrderId);
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
