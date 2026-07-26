<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    protected $casts = [
        'escalated_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function rfi(): BelongsTo
    {
        return $this->belongsTo(Rfi::class, 'rfi_id');
    }

    public function escalatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to');
    }

    public function escalatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
