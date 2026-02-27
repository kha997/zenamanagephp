<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkInstanceStep extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'work_instance_steps';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'work_instance_id',
        'work_template_step_id',
        'step_key',
        'name',
        'type',
        'step_order',
        'depends_on',
        'assignee_rule_json',
        'sla_hours',
        'snapshot_fields_json',
        'status',
        'assignee_id',
        'deadline_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'depends_on' => 'array',
        'assignee_rule_json' => 'array',
        'snapshot_fields_json' => 'array',
        'deadline_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function workInstance(): BelongsTo
    {
        return $this->belongsTo(WorkInstance::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(WorkInstanceFieldValue::class, 'work_instance_step_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 'work_instance_step_id');
    }
}
