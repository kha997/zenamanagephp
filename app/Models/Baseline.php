<?php declare(strict_types=1);

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Src\ChangeRequest\Models\ChangeRequest;

/**
 * Model Baseline để quản lý các baseline của dự án
 * 
 * @property string $id
 * @property string $project_id
 * @property string $type
 * @property \Carbon\Carbon $start_date
 * @property \Carbon\Carbon $end_date
 * @property float $cost
 * @property string|null $linked_contract_id
 * @property int $version
 * @property string|null $note
 * @property string $created_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Baseline extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'baselines';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Các loại baseline có thể có
     */
    public const TYPE_CONTRACT = 'contract';
    public const TYPE_EXECUTION = 'execution';

    /**
     * Danh sách các loại baseline hợp lệ
     */
    public const VALID_TYPES = [
        self::TYPE_CONTRACT,
        self::TYPE_EXECUTION,
    ];

    protected $fillable = [
        'project_id',
        'type',
        'start_date',
        'end_date',
        'cost',
        'linked_contract_id',
        'version',
        'note',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'cost' => 'decimal:2',
        'version' => 'integer',
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
     * Quan hệ với ChangeRequest (contract liên quan)
     */
    public function linkedContract(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class, 'linked_contract_id');
    }

    /**
     * Quan hệ với BaselineHistory (lịch sử thay đổi)
     */
    public function histories(): HasMany
    {
        return $this->hasMany(BaselineHistory::class);
    }

    /**
     * Scope để lọc theo loại baseline
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope để lấy baseline mới nhất theo loại
     */
    public function scopeLatestByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type)
                    ->orderBy('version', 'desc')
                    ->orderBy('created_at', 'desc');
    }

    /**
     * Scope để lọc theo dự án
     */
    public function scopeForProject(Builder $query, string $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Kiểm tra xem baseline có phải là contract baseline không
     */
    public function isContractBaseline(): bool
    {
        return $this->type === self::TYPE_CONTRACT;
    }

    /**
     * Kiểm tra xem baseline có phải là execution baseline không
     */
    public function isExecutionBaseline(): bool
    {
        return $this->type === self::TYPE_EXECUTION;
    }

    /**
     * Tính toán số ngày của baseline
     */
    public function getDurationInDays(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * Tạo baseline mới với version tự động tăng và ghi lại lịch sử
     */
    public static function createNewVersion(array $attributes, ?string $rebaselineNote = null): self
    {
        $latestBaseline = static::where('project_id', $attributes['project_id'])
                               ->where('type', $attributes['type'])
                               ->orderBy('version', 'desc')
                               ->first();

        $oldVersion = $latestBaseline ? $latestBaseline->version : 0;
        $newVersion = $oldVersion + 1;
        
        $attributes['version'] = $newVersion;
        $newBaseline = static::create($attributes);

        // Ghi lại lịch sử nếu đây là re-baseline
        if ($latestBaseline && $rebaselineNote) {
            BaselineHistory::recordRebaseline(
                $newBaseline->id,
                $oldVersion,
                $newVersion,
                $rebaselineNote,
                $attributes['created_by']
            );
        }

        return $newBaseline;
    }

    /**
     * Thực hiện re-baseline với ghi lại lịch sử
     */
    public function rebaseline(array $updates, string $note): self
    {
        $oldVersion = $this->version;
        $newVersion = $oldVersion + 1;
        
        // Tạo baseline mới với version tăng
        $newAttributes = array_merge($this->toArray(), $updates, [
            'version' => $newVersion,
            'created_by' => $updates['created_by'] ?? $this->created_by,
        ]);
        
        unset($newAttributes['id'], $newAttributes['created_at'], $newAttributes['updated_at']);
        
        $newBaseline = static::create($newAttributes);
        
        // Ghi lại lịch sử thay đổi
        BaselineHistory::recordRebaseline(
            $newBaseline->id,
            $oldVersion,
            $newVersion,
            $note,
            $updates['created_by'] ?? $this->created_by
        );
        
        return $newBaseline;
    }

    /**
     * Lấy baseline hiện tại (version cao nhất) theo loại
     */
    public static function getCurrentBaseline(string $projectId, string $type): ?self
    {
        return static::forProject($projectId)
                    ->ofType($type)
                    ->latestByType($type)
                    ->first();
    }
}