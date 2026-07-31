<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $opportunity_id
 * @property string $type
 * @property \Carbon\Carbon $scheduled_at
 * @property string|null $location
 * @property string|null $assigned_to
 * @property string $status
 * @property string|null $outcome_notes
 * @property string $created_by
 * @property-read Opportunity|null $opportunity
 * @property-read User|null $assignee
 */
class OpportunityAppointment extends Model
{
    use HasUlids, TenantScope;

    /** @use HasFactory<\Database\Factories\OpportunityAppointmentFactory> */
    use HasFactory;

    public const TYPE_CONSULTATION = 'consultation';
    public const TYPE_SURVEY = 'survey';
    public const TYPE_SITE_VISIT = 'site_visit';
    public const TYPE_MEETING = 'meeting';

    public const VALID_TYPES = [
        self::TYPE_CONSULTATION,
        self::TYPE_SURVEY,
        self::TYPE_SITE_VISIT,
        self::TYPE_MEETING,
    ];

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_RESCHEDULED = 'rescheduled';

    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        self::STATUS_SCHEDULED => [
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_RESCHEDULED,
        ],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_RESCHEDULED => [],
    ];

    protected $table = 'opportunity_appointments';

    protected $fillable = [
        'tenant_id',
        'opportunity_id',
        'type',
        'scheduled_at',
        'location',
        'assigned_to',
        'status',
        'outcome_notes',
        'created_by',
    ];

    /** @var array{scheduled_at: string} */
    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** @return BelongsTo<Opportunity, $this> */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
