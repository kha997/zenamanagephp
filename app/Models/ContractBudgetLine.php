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
 * Model ContractBudgetLine - Quản lý dự toán chi phí theo hạng mục cho hợp đồng
 * 
 * Round 43: Cost Control / Budget vs Actual (Backend-only Foundation)
 * 
 * @property string $id ULID của budget line (primary key)
 * @property string $tenant_id ID tenant
 * @property string $contract_id ID hợp đồng
 * @property string|null $code Mã hạng mục (VD: CC-001)
 * @property string $name Mô tả ngắn: "Bê tông móng", "Nhôm kính",...
 * @property string|null $category Vật tư / nhân công / thầu phụ / khác
 * @property string|null $cost_type Optional: direct, indirect, contingency,...
 * @property float|null $quantity Số lượng
 * @property string|null $unit Đơn vị (m3, m2, bộ, công,...)
 * @property float|null $unit_price Đơn giá
 * @property float|null $total_amount Tổng tiền (có thể auto = quantity * unit_price)
 * @property string|null $currency Tiền tệ (default = contract.currency nếu null)
 * @property string|null $wbs_code Link tới WBS nếu sau này cần
 * @property string $status Trạng thái (planned/approved/locked/cancelled)
 * @property string|null $notes Ghi chú
 * @property int $sort_order Thứ tự sắp xếp
 * @property string|null $created_by_id ID người tạo
 * @property string|null $updated_by_id ID người cập nhật
 */
class ContractBudgetLine extends Model
{
    use HasUlids, HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'contract_budget_lines';
    
    // Cấu hình ULID primary key
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'tenant_id',
        'contract_id',
        'code',
        'name',
        'category',
        'cost_type',
        'quantity',
        'unit',
        'unit_price',
        'total_amount',
        'currency',
        'wbs_code',
        'status',
        'notes',
        'sort_order',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'status' => 'planned',
        'sort_order' => 0,
    ];

    /**
     * Relationship: Budget line thuộc về tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relationship: Budget line thuộc về contract
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
     * Scope: Lọc các line active (không cancelled và không bị soft delete)
     */
    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'cancelled');
    }

    /**
     * Scope: Sắp xếp theo sort_order và name
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Resolve route model binding with tenant isolation
     * 
     * Excludes soft-deleted records to prevent accessing deleted budget lines via direct routes.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        return $this->where($field ?? $this->getRouteKeyName(), $value)
                   ->where('tenant_id', $user->tenant_id)
                   ->whereNull('deleted_at')
                   ->first();
    }
}
