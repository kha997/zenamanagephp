<?php declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model CostApprovalPolicy - Quản lý chính sách phê duyệt chi phí
 * 
 * Round 239: Cost Approval Policies (Phase 1 - Thresholds & Blocking)
 * 
 * @property int $id
 * @property string $tenant_id Tenant ID
 * @property float|null $co_dual_threshold_amount Amount threshold for Change Order approvals
 * @property float|null $certificate_dual_threshold_amount Amount threshold for Certificate approvals
 * @property float|null $payment_dual_threshold_amount Amount threshold for Payment approvals
 * @property float|null $over_budget_threshold_percent Over-budget percentage threshold
 */
class CostApprovalPolicy extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'cost_approval_policies';
    
    protected $fillable = [
        'tenant_id',
        'co_dual_threshold_amount',
        'certificate_dual_threshold_amount',
        'payment_dual_threshold_amount',
        'over_budget_threshold_percent',
    ];

    protected $casts = [
        'co_dual_threshold_amount' => 'decimal:2',
        'certificate_dual_threshold_amount' => 'decimal:2',
        'payment_dual_threshold_amount' => 'decimal:2',
        'over_budget_threshold_percent' => 'decimal:2',
    ];
}
