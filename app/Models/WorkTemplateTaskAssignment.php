<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkTemplateTaskAssignment extends Model
{
    use HasUlids, HasFactory, TenantScope;

    public const ASSIGNMENT_TYPE_ASSIGNEE = 'assignee';
    public const ASSIGNMENT_TYPE_APPROVER = 'approver';
    public const ASSIGNMENT_TYPE_REVIEWER = 'reviewer';

    public const VALID_ASSIGNMENT_TYPES = [
        self::ASSIGNMENT_TYPE_ASSIGNEE,
        self::ASSIGNMENT_TYPE_APPROVER,
        self::ASSIGNMENT_TYPE_REVIEWER,
    ];

    protected $table = 'work_template_task_assignments';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'work_template_task_id',
        'assignment_key',
        'assignment_type',
        'role_code',
        'approval_order',
        'is_required',
        'conditions_json',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'approval_order' => 'integer',
        'is_required' => 'boolean',
        'conditions_json' => 'array',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(WorkTemplateTask::class, 'work_template_task_id');
    }
}
