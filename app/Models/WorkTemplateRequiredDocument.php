<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkTemplateRequiredDocument extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'work_template_required_documents';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'work_template_task_id',
        'work_template_checklist_item_id',
        'doc_key',
        'document_type',
        'name',
        'description',
        'doc_order',
        'is_required',
        'rules_json',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'doc_order' => 'integer',
        'is_required' => 'boolean',
        'rules_json' => 'array',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(WorkTemplateTask::class, 'work_template_task_id');
    }

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(WorkTemplateChecklistItem::class, 'work_template_checklist_item_id');
    }
}
