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
 * Model ContractExpense - Quản lý chi phí thực tế (actual costs) cho hợp đồng
 * 
 * Round 44: Contract Expenses (Actual Costs) - Backend Only
 * 
 * @property string $id ULID của expense (primary key)
 * @property string $tenant_id ID tenant
 * @property string $contract_id ID hợp đồng
 * @property string|null $budget_line_id ID budget line (nếu liên kết)
 * @property string|null $code Mã chi phí (nếu có)
 * @property string $name Tên khoản chi (VD: "Thanh toán nhà thầu A đợt 1")
 * @property string|null $category Nhóm: labor, material, service, other
 * @property string|null $vendor_name Tên NCC/nhà thầu
 * @property float|null $quantity Khối lượng
 * @property float|null $unit_cost Đơn giá
 * @property float|null $amount Tổng tiền (có thể auto = quantity * unit_cost)
 * @property string $currency Tiền tệ (default VND)
 * @property \Carbon\Carbon|null $incurred_at Ngày phát sinh chi phí
 * @property string $status Trạng thái (planned, recorded, approved, paid, cancelled)
 * @property string|null $notes Ghi chú
 * @property int $sort_order Thứ tự sắp xếp
 * @property string|null $created_by_id ID người tạo
 * @property string|null $updated_by_id ID người cập nhật
 */
class ContractExpense extends Model
{
    use HasUlids, HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'contract_expenses';
    
    // Cấu hình ULID primary key
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'tenant_id',
        'contract_id',
        'budget_line_id',
        'code',
        'name',
        'category',
        'vendor_name',
        'quantity',
        'unit_cost',
        'amount',
        'currency',
        'incurred_at',
        'status',
        'notes',
        'sort_order',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'amount' => 'decimal:2',
        'incurred_at' => 'date',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'status' => 'recorded',
        'currency' => 'VND',
        'sort_order' => 0,
    ];

    /**
     * Relationship: Expense thuộc về tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relationship: Expense thuộc về contract
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Relationship: Expense có thể liên kết với budget line
     */
    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(ContractBudgetLine::class, 'budget_line_id');
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
    public function scopeForContract($query, Contract $contract)
    {
        return $query->where('contract_id', $contract->id);
    }

    /**
     * Scope: Lọc các expense active (không cancelled và không bị soft delete)
     */
    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'cancelled');
    }

    /**
     * Scope: Sắp xếp theo sort_order, incurred_at, name
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('incurred_at')->orderBy('name');
    }

    /**
     * Resolve route model binding with tenant isolation
     * 
     * Excludes soft-deleted records to prevent accessing deleted expenses via direct routes.
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

