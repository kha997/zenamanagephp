<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $rfi_id
 * @property string $tenant_id
 * @property string $escalated_to
 * @property string $escalated_by
 * @property \Carbon\Carbon $escalated_at
 * @property string $escalation_reason
 * @property \Carbon\Carbon|null $resolved_at
 * @property string|null $resolved_by
 * @property string|null $resolution
 * @property string|null $resolution_type
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class RfiEscalation extends Model
{
    use HasUlids;

    public const RESOLUTION_TYPE_MANUALLY_RESOLVED = 'manually_resolved';
    public const RESOLUTION_TYPE_RFI_CANCELLED = 'rfi_cancelled';

    protected $table = 'rfi_escalations';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'rfi_id',
        'tenant_id',
        'escalated_to',
        'escalated_by',
        'escalated_at',
        'escalation_reason',
        'resolved_at',
        'resolved_by',
        'resolution',
        'resolution_type',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'escalated_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Rfi, $this>
     */
    public function rfi(): BelongsTo
    {
        return $this->belongsTo(Rfi::class, 'rfi_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function escalatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function escalatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
