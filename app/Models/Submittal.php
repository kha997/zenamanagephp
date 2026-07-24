<?php

namespace App\Models;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Traits\TenantScope;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $project_id
 * @property string $submittal_number
 * @property string|null $package_no
 * @property string $title
 * @property string $description
 * @property string $submittal_type
 * @property string|null $specification_section
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property string|null $contractor
 * @property string|null $manufacturer
 * @property string|null $file_url
 * @property string|null $submitted_by
 * @property \Carbon\Carbon|null $submitted_at
 * @property string|null $reviewed_by
 * @property \Carbon\Carbon|null $reviewed_at
 * @property string|null $review_comments
 * @property string|null $review_notes
 * @property string|null $approved_by
 * @property \Carbon\Carbon|null $approved_at
 * @property string|null $approval_comments
 * @property string|null $rejected_by
 * @property \Carbon\Carbon|null $rejected_at
 * @property string|null $rejection_reason
 * @property string|null $rejection_comments
 * @property string|null $created_by
 * @property array<int, mixed>|null $attachments
 * @property int|null $current_revision_no
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SubmittalRevision> $revisions
 * @property-read SubmittalRevision|null $currentRevision
 */
class Submittal extends Model
{
    use HasFactory, TenantScope;

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::creating(function (Submittal $submittal) {
            if (empty($submittal->id)) {
                $submittal->id = (string) Str::ulid();
            }
        });
    }

    protected $fillable = [
        'id',
        'tenant_id',
        'project_id',
        'submittal_number',
        'package_no',
        'title',
        'description',
        'submittal_type',
        'specification_section',
        'status',
        'due_date',
        'contractor',
        'manufacturer',
        'file_url',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_comments',
        'review_notes',
        'approved_by',
        'approved_at',
        'approval_comments',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'rejection_comments',
        'created_by',
        'attachments',
        'current_revision_no',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REVISING = 'revising';

    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        self::STATUS_DRAFT     => [self::STATUS_SUBMITTED],
        self::STATUS_SUBMITTED => [self::STATUS_APPROVED, self::STATUS_REJECTED],
        self::STATUS_REJECTED  => [self::STATUS_REVISING],
        self::STATUS_REVISING  => [self::STATUS_SUBMITTED],
        self::STATUS_APPROVED  => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return self::canTransition($this->status, $newStatus);
    }

    protected $casts = [
        'due_date' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'attachments' => 'array',
        'current_revision_no' => 'integer',
    ];

    /**
     * Get the project that owns the submittal.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Get the user who submitted the submittal.
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Get the user who reviewed the submittal.
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the user who approved the submittal.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who rejected the submittal.
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Get submittal status badge color.
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'bg-gray-100 text-gray-800',
            'submitted' => 'bg-blue-100 text-blue-800',
            'pending_review' => 'bg-yellow-100 text-yellow-800',
            'approved' => 'bg-green-100 text-green-800',
            'rejected' => 'bg-red-100 text-red-800',
            'revising' => 'bg-purple-100 text-purple-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Check if submittal is overdue.
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date && $this->due_date->isPast() && !in_array($this->status, ['approved', 'rejected'], true);
    }

    /**
     * Scope for submittals by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for overdue submittals.
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->whereNotIn('status', ['approved', 'rejected']);
    }

    /**
     * Scope for submittals submitted by user.
     */
    public function scopeSubmittedBy($query, $userId)
    {
        return $query->where('submitted_by', $userId);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'linked_entity_id')
            ->where('linked_entity_type', Document::ENTITY_TYPE_SUBMITTAL);
    }

    /** @return HasMany<SubmittalRevision, $this> */
    public function revisions(): HasMany
    {
        /** @phpstan-ignore return.type */
        return $this->hasMany(SubmittalRevision::class, 'submittal_id')->orderBy('revision_no');
    }

    /** @return HasOne<SubmittalRevision, $this> */
    public function currentRevision(): HasOne
    {
        /** @phpstan-ignore return.type */
        return $this->hasOne(SubmittalRevision::class, 'submittal_id')->ofMany('revision_no', 'max');
    }
}
