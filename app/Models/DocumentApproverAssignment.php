<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $document_id
 * @property string $actor_id
 * @property string|null $previous_approver_id
 * @property string|null $new_approver_id
 */
class DocumentApproverAssignment extends Model
{
    use HasUlids;

    protected $table = 'document_approver_assignments';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'document_id',
        'actor_id',
        'previous_approver_id',
        'new_approver_id',
    ];

    /** @param array<string, mixed> $options */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Document approver assignments are append-only.');
        }

        return parent::save($options);
    }

    public function delete(): never
    {
        throw new LogicException('Document approver assignments are append-only.');
    }

    public function forceDelete(): never
    {
        throw new LogicException('Document approver assignments are append-only.');
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function previousApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'previous_approver_id');
    }

    /** @return BelongsTo<User, $this> */
    public function newApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_approver_id');
    }
}
