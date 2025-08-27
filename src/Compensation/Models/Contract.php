<?php declare(strict_types=1);

namespace Src\Compensation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Src\Foundation\Traits\HasTimestamps;
use Src\CoreProject\Models\Project;

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
 */
class Contract extends Model
{
    use HasUlids, HasTimestamps;

    protected $table = 'contracts';
    
    // Cấu hình ULID primary key
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'project_id',
        'contract_number',
        'title',
        'description',
        'total_value',
        'version',
        'status',
        'start_date',
        'end_date',
        'signed_date',
        'terms',
        'client_name',
        'notes',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'total_value' => 'float',
        'version' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'signed_date' => 'date',
        'terms' => 'array'
    ];

    protected $attributes = [
        'total_value' => 0.0,
        'version' => 1,
        'status' => 'draft'
    ];

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