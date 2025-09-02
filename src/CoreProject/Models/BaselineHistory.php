<?php declare(strict_types=1);

namespace Src\CoreProject\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

/**
 * Model BaselineHistory để theo dõi lịch sử thay đổi baseline
 * 
 * @property int $id
 * @property int $baseline_id
 * @property int $from_version
 * @property int $to_version
 * @property string|null $note
 * @property string $created_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class BaselineHistory extends Model
{
    use HasFactory;

    protected $table = 'baseline_history';

    protected $fillable = [
        'baseline_id',
        'from_version',
        'to_version',
        'note',
        'created_by',
    ];

    protected $casts = [
        'baseline_id' => 'integer',
        'from_version' => 'integer',
        'to_version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Quan hệ với Baseline
     */
    public function baseline(): BelongsTo
    {
        return $this->belongsTo(Baseline::class);
    }

    /**
     * Quan hệ với User (người thực hiện thay đổi)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope để lọc theo baseline
     */
    public function scopeForBaseline(Builder $query, int $baselineId): Builder
    {
        return $query->where('baseline_id', $baselineId);
    }

    /**
     * Scope để lọc theo version range
     */
    public function scopeVersionRange(Builder $query, int $fromVersion, int $toVersion): Builder
    {
        return $query->where('from_version', $fromVersion)
                    ->where('to_version', $toVersion);
    }

    /**
     * Scope để sắp xếp theo thời gian tạo mới nhất
     */
    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Tạo bản ghi lịch sử khi baseline được re-baseline
     */
    public static function recordRebaseline(
        int $baselineId,
        int $fromVersion,
        int $toVersion,
        string $note,
        string $createdBy
    ): self {
        return static::create([
            'baseline_id' => $baselineId,
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'note' => $note,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Lấy lịch sử thay đổi của một baseline
     */
    public static function getHistoryForBaseline(int $baselineId): \Illuminate\Database\Eloquent\Collection
    {
        return static::forBaseline($baselineId)
                    ->latest()
                    ->with(['creator'])
                    ->get();
    }

    /**
     * Kiểm tra xem có phải là version upgrade không
     */
    public function isVersionUpgrade(): bool
    {
        return $this->to_version > $this->from_version;
    }

    /**
     * Kiểm tra xem có phải là version downgrade không
     */
    public function isVersionDowngrade(): bool
    {
        return $this->to_version < $this->from_version;
    }

    /**
     * Lấy mức độ thay đổi version
     */
    public function getVersionDifference(): int
    {
        return $this->to_version - $this->from_version;
    }
}