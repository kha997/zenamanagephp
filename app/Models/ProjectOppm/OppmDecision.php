<?php

declare(strict_types=1);

namespace App\Models\ProjectOppm;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * GAP-110 D-1 — a management decision the Owner / PM must take on a project: open, then decided (with its outcome) or
 * cancelled (the reason is kept in outcome).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $project_id
 * @property string $title
 * @property string|null $context
 * @property string|null $decision_maker_id
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property string $status
 * @property string|null $outcome
 * @property \Illuminate\Support\Carbon|null $decided_at
 * @property string|null $decided_by
 * @property string $created_by
 * @property \Illuminate\Support\Carbon $created_at
 */
class OppmDecision extends Model
{
    use HasUlids;
    use TenantScope;

    public const STATUS_OPEN = 'open';

    public const STATUS_DECIDED = 'decided';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_OPEN => 'Chờ quyết', self::STATUS_DECIDED => 'Đã quyết', self::STATUS_CANCELLED => 'Đã huỷ'];

    protected $table = 'oppm_decisions';

    protected $fillable = [
        'tenant_id', 'project_id', 'title', 'context', 'decision_maker_id', 'due_date', 'status', 'outcome',
        'decided_at', 'decided_by', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = ['due_date' => 'date', 'decided_at' => 'datetime'];
}
