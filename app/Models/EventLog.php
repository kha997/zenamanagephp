<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EventLog model đại diện cho dòng log từ EventLogListener
 *
 * @property string $id
 * @property string|null $event_name
 * @property string|null $event_class
 * @property string|null $project_id
 * @property string|null $tenant_id
 * @property string|null $actor_id
 * @property array|null $payload
 * @property array|null $changed_fields
 * @property string|null $source_module
 * @property string|null $severity
 * @property \Illuminate\Support\Carbon|null $event_timestamp
 */
class EventLog extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'event_logs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'event_name',
        'event_type',
        'event_class',
        'project_id',
        'tenant_id',
        'actor_id',
        'entity_id',
        'payload',
        'event_data',
        'changed_fields',
        'source_module',
        'severity',
        'event_timestamp',
    ];

    protected $casts = [
        'payload' => 'array',
        'event_data' => 'array',
        'changed_fields' => 'array',
        'event_timestamp' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
