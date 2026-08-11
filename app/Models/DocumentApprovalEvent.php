<?php declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentApprovalStatus;
use App\Models\Builders\DocumentApprovalEventBuilder;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $document_id
 * @property string|null $document_version_id
 * @property string $event
 * @property string $from_approval_status
 * @property string $to_approval_status
 * @property string $actor_id
 * @property string|null $note
 * @property array<string, mixed> $context
 */
#[UseEloquentBuilder(DocumentApprovalEventBuilder::class)]
class DocumentApprovalEvent extends Model
{
    use HasUlids;

    private bool $validatedInsertInProgress = false;

    private const EVENTS = [
        'submitted',
        'approved',
        'rejected',
        'reopened',
        'reactivated',
    ];

    private const VERSION_REQUIRED_EVENTS = [
        'submitted',
        'approved',
        'rejected',
        'reopened',
    ];

    protected $table = 'document_approval_events';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'document_id',
        'document_version_id',
        'event',
        'from_approval_status',
        'to_approval_status',
        'actor_id',
        'note',
        'context',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @param array<string, mixed> $options */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Document approval events are append-only.');
        }

        $this->validateCreation();
        $this->validatedInsertInProgress = true;

        try {
            return parent::save($options);
        } finally {
            $this->validatedInsertInProgress = false;
        }
    }

    public function delete(): never
    {
        throw new LogicException('Document approval events are append-only.');
    }

    public function forceDelete(): never
    {
        throw new LogicException('Document approval events are append-only.');
    }

    public function allowsValidatedApprovalEventInsert(): bool
    {
        return $this->validatedInsertInProgress;
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<DocumentVersion, $this> */
    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    private function validateCreation(): void
    {
        if (! in_array($this->event, self::EVENTS, true)) {
            throw new LogicException('Unsupported document approval event.');
        }

        if (DocumentApprovalStatus::tryFrom($this->from_approval_status) === null
            || DocumentApprovalStatus::tryFrom($this->to_approval_status) === null) {
            throw new LogicException('Document approval event statuses must be canonical.');
        }

        $document = Document::query()
            ->withoutGlobalScopes()
            ->whereKey($this->document_id)
            ->where('tenant_id', $this->tenant_id)
            ->first();

        if ($document === null) {
            throw new LogicException('Document approval event tenant does not own the document.');
        }

        if ($this->document_version_id === null) {
            if (in_array($this->event, self::VERSION_REQUIRED_EVENTS, true)) {
                throw new LogicException('Document approval event requires a document version.');
            }

            if ($this->context !== ['legacy_without_current_version' => true]) {
                throw new LogicException('Only explicit legacy reactivation may omit a document version.');
            }

            return;
        }

        $versionBelongsToDocument = DocumentVersion::query()
            ->whereKey($this->document_version_id)
            ->where('document_id', $document->id)
            ->exists();

        if (! $versionBelongsToDocument) {
            throw new LogicException('Document approval event version does not belong to the tenant document.');
        }
    }
}
