<?php declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model ProjectBudgetLine - Quản lý dòng ngân sách dự án
 * 
 * Round 219: Core Contracts & Budget (Backend-first)
 * 
 * @property string $id ULID primary key
 * @property string $tenant_id Tenant ID
 * @property string $project_id Project ID
 * @property string|null $cost_category Cost category (e.g., 'structure', 'mep', 'interior')
 * @property string|null $cost_code Cost code
 * @property string $description Description
 * @property string|null $unit Unit of measurement
 * @property float|null $quantity Quantity
 * @property float|null $unit_price_budget Unit price budget
 * @property float $amount_budget Budget amount
 * @property array|null $metadata Additional metadata
 * @property string|null $created_by Creator user ID
 * @property string|null $updated_by Last updater user ID
 */
class ProjectBudgetLine extends Model
{
    use HasUlids, HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'project_budget_lines';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'tenant_id',
        'project_id',
        'cost_category',
        'cost_code',
        'description',
        'unit',
        'quantity',
        'unit_price_budget',
        'amount_budget',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price_budget' => 'decimal:2',
        'amount_budget' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Relationship: Budget line belongs to project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Scope: Filter by project
     */
    public function scopeForProject($query, string $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope: Filter by cost category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('cost_category', $category);
    }
}
