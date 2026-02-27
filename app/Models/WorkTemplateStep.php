<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkTemplateStep extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'work_template_steps';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'work_template_version_id',
        'step_key',
        'name',
        'type',
        'step_order',
        'depends_on',
        'assignee_rule_json',
        'sla_hours',
        'config_json',
    ];

    protected $casts = [
        'depends_on' => 'array',
        'assignee_rule_json' => 'array',
        'config_json' => 'array',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkTemplateVersion::class, 'work_template_version_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(WorkTemplateField::class, 'work_template_step_id');
    }
}
