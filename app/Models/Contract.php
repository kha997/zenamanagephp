<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Project;

/**
 * Model Contract - Quản lý hợp đồng dự án
 * 
 * @property string $id ULID của contract (primary key)
 * @property string $project_id ID dự án (ULID)
 * @property string $contract_number Số hợp đồng
 * @property string $title Tiêu đề hợp đồng
 * @property string|null $description Mô tả hợp đồng
 * @property float $total_value Tổng giá trị hợp đồng
 * @property int $version Phiên bản hợp đồng
 * @property string $status Trạng thái hợp đồng
 * @property \Carbon\Carbon|null $start_date Ngày bắt đầu
 * @property \Carbon\Carbon|null $end_date Ngày kết thúc
 * @property \Carbon\Carbon|null $signed_date Ngày ký hợp đồng
 * @property array|null $terms Điều khoản hợp đồng
 * @property string|null $client_name Tên khách hàng
 * @property string|null $notes Ghi chú
 * @property string $code Mã hợp đồng
 * @property float $retention_percent Tỷ lệ giữ lại (%)
 * @property float $advance_amount Số tiền tạm ứng
 * @property float $advance_recovery_percent Tỷ lệ thu hồi tạm ứng (%)
 * @property \App\Models\Boq|null $boq BOQ attached to this contract
 */
class Contract extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'contracts';
    
    // Cấu hình ULID primary key
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'tenant_id',
        'project_id',
        'source_opportunity_id',
        'source_quote_id',
        'source_quote_revision',
        'code',
        'contract_number',
        'title',
        'contract_type',
        'status',
        'currency',
        'total_value',
        'signed_at',
        'start_date',
        'end_date',
        'created_by',
        'description',
        'version',
        'signed_date',
        'terms',
        'client_name',
        'notes',
        'updated_by',
        'retention_percent',
        'advance_amount',
        'advance_recovery_percent',
    ];

    protected $casts = [
        'tenant_id' => 'string',
        'project_id' => 'string',
        'source_opportunity_id' => 'string',
        'source_quote_id' => 'string',
        'source_quote_revision' => 'integer',
        'code' => 'string',
        'status' => 'string',
        'currency' => 'string',
        'total_value' => 'float',
        'version' => 'integer',
        'signed_at' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'signed_date' => 'date',
        'terms' => 'array',
        'retention_percent' => 'float',
        'advance_amount' => 'float',
        'advance_recovery_percent' => 'float',
    ];

    protected $attributes = [
        'status' => 'draft',
        'currency' => 'USD',
        'total_value' => 0.0,
        'version' => 1,
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    public const VALID_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
    ];

    public const TYPE_DESIGN = 'design';
    public const TYPE_CONSTRUCTION = 'construction';
    public const TYPE_OTHER = 'other';

    public const VALID_TYPES = [
        self::TYPE_DESIGN,
        self::TYPE_CONSTRUCTION,
        self::TYPE_OTHER,
    ];

    public function typeLabel(): string
    {
        return match ($this->contract_type) {
            self::TYPE_DESIGN => 'Thiết kế',
            self::TYPE_CONSTRUCTION => 'Thi công',
            default => 'Khác',
        };
    }

    /**
     * Relationship: Contract thuộc về project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Relationship: Contract có nhiều task compensations
     */
    public function taskCompensations(): HasMany
    {
        return $this->hasMany(TaskCompensation::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ContractPayment::class, 'contract_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ContractExpense::class, 'contract_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasOne<Boq, $this> */
    public function boq(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Boq::class, 'contract_id');
    }

    public function getContractNumberAttribute(): ?string
    {
        return $this->attributes['contract_number'] ?? $this->attributes['code'] ?? null;
    }

    public function setContractNumberAttribute(?string $value): void
    {
        $this->attributes['contract_number'] = $value;

        if ($value !== null) {
            $this->attributes['code'] = $value;
        }
    }

    public function getSignedDateAttribute(): ?string
    {
        return $this->attributes['signed_date'] ?? $this->attributes['signed_at'] ?? null;
    }

    public function setSignedDateAttribute(?string $value): void
    {
        $this->attributes['signed_date'] = $value;
        $this->attributes['signed_at'] = $value;
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
}
