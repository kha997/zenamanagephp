<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkTemplateField extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'work_template_fields';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'work_template_step_id',
        'field_key',
        'label',
        'type',
        'is_required',
        'default_value',
        'validation_json',
        'enum_options_json',
        'visibility_rule_json',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'validation_json' => 'array',
        'enum_options_json' => 'array',
        'visibility_rule_json' => 'array',
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkTemplateStep::class, 'work_template_step_id');
    }
}
