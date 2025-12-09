<?php declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model ContractActualPayment - Quản lý thanh toán thực tế (tiền thực chi)
 * 
 * Round 221: Payment Certificates & Payments (Actual Cost)
 * 
 * Note: This model uses the contract_payments table with Round 221 structure.
 * The existing ContractPayment model (Round 36) uses the same table for payment schedules.
 * 
 * @property string $id ULID của payment (primary key)
 * @property string $tenant_id Tenant ID
 * @property string $project_id ID dự án (ULID)
 * @property string $contract_id ID hợp đồng (ULID)
 * @property string|null $certificate_id Optional link to contract_payment_certificates
 * @property \Carbon\Carbon $paid_date Actual pay date
 * @property float $amount_paid Số tiền thực trả
 * @property string|null $currency Currency (default same as contract)
 * @property string|null $payment_method e.g. 'bank_transfer', 'cash', 'offset'
 * @property string|null $reference_no Bank ref / internal ref
 * @property array|null $metadata Additional metadata
 * @property string|null $created_by Creator user ID
 * @property string|null $updated_by Last updater user ID
 */
class ContractActualPayment extends Model
{
    use HasUlids, HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'contract_payments';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'tenant_id',
        'project_id',
        'contract_id',
        'certificate_id',
        'paid_date',
        'amount_paid',
        'currency',
        'payment_method',
        'reference_no',
        'metadata',
        'created_by',
        'updated_by',
        // Round 241: Dual approval fields
        'first_approved_by',
        'first_approved_at',
        'second_approved_by',
        'second_approved_at',
        'requires_dual_approval',
        // Round 36 fields (nullable to support both models using same table)
        'name',
        'due_date',
        'amount',
        'code',
        'type',
        'status',
        'paid_at',
        'notes',
        'sort_order',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'paid_date' => 'date',
        'amount_paid' => 'decimal:2',
        'metadata' => 'array',
        'first_approved_at' => 'datetime',
        'second_approved_at' => 'datetime',
        'requires_dual_approval' => 'boolean',
    ];

    /**
     * Relationship: Payment belongs to project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Relationship: Payment belongs to contract
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    /**
     * Relationship: Payment optionally belongs to certificate
     */
    public function certificate(): BelongsTo
    {
        return $this->belongsTo(ContractPaymentCertificate::class, 'certificate_id');
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
     * Scope: Filter by certificate
     */
    public function scopeForCertificate($query, string $certificateId)
    {
        return $query->where('certificate_id', $certificateId);
    }

    /**
     * Scope: Filter only Round 221 actual payments (has paid_date and amount_paid)
     * This helps distinguish from Round 36 payment schedules
     */
    public function scopeActualPayments($query)
    {
        return $query->whereNotNull('paid_date')
            ->whereNotNull('amount_paid');
    }
}
