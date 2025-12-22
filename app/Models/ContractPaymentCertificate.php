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
 * Model ContractPaymentCertificate - Quản lý chứng chỉ nghiệm thu thanh toán (IPC/CC)
 * 
 * Round 221: Payment Certificates & Payments (Actual Cost)
 * 
 * @property string $id ULID của certificate (primary key)
 * @property string $tenant_id Tenant ID
 * @property string $project_id ID dự án (ULID)
 * @property string $contract_id ID hợp đồng (ULID)
 * @property string $code Certificate number (e.g. IPC-01, CC-05)
 * @property string|null $title e.g. "Interim Payment Certificate #01"
 * @property \Carbon\Carbon|null $period_start
 * @property \Carbon\Carbon|null $period_end
 * @property string $status 'draft', 'submitted', 'approved', 'rejected', 'cancelled'
 * @property float $amount_before_retention Tổng giá trị nghiệm thu trước retention
 * @property float|null $retention_percent_override Nếu null thì dùng contract.retention_percent
 * @property float $retention_amount Số tiền bị giữ lại
 * @property float $amount_payable Số được đề nghị thanh toán
 * @property array|null $metadata Additional metadata
 * @property string|null $created_by Creator user ID
 * @property string|null $updated_by Last updater user ID
 */
class ContractPaymentCertificate extends Model
{
    use HasUlids, HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'contract_payment_certificates';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'tenant_id',
        'project_id',
        'contract_id',
        'code',
        'title',
        'period_start',
        'period_end',
        'status',
        'amount_before_retention',
        'retention_percent_override',
        'retention_amount',
        'amount_payable',
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
        'period_start' => 'date',
        'period_end' => 'date',
        'amount_before_retention' => 'decimal:2',
        'retention_percent_override' => 'decimal:2',
        'retention_amount' => 'decimal:2',
        'amount_payable' => 'decimal:2',
        'metadata' => 'array',
        'first_approved_at' => 'datetime',
        'second_approved_at' => 'datetime',
        'requires_dual_approval' => 'boolean',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * Relationship: Certificate belongs to project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Relationship: Certificate belongs to contract
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    /**
     * Relationship: Certificate has many payments
     * 
     * Round 221: Payments can optionally link to certificates
     */
    public function payments(): HasMany
    {
        return $this->hasMany(ContractActualPayment::class, 'certificate_id');
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
