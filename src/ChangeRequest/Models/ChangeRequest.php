<?php declare(strict_types=1);

namespace Src\ChangeRequest\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Support\Facades\Event;
use Src\CoreProject\Models\Project;
use Src\RBAC\Models\User;
use Src\ChangeRequest\Events\ChangeRequestApproved;
use Src\ChangeRequest\Events\ChangeRequestRejected;
use Src\ChangeRequest\Events\ChangeRequestStatusChanged;

/**
 * Model ChangeRequest để quản lý các yêu cầu thay đổi
 * 
 * @property string $project_id
 * @property string $code
 * @property string $title
 * @property string $description
 * @property string $status
 * @property int $impact_days
 * @property float $impact_cost
 * @property array $impact_kpi
 * @property string $created_by
 * @property string|null $decided_by
 * @property \Carbon\Carbon|null $decided_at
 * @property string|null $decision_note
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ChangeRequest extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'change_requests';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Các trạng thái của change request
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_AWAITING_APPROVAL = 'awaiting_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /**
     * Danh sách các trạng thái hợp lệ
     */
    public const VALID_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_AWAITING_APPROVAL,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    /**
     * Các mức độ ưu tiên change request
     */
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH,
        self::PRIORITY_CRITICAL,
    ];

    /**
     * Các trạng thái có thể chuyển đổi
     */
    public const STATUS_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_AWAITING_APPROVAL],
        self::STATUS_AWAITING_APPROVAL => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_DRAFT],
        self::STATUS_APPROVED => [],
        self::STATUS_REJECTED => [self::STATUS_DRAFT],
    ];

    protected $fillable = [
        'tenant_id',
        'project_id',
        'task_id',
        'change_number',
        'code',
        'title',
        'description',
        'change_type',
        'priority',
        'status',
        'impact_level',
        'impact_days',
        'impact_cost',
        'impact_kpi',
        'requested_by',
        'assigned_to',
        'approved_by',
        'rejected_by',
        'created_by',
        'requested_at',
        'decided_by',
        'decided_at',
        'decision_note',
        'estimated_cost',
        'actual_cost',
        'estimated_days',
        'actual_days',
        'approval_notes',
        'rejection_reason',
        'implementation_notes',
        'attachments',
        'impact_analysis',
        'risk_assessment',
    ];

    protected $casts = [
        'impact_days' => 'integer',
        'impact_cost' => 'decimal:2',
        'impact_kpi' => 'array',
        'decided_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Quan hệ với Project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Quan hệ với User (người tạo)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Quan hệ với User (người quyết định)
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * Scope để lọc theo trạng thái
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope để lọc theo dự án
     */
    public function scopeForProject(Builder $query, string $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope để lấy các CR đang chờ approval
     */
    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AWAITING_APPROVAL);
    }

    /**
     * Scope để lấy các CR đã được approve
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope để lấy các CR bị reject
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Kiểm tra xem có thể chuyển sang trạng thái mới không
     */
    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::STATUS_TRANSITIONS[$this->status] ?? []);
    }

    /**
     * Chuyển sang trạng thái awaiting approval
     */
    public function submitForApproval(): bool
    {
        if (!$this->canTransitionTo(self::STATUS_AWAITING_APPROVAL)) {
            return false;
        }

        $this->status = self::STATUS_AWAITING_APPROVAL;
        $result = $this->save();

        if ($result) {
            Event::dispatch(new ChangeRequestStatusChanged($this, self::STATUS_DRAFT, self::STATUS_AWAITING_APPROVAL));
        }

        return $result;
    }

    /**
     * Approve change request
     */
    public function approve(string $deciderId, ?string $note = null): bool
    {
        if (!$this->canTransitionTo(self::STATUS_APPROVED)) {
            return false;
        }

        $oldStatus = $this->status;
        $this->status = self::STATUS_APPROVED;
        $this->decided_by = $deciderId;
        $this->decided_at = now();
        $this->decision_note = $note;
        
        $result = $this->save();

        if ($result) {
            Event::dispatch(new ChangeRequestApproved($this));
            Event::dispatch(new ChangeRequestStatusChanged($this, $oldStatus, self::STATUS_APPROVED));
        }

        return $result;
    }

    /**
     * Reject change request
     */
    public function reject(string $deciderId, ?string $note = null): bool
    {
        if (!$this->canTransitionTo(self::STATUS_REJECTED)) {
            return false;
        }

        $oldStatus = $this->status;
        $this->status = self::STATUS_REJECTED;
        $this->decided_by = $deciderId;
        $this->decided_at = now();
        $this->decision_note = $note;
        
        $result = $this->save();

        if ($result) {
            Event::dispatch(new ChangeRequestRejected($this));
            Event::dispatch(new ChangeRequestStatusChanged($this, $oldStatus, self::STATUS_REJECTED));
        }

        return $result;
    }

    /**
     * Kiểm tra xem CR có đang pending không
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_AWAITING_APPROVAL;
    }

    /**
     * Kiểm tra xem CR có được approve không
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Kiểm tra xem CR có bị reject không
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Kiểm tra xem CR có đang ở draft không
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Tạo mã CR tự động
     */
    public static function generateCode(string $projectId): string
    {
        $project = Project::find($projectId);
        $count = static::where('project_id', $projectId)->count() + 1;
        
        $projectCode = $project ? strtoupper(substr($project->name, 0, 3)) : 'PRJ';
        
        return sprintf('%s-CR-%04d', $projectCode, $count);
    }

    /**
     * Tính tổng impact cost của tất cả CR đã approve trong project
     */
    public static function getTotalApprovedImpactCost(string $projectId): float
    {
        return static::forProject($projectId)
                    ->approved()
                    ->sum('impact_cost');
    }

    /**
     * Tính tổng impact days của tất cả CR đã approve trong project
     */
    public static function getTotalApprovedImpactDays(string $projectId): int
    {
        return static::forProject($projectId)
                    ->approved()
                    ->sum('impact_days');
    }

    /**
     * Quan hệ với CrLink (các liên kết)
     */
    public function links(): HasMany
    {
        return $this->hasMany(CrLink::class);
    }

    /**
     * Quan hệ với Task thông qua CrLink
     */
    public function linkedTasks()
    {
        return $this->links()->forLinkedType(CrLink::LINKED_TYPE_TASK);
    }

    /**
     * Quan hệ với Document thông qua CrLink
     */
    public function linkedDocuments()
    {
        return $this->links()->forLinkedType(CrLink::LINKED_TYPE_DOCUMENT);
    }

    /**
     * Quan hệ với Component thông qua CrLink
     */
    public function linkedComponents()
    {
        return $this->links()->forLinkedType(CrLink::LINKED_TYPE_COMPONENT);
    }

    /**
     * Lấy tất cả entity được liên kết
     */
    public function getLinkedEntities(): array
    {
        return CrLink::getLinkedEntities($this->id);
    }
}
