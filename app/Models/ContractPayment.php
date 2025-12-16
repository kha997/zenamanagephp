<?php declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * Model ContractPayment - Quản lý lịch thanh toán hợp đồng
 * 
 * Round 36: Contract Payment Schedule Backend
 * 
 * @property string $id ULID của payment (primary key)
 * @property string $tenant_id ID tenant
 * @property string $contract_id ID hợp đồng
 * @property string|null $code Mã đợt thanh toán
 * @property string $name Mô tả đợt thanh toán
 * @property string|null $type Loại thanh toán (deposit, milestone, progress, retention, final)
 * @property \Carbon\Carbon $due_date Ngày đến hạn
 * @property float $amount Số tiền
 * @property string $currency Tiền tệ (USD, VND, etc.)
 * @property string $status Trạng thái (planned, due, paid, overdue, cancelled)
 * @property \Carbon\Carbon|null $paid_at Ngày thanh toán thực tế
 * @property string|null $notes Ghi chú
 * @property int $sort_order Thứ tự sắp xếp
 * @property string|null $created_by_id ID người tạo
 * @property string|null $updated_by_id ID người cập nhật
 */
class ContractPayment extends Model
{
    use HasUlids, HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'contract_payments';
    
    // Cấu hình ULID primary key
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'tenant_id',
        'contract_id',
        'code',
        'name',
        'type',
        'due_date',
        'amount',
        'currency',
        'status',
        'paid_at',
        'notes',
        'sort_order',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'status' => 'planned',
        'currency' => 'USD',
        'amount' => 0.0,
        'sort_order' => 0,
    ];

    /**
     * Relationship: Payment thuộc về tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relationship: Payment thuộc về contract
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Relationship: Người tạo
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Relationship: Người cập nhật
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    /**
     * Scope: Lọc theo contract
     */
    public function scopeForContract($query, string $contractId)
    {
        return $query->where('contract_id', $contractId);
    }

    /**
     * Scope: Lọc theo status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Sắp xếp theo sort_order và due_date
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('due_date');
    }

    /**
     * Scope: Lọc các payment overdue
     * 
     * Round 49: Centralized overdue payment logic
     * 
     * Rule: status != 'paid' && status != 'cancelled' && due_date < today && deleted_at IS NULL
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOverdue($query)
    {
        $today = \Carbon\Carbon::today();
        return $query->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->where('due_date', '<', $today)
            ->whereNull('deleted_at');
    }

    /**
     * Resolve route model binding with tenant isolation
     * 
     * Excludes soft-deleted records to prevent accessing deleted payments via direct routes.
     * 
     * Round 46: Hardening & Polish - Soft delete route binding
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $user = Auth::user();
        if (!$user || !$user->tenant_id) {
            return null;
        }

        return $this->where($field ?? $this->getRouteKeyName(), $value)
                   ->where('tenant_id', $user->tenant_id)
                   ->whereNull('deleted_at')
                   ->first();
    }
}
