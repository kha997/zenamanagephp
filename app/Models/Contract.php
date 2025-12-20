<?php declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\ContractBudgetLine;
use App\Models\ContractExpense;

/**
 * Model Contract - Quản lý hợp đồng dự án
 * 
 * Round 219: Core Contracts & Budget (Backend-first)
 * 
 * @property string $id ULID của contract (primary key)
 * @property string $tenant_id Tenant ID
 * @property string $project_id ID dự án (ULID)
 * @property string $code Số hợp đồng / mã HĐ
 * @property string $name Tên hợp đồng
 * @property string|null $type Loại hợp đồng ('main', 'subcontract', 'supply', 'consultant')
 * @property string|null $party_name Tên đối tác
 * @property string $currency Currency (default 'VND')
 * @property float|null $base_amount Giá trị hợp đồng gốc
 * @property float|null $vat_percent VAT percentage
 * @property float|null $total_amount_with_vat Tổng giá trị có VAT
 * @property float|null $retention_percent Retention percentage
 * @property string $status Trạng thái hợp đồng
 * @property \Carbon\Carbon|null $start_date Ngày bắt đầu
 * @property \Carbon\Carbon|null $end_date Ngày kết thúc
 * @property \Carbon\Carbon|null $signed_at Ngày ký hợp đồng
 * @property array|null $metadata Additional metadata
 * @property string|null $notes Ghi chú
 */
class Contract extends Model
{
    use HasUlids, HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'contracts';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'tenant_id',
        'project_id',
        'code',
        'name',
        'type',
        'party_name',
        'currency',
        'base_amount',
        'vat_percent',
        'total_amount_with_vat',
        'retention_percent',
        'status',
        'start_date',
        'end_date',
        'signed_at',
        'metadata',
        'notes',
        'created_by_id',
        'updated_by_id',
        // Legacy fields for backward compatibility
        'contract_number',
        'title',
        'description',
        'total_value',
        'version',
        'signed_date',
        'terms',
        'client_name',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'vat_percent' => 'decimal:2',
        'total_amount_with_vat' => 'decimal:2',
        'retention_percent' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'signed_at' => 'datetime',
        'metadata' => 'array',
        // Legacy casts
        'total_value' => 'float',
        'version' => 'integer',
        'signed_date' => 'date',
        'terms' => 'array',
    ];

    protected $attributes = [
        'status' => 'draft',
        'currency' => 'VND',
    ];

    /**
     * Relationship: Contract thuộc về project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Relationship: Contract có nhiều contract lines
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ContractLine::class, 'contract_id');
    }

    /**
     * Relationship: Contract có nhiều change orders
     * 
     * Round 220: Change Orders for Contracts
     */
    public function changeOrders(): HasMany
    {
        return $this->hasMany(ChangeOrder::class, 'contract_id');
    }

    /**
     * Relationship: Contract có nhiều budget lines
     */
    public function budgetLines(): HasMany
    {
        return $this->hasMany(ContractBudgetLine::class, 'contract_id');
    }

    /**
     * Relationship: Contract có nhiều expenses
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(ContractExpense::class, 'contract_id');
    }

    /**
     * Relationship: Contract có nhiều payment certificates
     * 
     * Round 221: Payment Certificates & Payments (Actual Cost)
     */
    public function paymentCertificates(): HasMany
    {
        return $this->hasMany(ContractPaymentCertificate::class, 'contract_id');
    }

    /**
     * Relationship: Contract có nhiều actual payments
     * 
     * Round 221: Payment Certificates & Payments (Actual Cost)
     */
    public function actualPayments(): HasMany
    {
        return $this->hasMany(ContractActualPayment::class, 'contract_id');
    }

    /**
     * Relationship: Contract có nhiều task compensations (legacy)
     */
    public function taskCompensations(): HasMany
    {
        return $this->hasMany(\App\Models\TaskCompensation::class);
    }

    /**
     * Scope: Lọc theo project
     */
    public function scopeForProject($query, string $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope: Lọc theo status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Lấy contract active (status = 'active')
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Lấy contract mới nhất theo version
     */
    public function scopeLatestVersion($query)
    {
        return $query->orderBy('version', 'desc');
    }

    /**
     * Kiểm tra xem contract có thể được cập nhật không
     */
    public function canBeUpdated(): bool
    {
        return in_array($this->status, ['draft', 'active']);
    }

    /**
     * Kiểm tra xem contract có thể được apply compensation không
     */
    public function canApplyCompensation(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get current contract amount including approved change orders
     * 
     * Round 220: Change Orders for Contracts
     * 
     * Computed as: base_amount + sum(approved CO amount_delta)
     * 
     * @return float
     */
    public function getCurrentAmountAttribute(): float
    {
        $approvedDelta = $this->changeOrders()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant_id)
            ->where('status', 'approved')
            ->sum('amount_delta');

        $baseAmount = $this->base_amount ? (float) $this->base_amount : 0.0;

        return $baseAmount + (float) $approvedDelta;
    }

    /**
     * Get total certified amount from approved payment certificates
     * 
     * Round 221: Payment Certificates & Payments (Actual Cost)
     * 
     * @return float
     */
    public function getTotalCertifiedAmountAttribute(): float
    {
        return (float) $this->paymentCertificates()
            ->where('status', 'approved')
            ->sum('amount_payable');
    }

    /**
     * Get total paid amount from actual payments
     * 
     * Round 221: Payment Certificates & Payments (Actual Cost)
     * 
     * @return float
     */
    public function getTotalPaidAmountAttribute(): float
    {
        return (float) $this->actualPayments()
            ->whereNotNull('paid_date')
            ->whereNotNull('amount_paid')
            ->sum('amount_paid');
    }

    /**
     * Get outstanding amount (current_amount - total_paid_amount)
     * 
     * Round 221: Payment Certificates & Payments (Actual Cost)
     * 
     * @return float
     */
    public function getOutstandingAmountAttribute(): float
    {
        $current = $this->current_amount ?? 0.0;
        $paid = $this->total_paid_amount ?? 0.0;

        return $current - $paid;
    }
}
