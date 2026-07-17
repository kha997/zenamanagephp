<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkTemplateChecklistItem extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'work_template_checklist_items';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'work_template_task_id',
        'checklist_key',
        'label',
        'help_text',
        'item_order',
        'is_required',
        'validation_json',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'item_order' => 'integer',
        'is_required' => 'boolean',
        'validation_json' => 'array',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(WorkTemplateTask::class, 'work_template_task_id');
    }
}
