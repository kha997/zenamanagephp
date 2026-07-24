<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $submittal_id
 * @property int $revision_no
 * @property string|null $revision_summary
 * @property string $title
 * @property string $description
 * @property string|null $file_url
 * @property array<int, mixed>|null $attachment_manifest
 * @property string|null $submitted_by
 * @property \Carbon\Carbon $submitted_at
 * @property string|null $decision
 * @property string|null $decided_by
 * @property \Carbon\Carbon|null $decided_at
 * @property string|null $decision_comments
 * @property \Carbon\Carbon $created_at
 */
class SubmittalRevision extends Model
{
    use HasUlids, TenantScope;

    protected $table = 'submittal_revisions';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'submittal_id',
        'revision_no',
        'revision_summary',
        'title',
        'description',
        'file_url',
        'attachment_manifest',
        'submitted_by',
        'submitted_at',
        'decision',
        'decided_by',
        'decided_at',
        'decision_comments',
        'created_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'revision_no' => 'integer',
        'attachment_manifest' => 'array',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Submittal, $this> */
    public function submittal(): BelongsTo
    {
        return $this->belongsTo(Submittal::class, 'submittal_id');
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
