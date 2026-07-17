<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkTemplateTrigger extends Model
{
    use HasUlids, HasFactory, TenantScope;

    public const VALID_EVENTS = [
        'task.started',
        'task.completed',
        'task.overdue',
        'phase.completed',
    ];

    public const VALID_ACTIONS = [
        'notify_role',
        'notify_user',
        'create_task',
        'update_status',
    ];

    protected $table = 'work_template_triggers';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'work_template_task_id',
        'trigger_key',
        'event',
        'action',
        'trigger_order',
        'is_active',
        'conditions_json',
        'payload_json',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'trigger_order' => 'integer',
        'is_active' => 'boolean',
        'conditions_json' => 'array',
        'payload_json' => 'array',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(WorkTemplateTask::class, 'work_template_task_id');
    }
}
