<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkTemplateTask extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'work_template_tasks';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'work_template_phase_id',
        'task_key',
        'name',
        'description',
        'task_type',
        'task_order',
        'default_duration_days',
        'is_required',
        'config_json',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'task_order' => 'integer',
        'default_duration_days' => 'integer',
        'is_required' => 'boolean',
        'config_json' => 'array',
    ];

    public function phase(): BelongsTo
    {
        return $this->belongsTo(WorkTemplatePhase::class, 'work_template_phase_id');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(WorkTemplateChecklistItem::class, 'work_template_task_id')->orderBy('item_order');
    }

    public function requiredDocuments(): HasMany
    {
        return $this->hasMany(WorkTemplateRequiredDocument::class, 'work_template_task_id')->orderBy('doc_order');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkTemplateTaskAssignment::class, 'work_template_task_id')
            ->orderByRaw("CASE WHEN assignment_type = 'approver' THEN 1 ELSE 0 END")
            ->orderBy('approval_order')
            ->orderBy('assignment_key');
    }

    public function triggers(): HasMany
    {
        return $this->hasMany(WorkTemplateTrigger::class, 'work_template_task_id')->orderBy('trigger_order');
    }
}
