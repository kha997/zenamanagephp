<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DesignItem — theo dõi công việc thiết kế qua vòng duyệt nội bộ và phản hồi khách hàng.
 * Spec: docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md (Phase 1).
 *
 * review_status is the sole authority for the client cycle — never synced with
 * WorkInstanceStep's own internal checklist status, even when work_instance_step_id is set.
 */
class DesignItem extends Model
{
    use HasUlids;

    public const TYPE_CONCEPT = 'concept';
    public const TYPE_SCHEMATIC = 'schematic';
    public const TYPE_TECHNICAL = 'technical';
    public const TYPE_STRUCTURAL = 'structural';
    public const TYPE_MEP = 'mep';
    public const TYPE_INTERIOR = 'interior';
    public const TYPE_OTHER = 'other';

    public const VALID_TYPES = [
        self::TYPE_CONCEPT,
        self::TYPE_SCHEMATIC,
        self::TYPE_TECHNICAL,
        self::TYPE_STRUCTURAL,
        self::TYPE_MEP,
        self::TYPE_INTERIOR,
        self::TYPE_OTHER,
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_INTERNAL_REVIEW = 'internal_review';
    public const STATUS_SENT_TO_CLIENT = 'sent_to_client';
    public const STATUS_REVISION_REQUESTED = 'revision_requested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_FINAL = 'final';

    public const VALID_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_INTERNAL_REVIEW,
        self::STATUS_SENT_TO_CLIENT,
        self::STATUS_REVISION_REQUESTED,
        self::STATUS_APPROVED,
        self::STATUS_FINAL,
    ];

    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_INTERNAL_REVIEW],
        self::STATUS_INTERNAL_REVIEW => [self::STATUS_DRAFT, self::STATUS_SENT_TO_CLIENT],
        self::STATUS_SENT_TO_CLIENT => [self::STATUS_REVISION_REQUESTED, self::STATUS_APPROVED],
        self::STATUS_REVISION_REQUESTED => [self::STATUS_INTERNAL_REVIEW],
        self::STATUS_APPROVED => [self::STATUS_FINAL, self::STATUS_REVISION_REQUESTED],
        self::STATUS_FINAL => [],
    ];

    public const EVIDENCE_PHONE = 'phone';
    public const EVIDENCE_EMAIL = 'email';
    public const EVIDENCE_ZALO = 'zalo';
    public const EVIDENCE_CLIENT_PORTAL = 'client_portal';

    public const VALID_APPROVAL_EVIDENCE = [
        self::EVIDENCE_PHONE,
        self::EVIDENCE_EMAIL,
        self::EVIDENCE_ZALO,
        self::EVIDENCE_CLIENT_PORTAL,
    ];

    protected $table = 'design_items';

    protected $fillable = [
        'tenant_id',
        'project_id',
        'work_instance_step_id',
        'name',
        'item_type',
        'review_status',
        'assigned_to',
        'due_to_client_at',
        'client_feedback_notes',
        'approval_evidence',
        'created_by',
    ];

    protected $casts = [
        'due_to_client_at' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workInstanceStep(): BelongsTo
    {
        return $this->belongsTo(WorkInstanceStep::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
}
